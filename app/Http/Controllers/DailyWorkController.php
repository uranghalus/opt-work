<?php

namespace App\Http\Controllers;

use App\Models\DailyWork;
use App\Models\Employee;
use App\Services\DailyWorkService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FR-5.3–5.4: daftar harian Daily Work milik karyawan yang login
 * (materialize-on-view dulu, lalu render), plus update status/lokasi
 * item miliknya sendiri (FR-5.4). Template CRUD + adhoc + team view
 * ada di controller ini juga (Task 3).
 */
class DailyWorkController extends Controller
{
    public function __construct(private readonly DailyWorkService $dailyWorkService) {}

    public function index(Request $request): Response
    {
        Gate::authorize('daily-work.read');

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,in_progress,done'],
        ]);

        // "Hari ini" mengikuti timezone app (Asia/Makassar — D2).
        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'])
            : CarbonImmutable::today();

        $user = $request->user();
        $employee = $user->employee_id ? Employee::find($user->employee_id) : null;

        if ($employee !== null) {
            $this->dailyWorkService->materializeFor($employee, $date);
        }

        $dailyWork = DailyWork::query()
            ->when($employee !== null, fn ($query) => $query->where('employee_id', $employee->getKey()))
            ->where('work_date', $date->toDateString())
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DailyWork $work) => [
                'id' => $work->id,
                'work_date' => $work->work_date->toDateString(),
                'title' => $work->title,
                'description' => $work->description,
                'location' => $work->location,
                'status' => $work->status,
                'done_at' => $work->done_at?->toDateTimeString(),
                'source' => $work->source,
                'template_id' => $work->template_id,
            ]);

        return Inertia::render('daily-work/index', [
            'dailyWork' => $dailyWork,
            'date' => $date->toDateString(),
            'can_update' => $employee !== null && $user->can('daily-work.update'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('daily-work.update');

        // Resolusi manual (bukan implicit binding): binding model jalan
        // sebelum IdentifyTenant → scope BelongsToTenant gagal saat Tenant
        // belum current (pola resolveWorkOrderFromRoute).
        $dailyWork = DailyWork::query()
            ->where('id', (string) $request->route('dailyWork'))
            ->firstOrFail();

        // FR-5.4: karyawan hanya mengubah item miliknya sendiri.
        abort_unless($dailyWork->employee_id === $request->user()->employee_id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,done'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $dailyWork->update([
            'status' => $validated['status'],
            'location' => $validated['location'] ?? null,
            'done_at' => $validated['status'] === 'done' ? now() : null,
        ]);

        return redirect()->route('daily-work.index', [
            'tenant' => tenant()?->code,
            'date' => $dailyWork->work_date->toDateString(),
        ]);
    }
}
