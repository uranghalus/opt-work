<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        $tenant = tenant();

        $departments = Department::query()
            ->with(['hodEmployee', 'hod.employee', 'manager'])
            ->withCount(['employees', 'positions', 'divisions'])
            ->orderBy('kode_department')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Department $department) => $department->append('hod_display'));

        return Inertia::render('departments/index', [
            'departments' => $departments,
            'last_synced_at' => $tenant?->last_department_synced_at,
        ]);
    }

    /**
     * Departments are resolved manually against the route's tenant: implicit
     * binding runs before tenancy bootstraps, so the BelongsToTenant scope
     * cannot be trusted at bind time (same pattern as EmployeeController and
     * WorkOrderController).
     */
    protected function resolveDepartmentFromRoute(Request $request): Department
    {
        $tenant = Tenant::whereCode((string) $request->route('tenant'))->first();
        abort_unless($tenant !== null, 404);

        return Department::withoutGlobalScope('tenant')
            ->where('id', (string) $request->route('department'))
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
    }

    public function show(Request $request): Response
    {
        $department = $this->resolveDepartmentFromRoute($request);

        return Inertia::render('departments/show', [
            'department' => $department
                ->load([
                    'divisions' => fn ($query) => $query->orderBy('nama_division'),
                    'hod.employee',
                    'hodEmployee',
                    'manager',
                ])
                ->loadCount(['employees', 'positions', 'divisions'])
                ->append('hod_display'),
        ]);
    }

    /**
     * Run the Optigate department sync from the UI. Idempotent, same as the
     * scheduled command; the button only saves typing the command.
     */
    public function sync(): RedirectResponse
    {
        $tenant = tenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return back()->with('error', 'Tidak ada tenant aktif.');
        }

        $exitCode = Artisan::call('app:sync-departments', ['--tenant' => $tenantId]);
        $output = Artisan::output();

        if ($exitCode !== Command::SUCCESS) {
            Log::error('Manual department sync from UI failed.', ['output' => $output, 'tenant_id' => $tenantId]);

            return back()->with('error', 'Sinkronisasi gagal. Periksa log untuk detail.');
        }

        // Refresh so the index page picks up the updated last_department_synced_at.
        $tenant->refresh();

        $summary = collect(explode("\n", $output))
            ->map(fn (string $line) => trim($line))
            ->first(fn (string $line) => str_contains($line, 'Sinkronisasi selesai'));

        return back()->with('success', $summary ?? 'Sinkronisasi selesai.');
    }
}
