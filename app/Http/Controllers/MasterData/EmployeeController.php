<?php

namespace App\Http\Controllers\MasterData;

use App\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\LinkAkunRequest;
use App\Http\Requests\Employee\SyncAkunRolesRequest;
use App\Http\Requests\EmployeeIndexRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Queries\EmployeeQuery;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
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
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(EmployeeIndexRequest $request): Response
    {
        Gate::authorize('employee.read');

        $query = (new EmployeeQuery)->build(
            $request->search(),
            $request->sort(),
            $request->direction(),
            $request->departmentId(),
            $request->divisionId(),
            $request->status(),
            $request->akun(),
        );

        $perPage = $request->perPage();

        $employees = $query->paginate($perPage)
            ->withQueryString()
            ->through(fn (Employee $employee) => EmployeeResource::make($employee)->resolve());

        return Inertia::render('employees/index', [
            'employees' => $employees,
            // Filter options for the organizational selects (tenant-scoped by
            // the BelongsToTenant global scope — same cabang only).
            'filter_options' => [
                'departments' => Department::query()
                    ->orderBy('nama_department')
                    ->get(['id', 'kode_department', 'nama_department'])
                    ->map(fn (Department $department): array => [
                        'id' => $department->id,
                        'label' => trim("{$department->kode_department} — {$department->nama_department}") ?: $department->nama_department,
                    ])
                    ->all(),
                'divisions' => Division::query()
                    ->orderBy('nama_division')
                    ->get(['id', 'kode_division', 'nama_division'])
                    ->map(fn (Division $division): array => [
                        'id' => $division->id,
                        'label' => trim("{$division->kode_division} — {$division->nama_division}") ?: $division->nama_division,
                    ])
                    ->all(),
            ],
            'filters' => [
                'search' => $request->search() ?? '',
                'per_page' => $perPage,
                'sort' => $request->sort(),
                'direction' => $request->direction(),
                'department_id' => $request->departmentId() ?? '',
                'division_id' => $request->divisionId() ?? '',
                'status' => $request->status() ?? '',
                'akun' => $request->akun() ?? '',
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

        // Only accounts that can safely be linked to this karyawan are
        // offered by the search endpoint (searchAkun) — the old static
        // limit(100) list in this payload is gone.

        return Inertia::render('employees/show', [
            'employee' => EmployeeResource::make($employee)->resolve(),
            'akun' => $akun,
            'assignable_roles' => $assignableRoles,
        ]);
    }

    /**
     * Pencarian server-side kandidat akun yang dapat ditautkan (Q6 grill
     * 2026-10-21, langkah 6): same cabang, unlinked, non-superadmin — aturan
     * yang sama dengan linkAkun, digali lewat param `search` + paginasi.
     * Menggantikan daftar statis limit(100) di payload show.
     */
    public function searchAkun(Request $request): JsonResponse
    {
        Gate::authorize('akun.update');

        $employee = $this->resolveEmployeeFromRoute($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ]);

        $searchTerm = filled($validated['search'] ?? null) ? '%'.$validated['search'].'%' : null;

        $accounts = User::query()
            ->where('tenant_id', $employee->tenant_id)
            ->whereNull('employee_id')
            ->where('is_super_admin', false)
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'super_admin'))
            ->when($searchTerm !== null, fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)))
            ->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return response()->json($accounts);
    }

    /**
     * Diblokir a karyawan (record-level block, superadmin only). The block
     * survives portal syncs; the linked akun, if any, is untouched.
     */
    public function ban(Request $request): RedirectResponse
    {
        $this->authorizeBan($request);

        $employee = $this->resolveEmployeeFromRoute($request);

        DB::transaction(function () use ($request, $employee): void {
            $employee->forceFill(['banned_at' => now()])->save();

            $this->audit->record(
                action: AuditAction::EmployeeBan,
                auditable: $employee,
                before: ['banned_at' => null],
                after: ['banned_at' => $employee->banned_at?->toISOString()],
                actorId: $request->user()?->id,
                tenantId: $employee->tenant_id,
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

        DB::transaction(function () use ($request, $employee): void {
            $previous = $employee->banned_at;

            $employee->forceFill(['banned_at' => null])->save();

            $this->audit->record(
                action: AuditAction::EmployeeUnban,
                auditable: $employee,
                before: ['banned_at' => $previous?->toISOString()],
                after: ['banned_at' => null],
                actorId: $request->user()?->id,
                tenantId: $employee->tenant_id,
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

        DB::transaction(function () use ($akun, $final, $request, $employee): void {
            $before = $akun->getRoleNames()->all();

            $akun->syncRoles($final);

            $this->audit->record(
                action: AuditAction::AkunRolesSync,
                auditable: $akun,
                before: ['roles' => $before],
                after: ['roles' => $akun->getRoleNames()->all()],
                actorId: $request->user()?->id,
                tenantId: $employee->tenant_id,
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

        $akun = User::query()
            ->whereKey($request->integer('user_id'))
            ->where('tenant_id', $employee->tenant_id)
            ->whereNull('employee_id')
            ->where('is_super_admin', false)
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'super_admin'))
            ->first();

        if ($akun === null) {
            return back()->withErrors([
                'user_id' => 'Akun tidak tersedia di cabang ini, sudah tertaut, atau bukan akun yang dapat ditautkan.',
            ]);
        }

        try {
            DB::transaction(function () use ($akun, $employee, $request): void {
                $akun->forceFill(['employee_id' => $employee->id])->save();

                // Q4 (grill 2026-10-21): provisioning dasar saat link — akun
                // tanpa peran sama sekali langsung mendapat `karyawan` supaya
                // tautan tidak menghasilkan akun yang mati di login (deny
                // no_role). Akun yang SUDAH ber-role tidak disentuh. Ini
                // sebidang auto-`guest` (ADR-0006), bukan grant manual —
                // UI grant/revoke tetap superadmin-only (ADR-0008/Q6).
                $provisionedRole = null;

                if (! $akun->roles()->exists()) {
                    $akun->assignRole('karyawan');
                    $provisionedRole = 'karyawan';
                }

                $this->audit->record(
                    action: AuditAction::AkunLink,
                    auditable: $employee,
                    before: ['user_id' => null],
                    after: [
                        'user_id' => $akun->id,
                        'auto_role' => $provisionedRole,
                    ],
                    actorId: $request->user()?->id,
                    tenantId: $employee->tenant_id,
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
            $akun->forceFill(['employee_id' => null])->save();

            $this->audit->record(
                action: AuditAction::AkunUnlink,
                auditable: $employee,
                before: ['user_id' => $akun->id],
                after: ['user_id' => null],
                actorId: $request->user()?->id,
                tenantId: $employee->tenant_id,
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
