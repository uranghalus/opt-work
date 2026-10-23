<?php

namespace App\Http\Controllers;

use App\ExtendRequestStatus;
use App\Http\Requests\WorkOrder\AssignWorkOrderRequest;
use App\Http\Requests\WorkOrder\ReviewWorkOrderRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExtendRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Services\ExtendRequestService;
use App\Services\WorkOrderService;
use App\WorkOrderCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkOrderController extends Controller
{
    /**
     * Indonesian day names, Monday first. Authored here rather than formatted
     * from a locale so the server-rendered markup and the client render agree
     * (the app timezone is UTC and the server locale is not Indonesian).
     *
     * @var list<string>
     */
    private const DAY_NAMES = [
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu',
        'Minggu',
    ];

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

    public function __construct(
        private WorkOrderService $workOrderService,
        private ExtendRequestService $extendRequestService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $reviewableDepartmentIds = Department::query()
            ->where('hod_user_id', $user->getKey())
            ->orWhere('manager_user_id', $user->getKey())
            ->pluck('id');

        $workOrders = WorkOrder::query()
            ->with(['requester', 'targetDepartment'])
            ->where(function ($query) use ($user, $reviewableDepartmentIds): void {
                $query->where('requester_user_id', $user->getKey());

                if ($reviewableDepartmentIds->isNotEmpty()) {
                    $query->orWhereIn('target_department_id', $reviewableDepartmentIds);
                }
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('work-orders/index', [
            'workOrders' => $workOrders,
        ]);
    }

    /**
     * Timeline — a week grid of scheduled Work Orders.
     *
     * The grid is a *day* grid, not a time-of-day grid. `work_orders.scheduled_date`
     * is a DATE column and the model carries no time-of-day column anywhere, so a
     * "08:30 / 09:00" gutter would have no data behind it. The date-level
     * equivalent of a current-time indicator is the "today" column, which the page
     * marks with a text label plus a rule — never colour alone (WCAG 1.4.1).
     *
     * Visibility mirrors index(): the requester plus the departments the user
     * heads or deputises for.
     */
    public function timeline(Request $request): Response
    {
        $user = $request->user();

        $weekStart = $this->resolveWeekStart($request->query('week'));
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $reviewableDepartmentIds = Department::query()
            ->where('hod_user_id', $user->getKey())
            ->orWhere('manager_user_id', $user->getKey())
            ->pluck('id');

        $workOrders = WorkOrder::query()
            ->with(['requester', 'targetDepartment'])
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where(function ($query) use ($user, $reviewableDepartmentIds): void {
                $query->where('requester_user_id', $user->getKey());

                if ($reviewableDepartmentIds->isNotEmpty()) {
                    $query->orWhereIn('target_department_id', $reviewableDepartmentIds);
                }
            })
            ->orderBy('scheduled_date')
            ->orderBy('nomor_wo')
            ->get();

        $byDate = $workOrders->groupBy(
            fn (WorkOrder $workOrder): string => $workOrder->scheduled_date->toDateString()
        );

        $days = collect(range(0, 6))
            ->map(function (int $offset) use ($weekStart, $byDate): array {
                $date = $weekStart->copy()->addDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'dayName' => self::DAY_NAMES[$date->dayOfWeekIso - 1],
                    'dayNumber' => (int) $date->format('j'),
                    'monthName' => self::MONTH_NAMES[$date->month - 1],
                    'isToday' => $date->isSameDay(Carbon::today()),
                    'workOrders' => ($byDate->get($date->toDateString()) ?? collect())
                        ->map(fn (WorkOrder $workOrder): array => $this->timelineEntry($workOrder))
                        ->values(),
                ];
            })
            ->all();

        return Inertia::render('work-orders/timeline', [
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekEnd->toDateString(),
            'weekLabel' => $weekStart->format('j M Y').' - '.$weekEnd->format('j M Y'),
            'today' => Carbon::today()->toDateString(),
            'isCurrentWeek' => $weekStart->isSameDay(Carbon::today()->startOfWeek(Carbon::MONDAY)),
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'totalScheduled' => $workOrders->count(),
            'days' => $days,
        ]);
    }

    /**
     * Flatten a Work Order into the shape a timeline event card renders.
     *
     * @return array<string, mixed>
     */
    private function timelineEntry(WorkOrder $workOrder): array
    {
        [$deadlineState, $deadlineLabel] = $this->deadlineState($workOrder);

        return [
            'id' => $workOrder->getKey(),
            'nomor_wo' => $workOrder->nomor_wo,
            'title' => $workOrder->title,
            'status' => $workOrder->status->value,
            'category' => $workOrder->category->value,
            'deadline_date' => $workOrder->deadline_date?->toDateString(),
            'deadline_state' => $deadlineState,
            'deadline_label' => $deadlineLabel,
            'requester_name' => $workOrder->requester?->name,
            'department_name' => $workOrder->targetDepartment?->nama_department,
        ];
    }

    /**
     * SLA proximity of a Work Order, mirroring the "SLA proximity -> token" table
     * in DESIGN_BRIEF: on track, due within a day, one to two days late, and
     * escalated from H+3.
     *
     * @return array{0: 'on_track'|'due_soon'|'overdue'|'escalated', 1: string}
     */
    private function deadlineState(WorkOrder $workOrder): array
    {
        if ($workOrder->deadline_date === null) {
            return ['on_track', 'On track'];
        }

        // DateTime::diff() reports the magnitude in ->days and the direction in
        // ->invert (1 when the second date is the earlier one).
        $diff = Carbon::today()
            ->startOfDay()
            ->diff($workOrder->deadline_date->startOfDay());

        if ($diff->invert === 1) {
            $daysLate = (int) $diff->days;

            return $daysLate >= 3 || $workOrder->is_escalated
                ? ['escalated', 'Eskalasi H+'.$daysLate]
                : ['overdue', 'Telat H+'.$daysLate];
        }

        $daysUntil = (int) $diff->days;

        return match (true) {
            $daysUntil === 0 => ['due_soon', 'Deadline hari ini'],
            $daysUntil === 1 => ['due_soon', 'Deadline besok'],
            default => ['on_track', 'On track'],
        };
    }

    /**
     * Resolve the Monday that opens the requested week.
     *
     * The week travels as a query parameter so a view is deep-linkable and the
     * browser back button works. A value that will not parse, or one absurdly far
     * from the current week, falls back to the current week rather than erroring.
     */
    private function resolveWeekStart(mixed $week): Carbon
    {
        $current = Carbon::today()->startOfWeek(Carbon::MONDAY);

        if (! is_string($week) || trim($week) === '') {
            return $current;
        }

        try {
            $parsed = Carbon::parse(trim($week))->startOfWeek(Carbon::MONDAY);
        } catch (\Throwable) {
            return $current;
        }

        if ($parsed->lt($current->copy()->subYear()) || $parsed->gt($current->copy()->addYear())) {
            return $current;
        }

        return $parsed;
    }

    public function create(): Response
    {
        return Inertia::render('work-orders/create', [
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('nama_department')
                ->get(['id', 'nama_department']),
            'categories' => WorkOrderCategory::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => ['required', Rule::enum(WorkOrderCategory::class)],
            'target_department_id' => [
                'required',
                Rule::exists('departments', 'id')->where('tenant_id', tenant()?->getTenantKey()),
            ],
            'requested_schedule_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
                Rule::prohibitedIf(
                    $request->enum('category', WorkOrderCategory::class)?->allowsScheduling() === false,
                ),
            ],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $targetDepartment = Department::query()->findOrFail($validated['target_department_id']);

        $attachments = collect($request->file('attachments', []))
            ->map(fn ($file) => Storage::disk('public')->putFile('work-orders/'.tenant()?->getTenantKey(), $file))
            ->all();

        $workOrder = $this->workOrderService->create(
            $request->user(),
            $targetDepartment,
            $validated,
            $attachments,
        );

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Work Order '.$workOrder->nomor_wo.' berhasil dibuat.');
    }

    public function review(ReviewWorkOrderRequest $request): RedirectResponse
    {
        Gate::authorize('work-order.review');

        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $this->workOrderService->review($request->user(), $workOrder, $request->validated());

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Keputusan review WO '.$workOrder->nomor_wo.' tersimpan.');
    }

    public function assign(AssignWorkOrderRequest $request): RedirectResponse
    {
        Gate::authorize('work-order.assign');

        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $this->workOrderService->assign($request->user(), $workOrder, $request->validated());

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Penugasan WO '.$workOrder->nomor_wo.' tersimpan.');
    }

    public function submit(Request $request): RedirectResponse
    {
        Gate::authorize('work-order.submit');

        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $validated = $request->validate([
            'result_notes' => ['required', 'string', 'max:2000'],
            'result_attachments' => ['nullable', 'array', 'max:3'],
            'result_attachments.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $attachments = collect($request->file('result_attachments', []))
            ->map(fn ($file) => Storage::disk('public')->putFile('work-orders/'.tenant()?->getTenantKey(), $file))
            ->all();

        $this->workOrderService->submit(
            $request->user(),
            $workOrder,
            $validated['result_notes'],
            $attachments,
        );

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Hasil WO '.$workOrder->nomor_wo.' diserahkan — menunggu verifikasi HOD.');
    }

    public function verify(Request $request): RedirectResponse
    {
        Gate::authorize('work-order.verify');

        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $this->workOrderService->approve($request->user(), $workOrder);

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'WO '.$workOrder->nomor_wo.' terverifikasi dan ditutup.');
    }

    public function reject(Request $request): RedirectResponse
    {
        Gate::authorize('work-order.verify');

        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $validated = $request->validate([
            'revision_notes' => ['required', 'string', 'max:2000'],
        ]);

        $this->workOrderService->reject(
            $request->user(),
            $workOrder,
            $validated['revision_notes'],
        );

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Hasil WO '.$workOrder->nomor_wo.' dikembalikan untuk revisi.');
    }

    public function show(Request $request): Response
    {
        $workOrderId = $request->route('workOrder');

        // The tenant global scope must apply here: the work order has to
        // belong to the tenant the URL (and tenant.access) resolved to —
        // withoutGlobalScope() would serve another branch's WO by id.
        $workOrderModel = WorkOrder::query()
            ->where('id', $workOrderId)
            ->firstOrFail();

        $workOrderModel->load(['requester', 'targetDepartment']);

        // The assign dialog lists karyawan pelaksana of the target department
        // only for users who may assign; assignee names hydrate for everyone
        // (read-only display of who is on the job).
        $canAssign = $request->user()?->can('work-order.assign') ?? false;

        $employees = $canAssign
            ? Employee::query()
                ->where('department_id', $workOrderModel->target_department_id)
                ->orderBy('nama_employee')
                ->get(['id', 'nama_employee'])
            : collect();

        $assignees = $workOrderModel->assigned_employees !== null
            ? Employee::query()
                ->whereIn('id', $workOrderModel->assigned_employees)
                ->orderBy('nama_employee')
                ->get(['id', 'nama_employee'])
            : collect();

        // FR-1.9: full audit trail of the WO, newest first — every recorded
        // WorkOrderEvent with its actor.
        $events = $workOrderModel->events()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WorkOrderEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'meta' => $event->meta,
                'user_name' => $event->user?->name,
                'created_at' => $event->created_at->toIso8601String(),
            ]);

        // FR-2.3: pengajuan extend deadline WO telat — panel keputusan dan
        // status pengajuan di halaman detail, terbaru dulu. Visibilitas
        // panel (boleh mengaju / boleh memutuskan) dihitung server-side
        // agar browser tidak menduplikasi aturan jendela extend.
        $pendingExtend = $workOrderModel->extendRequests()
            ->whereIn('status', [
                ExtendRequestStatus::PendingTeamLeader->value,
                ExtendRequestStatus::PendingHod->value,
            ])
            ->orderByDesc('created_at')
            ->first();

        $extendRequests = $workOrderModel->extendRequests()
            ->with('requester:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ExtendRequest $request): array => [
                'id' => $request->id,
                'days' => $request->days,
                'reason' => $request->reason,
                'status' => $request->status->value,
                'requester_name' => $request->requester?->name,
                'created_at' => $request->created_at->toIso8601String(),
            ]);

        $user = $request->user();

        $canRequestExtend = $user !== null && $pendingExtend === null
            ? $this->extendRequestService->canRequestExtend($user, $workOrderModel)
            : false;

        $canDecideExtend = $user !== null && $pendingExtend !== null
            ? $this->extendRequestService->canDecideExtend($user, $pendingExtend)
            : false;

        return Inertia::render('work-orders/show', [
            'workOrder' => $workOrderModel,
            'employees' => $employees,
            'assignees' => $assignees,
            'events' => $events,
            'extend_requests' => $extendRequests,
            'can_request_extend' => $canRequestExtend,
            'can_decide_extend' => $canDecideExtend,
        ]);
    }

    /**
     * Stream one attachment of a work order.
     *
     * Attachments live on the tenant-suffixed public disk
     * (`storage/tenant{id}/app/public/` — see the FilesystemTenancyBootstrapper),
     * which the `public/storage` symlink does not cover, so they cannot be served
     * from a plain `/storage/...` URL. Serving them from here also keeps them
     * behind the tenant middleware stack: a raw storage URL would let any
     * authenticated user read another branch's uploads by guessing the path.
     */
    public function attachment(Request $request, string $index): StreamedResponse
    {
        $workOrderId = $request->route('workOrder');

        // Same rule as show(): the tenant scope keeps another branch's WO
        // (and its attachments) invisible even when the id is known.
        $workOrderModel = WorkOrder::query()
            ->where('id', $workOrderId)
            ->firstOrFail();
        $path = match ($request->route('type')) {
            'result' => $workOrderModel->result_attachments[(int) $index] ?? null,
            default => $workOrderModel->attachments[(int) $index] ?? null,
        };

        abort_if($path === null || ! Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }
}
