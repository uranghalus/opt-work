<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Position;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
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
        $query = Position::query()
            ->with(['department', 'division'])
            ->withCount('employees')
            ->orderBy('nama_position');

        // Search filter
        if (request()->filled('search')) {
            $query->where('nama_position', 'like', '%'.request()->search.'%');
        }

        // Department filter
        if (request()->filled('department_id')) {
            $query->where('department_id', request()->department_id);
        }

        // Division filter
        if (request()->filled('division_id')) {
            $query->where('division_id', request()->division_id);
        }

        // Has employees filter
        if (request()->has('has_employees')) {
            $hasEmployees = request()->boolean('has_employees');
            $query->whereHas('employees', function ($q) {
                // No additional constraint needed; whereHas handles existence
            });
            if (! $hasEmployees) {
                $query->whereDoesntHave('employees');
            }
        }

        // Vacancy filter (occupied/vacant)
        if (request()->filled('vacancy')) {
            $vacancy = request()->vacancy;
            if ($vacancy === 'occupied') {
                $query->whereHas('employees');
            } elseif ($vacancy === 'vacant') {
                $query->whereDoesntHave('employees');
            }
        }

        $positions = $query->paginate(10)->withQueryString();

        // Stats for Registry Band
        $stats = [
            'total' => Position::query()->count(),
            'filled' => Position::query()->has('employees')->count(),
            'synced' => Position::query()->whereNotNull('optigate_position_id')->count(),
            'max_employees' => Position::query()
                ->withCount('employees')
                ->orderBy('employees_count', 'desc')
                ->value('employees_count') ?? 0,
        ];

        // Filter options for selects
        $departments = Department::query()
            ->select('id', 'kode_department', 'nama_department')
            ->orderBy('kode_department')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'kode_department' => $d->kode_department,
                'nama_department' => $d->nama_department,
            ]);

        $divisions = Division::query()
            ->select('id', 'nama_division')
            ->orderBy('nama_division')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'nama_division' => $d->nama_division,
            ]);

        $tenant = tenant();

        return Inertia::render('positions/index', [
            'positions' => $positions,
            'stats' => $stats,
            'filter_options' => [
                'departments' => $departments,
                'divisions' => $divisions,
            ],
            'last_synced_at' => $tenant?->last_position_synced_at?->toIso8601String(),
            'last_synced_label' => $this->formatSyncedAt($tenant?->last_position_synced_at),
        ]);
    }

    /**
     * The position is resolved manually against the route's tenant: implicit
     * binding runs before tenancy bootstraps, so the BelongsToTenant scope
     * cannot be trusted at bind time (same pattern as DepartmentController and
     * EmployeeController).
     */
    protected function resolvePositionFromRoute(Request $request): Position
    {
        $tenant = Tenant::whereCode((string) $request->route('tenant'))->first();
        abort_unless($tenant !== null, 404);

        return Position::withoutGlobalScope('tenant')
            ->where('id', (string) $request->route('position'))
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
    }

    public function show(Request $request): Response
    {
        $position = $this->resolvePositionFromRoute($request);

        return Inertia::render('positions/show', [
            'position' => $position->load([
                'department',
                'division',
                'parentPosition',
                'children',
                'employees' => function ($query) {
                    $query->with(['user'])
                        ->select('id', 'nama_employee', 'nik_employee', 'photo_url', 'banned_at')
                        ->orderBy('nama_employee')
                        ->limit(50);
                },
            ])->loadCount('employees'),
        ]);
    }

    /**
     * Run the Optigate position sync from the UI. Idempotent, same as the
     * scheduled command; the button only saves typing the command.
     */
    public function sync(): RedirectResponse
    {
        $tenant = tenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return back()->with('error', 'Tidak ada tenant aktif.');
        }

        $exitCode = Artisan::call('app:sync-positions', ['--tenant' => $tenantId]);
        $output = Artisan::output();

        if ($exitCode !== Command::SUCCESS) {
            Log::error('Manual position sync from UI failed.', ['output' => $output, 'tenant_id' => $tenantId]);

            return back()->with('error', 'Sinkronisasi gagal. Periksa log untuk detail.');
        }

        // Refresh so the index page picks up the updated last_position_synced_at.
        $tenant->refresh();

        $summary = collect(explode("\n", $output))
            ->map(fn (string $line) => trim($line))
            ->first(fn (string $line) => str_contains($line, 'Sinkronisasi selesai'));

        return back()->with('success', $summary ?? 'Sinkronisasi selesai.');
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
}
