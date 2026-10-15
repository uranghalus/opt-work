<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeIndexRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Tenant;
use App\Queries\EmployeeQuery;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(EmployeeIndexRequest $request): Response
    {
        $query = (new EmployeeQuery)->build(
            $request->search(),
            $request->sort(),
            $request->direction()
        );

        $perPage = $request->perPage();

        $employees = $query->paginate($perPage)
            ->withQueryString()
            ->through(fn (Employee $employee) => EmployeeResource::make($employee)->resolve());

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => [
                'search' => $request->search() ?? '',
                'per_page' => $perPage,
                'sort' => $request->sort(),
                'direction' => $request->direction(),
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
        $employee = $this->resolveEmployeeFromRoute($request);
        $employee->load(['department', 'position', 'division']);

        $akun = null;

        if ($request->user()?->can('akun.read')) {
            $akunUser = $employee->user()->first();

            $akun = $akunUser === null
                ? ['exists' => false]
                : [
                    'exists' => true,
                    'email' => $akunUser->email,
                    'roles' => $akunUser->getRoleNames()->toArray(),
                ];
        }

        return Inertia::render('employees/show', [
            'employee' => $employee,
            'akun' => $akun,
        ]);
    }

    /**
     * Diblokir a karyawan (record-level block, superadmin only). The block
     * survives portal syncs; the linked akun, if any, is untouched.
     */
    public function ban(Request $request): RedirectResponse
    {
        $this->authorizeBan($request);

        $employee = $this->resolveEmployeeFromRoute($request);

        $employee->forceFill(['banned_at' => now()])->save();

        return back()->with('success', "{$employee->nama_employee} diblokir.");
    }

    /**
     * Clear the blokir on a karyawan (superadmin only).
     */
    public function unban(Request $request): RedirectResponse
    {
        $this->authorizeBan($request);

        $employee = $this->resolveEmployeeFromRoute($request);

        $employee->forceFill(['banned_at' => null])->save();

        return back()->with('success', "Blokir {$employee->nama_employee} dibatalkan.");
    }

    /**
     * Run the Optigate employee sync from the UI. Idempotent, same as the
     * scheduled command; the button only saves typing the command.
     */
    public function sync(): RedirectResponse
    {
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
     * Blokir management is a superadmin capability for now.
     */
    protected function authorizeBan(Request $request): void
    {
        abort_unless($request->user()?->is_super_admin, 403, 'Hanya superadmin yang dapat memblokir karyawan.');
    }
}
