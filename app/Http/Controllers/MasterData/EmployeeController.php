<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\LinkAkunRequest;
use App\Http\Requests\Employee\SyncAkunRolesRequest;
use App\Http\Requests\EmployeeIndexRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Queries\EmployeeQuery;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(EmployeeIndexRequest $request): Response
    {
        Gate::authorize('employee.read');

        $filters = $request->filters();

        $query = (new EmployeeQuery)->build(
            $request->search(),
            $request->sort(),
            $request->direction(),
            $filters
        );

        $perPage = $request->perPage();

        $employees = $query->paginate($perPage)
            ->withQueryString()
            ->through(fn (Employee $employee) => EmployeeResource::make($employee)->resolve());

        // Summary reflects the current filters across the whole tenant, not
        // just the visible page — otherwise the metric would change meaning
        // whenever the user changes "Per halaman".
        $summaryQuery = (new EmployeeQuery)->build(
            $request->search(),
            $request->sort(),
            $request->direction(),
            $filters
        );

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'summary' => [
                'total' => $summaryQuery->count(),
                'with_account' => (clone $summaryQuery)->whereHas('user')->count(),
                'missing_department' => (clone $summaryQuery)->whereNull('department_id')->count(),
            ],
            'filters' => array_merge([
                'search' => $request->search() ?? '',
                'per_page' => $perPage,
                'sort' => $request->sort(),
                'direction' => $request->direction(),
            ], $filters),
            // Server-driven filter options: the lists live in their own
            // tenant-scoped tables, so the page never hardcodes them.
            'filter_options' => [
                'departments' => Department::query()
                    ->orderBy('nama_department')
                    ->get(['id', 'kode_department', 'nama_department']),
                'divisions' => Division::query()
                    ->orderBy('nama_division')
                    ->get(['id', 'kode_division', 'nama_division']),
                'positions' => Position::query()
                    ->orderBy('nama_position')
                    ->get(['id', 'nama_position']),
            ],
        ]);
    }

    /**
     * The karyawan is resolved manually against the route's tenant: implicit
     * binding runs before tenancy bootstraps, so the BelongsToTenant scope
     * cannot be trusted at bind time (same reason ban/unban and
     * WorkOrderController resolve their own models).
     */
    public function show(Request $request): Response
    {
        Gate::authorize('employee.read');

        $employee = $this->resolveEmployeeFromRoute($request);
        $employee->load(['department', 'position', 'division', 'user']);

        $akunUser = $employee->user;
        $canReadAkun = (bool) $request->user()?->can('akun.read');

        $akun = null;

        if ($canReadAkun) {
            $akun = $akunUser === null
                ? ['exists' => false]
                : [
                    'exists' => true,
                    'email' => $akunUser->email,
                    'roles' => $akunUser->getRoleNames()->toArray(),
                ];
        }

        // Role grant/revoke is a platform-superadmin capability (ADR-0008).
        // akun.update still gates link/unlink, but it no longer exposes the
        // assignable role list.
        $assignableRoles = $request->user()?->isSuperAdmin()
            ? Role::query()
                ->where('name', '!=', 'super_admin')
                ->orderBy('name')
                ->pluck('name')
                ->all()
            : [];

        // Kandidat akun tidak lagi dikirim sebagai prop statis — combobox di
        // halaman mengambil mereka dari endpoint akun/search (paginasi).

        return Inertia::render('employees/show', [
            'employee' => EmployeeResource::make($employee)->resolve(),
            'akun' => $akun,
            'assignable_roles' => $assignableRoles,
        ]);
    }

    /**
     * Kombobox async untuk memilih kandidat akun saat menautkan: pencarian +
     * paginasi menggantikan daftar statis limit(100). Kandidat memakai
     * aturan yang sama dengan linkAkun (linkCandidates).
     */
    public function akunSearch(Request $request): JsonResponse
    {
        Gate::authorize('akun.update');

        $employee = $this->resolveEmployeeFromRoute($request);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $search = trim($validated['q'] ?? '');

        $users = $this->linkCandidates($employee)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = "%{$search}%";

                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 10)
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return response()->json($users);
    }

    /**
     * Kandidat tautan akun — hanya akun cabang karyawan ini yang belum
     * tertaut dan bukan super admin. Aturan tunggal untuk linkAkun dan
     * akun/search (pembatasan ganda = sumber bug drift).
     */
    private function linkCandidates(Employee $employee): Builder
    {
        return User::query()
            ->where('tenant_id', $employee->tenant_id)
            ->whereNull('employee_id')
            ->where('is_super_admin', false)
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'super_admin'));
    }

    /**
     * Diblokir a karyawan (record-level block, superadmin only). The block
     * survives portal syncs; the linked akun, if any, keeps its roles and
     * link — but its live sessions are revoked here, and the web-stack
     * guard (EnsureEmployeeNotBanned) bounces anything that slips through.
     */
    public function ban(Request $request): RedirectResponse
    {
        $this->authorizeBan($request);

        $employee = $this->resolveEmployeeFromRoute($request);

        $before = ['banned_at' => $employee->banned_at?->toISOString()];

        DB::transaction(function () use ($employee, $request, $before): void {
            $employee->forceFill(['banned_at' => now()])->save();

            // Q7: cabut sesi aktif akun terkait seketika (driver database,
            // config/session.php); baris nol = tidak ada yang terlewat.
            $akunId = $employee->user()->first()?->getKey();

            if ($akunId !== null) {
                DB::table('sessions')->where('user_id', $akunId)->delete();
            }

            AuditEvent::record(
                $employee,
                $request->user(),
                'employee.ban',
                $before,
                ['banned_at' => $employee->banned_at?->toISOString()],
            );
        });

        return back()->with('success', "{$employee->nama_employee} diblokir.");
    }

    /**
     * Clear the blokir on a karyawan (superadmin only).
     */
    public function unban(Request $request): RedirectResponse
    {
        $this->authorizeBan($request);

        $employee = $this->resolveEmployeeFromRoute($request);

        $before = ['banned_at' => $employee->banned_at?->toISOString()];

        DB::transaction(function () use ($employee, $request, $before): void {
            $employee->forceFill(['banned_at' => null])->save();

            AuditEvent::record(
                $employee,
                $request->user(),
                'employee.unban',
                $before,
                ['banned_at' => null],
            );
        });

        return back()->with('success', "Blokir {$employee->nama_employee} dibatalkan.");
    }

    /**
     * Run the Optigate employee sync from the UI. Idempotent, same as the
     * scheduled command; the button only saves typing the command.
     */
    public function sync(): RedirectResponse
    {
        Gate::authorize('employee.read');

        $tenant = tenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return back()->with('error', 'Tidak ada tenant aktif.');
        }

        $exitCode = Artisan::call('app:sync-employees', ['--tenant' => $tenantId]);
        $output = Artisan::output();

        if ($exitCode !== Command::SUCCESS) {
            Log::error('Manual employee sync from UI failed.', ['output' => $output, 'tenant_id' => $tenantId]);

            return back()->with('error', 'Sinkronisasi gagal. Periksa log untuk detail.');
        }

        $summary = collect(explode("\n", $output))
            ->map(fn (string $line) => trim($line))
            ->first(fn (string $line) => str_contains($line, 'Sinkronisasi selesai'));

        return back()->with('success', $summary ?? 'Sinkronisasi selesai.');
    }

    /**
     * Resolve the {employee} of the {tenant} route, refusing any karyawan
     * that does not belong to that cabang (no IDOR across cabang).
     */
    protected function resolveEmployeeFromRoute(Request $request): Employee
    {
        $tenant = Tenant::whereCode((string) $request->route('tenant'))->first();
        abort_unless($tenant !== null, 404);

        return Employee::withoutGlobalScope('tenant')
            ->where('id', (string) $request->route('employee'))
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
    }

    /**
     * Sinkronisasi peran akun karyawan (ADR-0008). Only the platform
     * superadmin may grant or revoke roles; akun.update remains the gate for
     * link/unlink and linkable-account visibility. The submitted set replaces
     * the current set inside one transaction; the pair invariant and the
     * super_admin seal are guarded before any write, and the write touches
     * only Spatie roles — never identity flags or department links.
     */
    public function syncAkunRoles(SyncAkunRolesRequest $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->isSuperAdmin(),
            403,
            'Hanya superadmin yang dapat mengubah peran akun.',
        );

        $employee = $this->resolveEmployeeFromRoute($request);
        $akun = $employee->user()->first();

        abort_unless($akun !== null, 403, 'Karyawan ini tidak memiliki akun pengguna.');

        // Roles are global, employee data is per-cabang. Never let even a
        // superadmin change an account linked to an employee in a different
        // cabang.
        abort_unless(
            $akun->tenant_id !== null && $akun->tenant_id === $employee->tenant_id,
            403,
            'Akun pengguna ini bukan milik cabang karyawan tersebut.',
        );

        if ($akun->hasRole('super_admin')) {
            return back()->withErrors(['roles' => 'Peran akun super admin tidak dapat diubah di sini.']);
        }

        $requested = $request->validated('roles');

        // Pair invariant (ADR-0005 decision 5): dropping karyawan while the
        // submission keeps field_staff would silently break the pair — reject
        // it instead of re-adding the role behind the editor's back.
        if (in_array('field_staff', $requested, true) && ! in_array('karyawan', $requested, true) && $akun->hasRole('field_staff')) {
            return back()->withErrors(['roles' => 'field_staff selalu berpasangan dengan karyawan. Lepaskan field_staff terlebih dahulu, atau tetap sertakan karyawan.']);
        }

        // Granting field_staff auto-attaches karyawan (the execution superset
        // keeps its baseline role); revoking field_staff leaves karyawan.
        $final = $requested;
        if (in_array('field_staff', $final, true) && ! in_array('karyawan', $final, true)) {
            $final[] = 'karyawan';
        }

        $actor = $request->user();

        DB::transaction(function () use ($akun, $employee, $actor, $final): void {
            $before = ['roles' => $akun->getRoleNames()->sort()->values()->all()];

            $akun->syncRoles($final);

            AuditEvent::record(
                $employee,
                $actor,
                'akun.roles.sync',
                $before,
                ['roles' => $akun->getRoleNames()->sort()->values()->all()],
                ['user_id' => $akun->getKey(), 'user_email' => $akun->email],
            );
        });

        return back()->with('success', "Peran akun {$employee->nama_employee} diperbarui.");
    }

    /**
     * Tautkan satu akun tenant yang belum terhubung ke karyawan ini. The
     * account is resolved against the employee's cabang; cross-tenant and
     * already-linked accounts are never valid targets.
     */
    public function linkAkun(LinkAkunRequest $request): RedirectResponse
    {
        Gate::authorize('akun.update');

        $employee = $this->resolveEmployeeFromRoute($request);

        if ($employee->user()->exists()) {
            return back()->withErrors([
                'user_id' => 'Karyawan ini sudah memiliki akun. Lepas tautan lama terlebih dahulu.',
            ]);
        }

        $akun = $this->linkCandidates($employee)
            ->whereKey($request->integer('user_id'))
            ->first();

        if ($akun === null) {
            return back()->withErrors([
                'user_id' => 'Akun tidak tersedia di cabang ini, sudah tertaut, atau bukan akun yang dapat ditautkan.',
            ]);
        }

        try {
            DB::transaction(function () use ($akun, $employee, $request): void {
                $akun->forceFill(['employee_id' => $employee->id])->save();

                // Q4: a roleless linked account would bounce at its next
                // login (LoginLanding no_role) — linking provisions the
                // zero-permission `guest` role, the same bootstrap the SSO
                // login applies (LoginProvisioningService). Accounts with
                // any role are untouched; unlink never revokes what a link
                // provisioned (ADR-0008 amendment).
                $after = ['user_id' => $akun->getKey(), 'user_email' => $akun->email];

                if ($akun->roles()->doesntExist()) {
                    $akun->assignRole('guest');
                    $after['auto_role'] = 'guest';
                }

                // Dalam transaksi yang sama: audit gagal → link ikut
                // ter-rollback (atomic), menggantikan Log::info lama.
                AuditEvent::record(
                    $employee,
                    $request->user(),
                    'akun.link',
                    ['user_id' => null],
                    $after,
                );
            });
        } catch (QueryException $exception) {
            // The unique index catches the race where another request linked
            // the same akun between the lookup and the write.
            Log::warning('Employee account link failed.', [
                'employee_id' => $employee->id,
                'user_id' => $akun->id,
                'tenant_id' => $employee->tenant_id,
                'exception' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'user_id' => 'Akun gagal ditautkan. Muat ulang halaman lalu coba lagi.',
            ]);
        }

        return back()->with('success', "Akun {$akun->email} ditautkan ke {$employee->nama_employee}.");
    }

    /**
     * Lepas tautan akun dari karyawan. The akun itself, its roles, and its
     * sessions are left intact — only the employee_id relation is cleared.
     */
    public function unlinkAkun(Request $request): RedirectResponse
    {
        Gate::authorize('akun.update');

        $employee = $this->resolveEmployeeFromRoute($request);
        $akun = $employee->user()->first();

        abort_unless($akun !== null, 404, 'Karyawan ini tidak memiliki akun pengguna.');

        if ($akun->is_super_admin || $akun->hasRole('super_admin')) {
            return back()->withErrors([
                'akun' => 'Akun super admin tidak dapat dilepas dari sini.',
            ]);
        }

        DB::transaction(function () use ($akun, $employee, $request): void {
            $before = ['user_id' => $akun->getKey(), 'user_email' => $akun->email];

            $akun->forceFill(['employee_id' => null])->save();

            AuditEvent::record(
                $employee,
                $request->user(),
                'akun.unlink',
                $before,
                ['user_id' => null],
            );
        });

        return back()->with('success', "Tautan akun {$akun->email} dilepas dari {$employee->nama_employee}.");
    }

    /**
     * Blokir management is a superadmin capability for now.
     */
    protected function authorizeBan(Request $request): void
    {
        abort_unless($request->user()?->is_super_admin, 403, 'Hanya superadmin yang dapat memblokir karyawan.');
    }
}
