<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Master data is a short list — a branch has a dozen departments, not a
     * thousand. A generous page size keeps the whole list on one screen, and
     * the pagination footer stays as a safety net so a bigger branch is still
     * fully reachable.
     */
    private const PER_PAGE = 50;

    /** @var list<string> */
    private const MONTH_NAMES = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des',
    ];

    public function index(): Response
    {
        Gate::authorize('department.read');

        $tenant = tenant();

        $departments = Department::query()
            ->with([
                'divisions' => fn ($query) => $query
                    ->select(['id', 'department_id', 'nama_division'])
                    ->orderBy('nama_division'),
                'hodEmployee',
                'hod.employee',
                'manager',
            ])
            ->withCount(['employees', 'positions', 'divisions'])
            ->orderBy('kode_department')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Department $department) => $department->append('hod_display'));

        return Inertia::render('departments/index', [
            'departments' => $departments,
            'stats' => $this->orgStats(),
            'last_synced_at' => $tenant?->last_department_synced_at?->toIso8601String(),
            'last_synced_label' => $this->formatSyncedAt($tenant?->last_department_synced_at),
        ]);
    }

    /**
     * The three numbers the index page is actually about: how many
     * departments exist, how many people they hold, and where the HOD seat is
     * still empty. The last figure decides whether the page shows a warning.
     *
     * @return array{departments: int, employees: int, without_hod: int, max_employees: int}
     */
    private function orgStats(): array
    {
        $maxEmployees = Employee::query()
            ->whereNotNull('department_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('department_id')
            ->orderByDesc('aggregate')
            ->value('aggregate');

        return [
            'departments' => Department::query()->count(),
            'employees' => Employee::query()->whereNotNull('department_id')->count(),
            'without_hod' => Department::query()
                ->whereNull('hod_user_id')
                ->whereNull('hod_employee_id')
                ->count(),
            'max_employees' => (int) ($maxEmployees ?? 0),
        ];
    }

    /**
     * Format the last-sync timestamp on the server.
     *
     * A locale-dependent client format makes the server markup and the client
     * render disagree, which React reports as a hydration mismatch and pays
     * for by discarding the whole server-rendered tree. Formatting here keeps
     * one locale and one timezone for both sides.
     */
    private function formatSyncedAt(?CarbonInterface $syncedAt): ?string
    {
        if ($syncedAt === null) {
            return null;
        }

        $local = $syncedAt->copy()->setTimezone(config('app.timezone'));

        // The month name is concatenated, never passed through format():
        // "Okt" would be read as format specifiers (O = UTC offset, k = hour,
        // t = days in month).
        return $local->format('j').' '.self::MONTH_NAMES[$local->month - 1].' '.$local->format('Y, H:i');
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
        Gate::authorize('department.read');

        $department = $this->resolveDepartmentFromRoute($request);

        return Inertia::render('departments/show', [
            'department' => $department
                ->load([
                    'divisions' => fn ($query) => $query->orderBy('nama_division'),
                    'positions' => fn ($query) => $query
                        ->with('division')
                        ->orderBy('nama_position'),
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
