<?php

namespace App\Http\Controllers;

use App\ExtendRequestStatus;
use App\Http\Requests\WorkOrder\AssignWorkOrderRequest;
use App\Http\Requests\WorkOrder\CancelWorkOrderRequest;
use App\Http\Requests\WorkOrder\RejectWorkOrderRequest;
use App\Http\Requests\WorkOrder\ReviewWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExtendRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkTimeLog;
use App\Services\ExtendRequestService;
use App\Services\WorkOrderRequestService;
use App\Services\WorkOrderService;
use App\Services\WorkTimeLogService;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    public function __construct(
        private WorkOrderService $workOrderService,
        private ExtendRequestService $extendRequestService,
        private WorkOrderRequestService $requestService,
        private WorkTimeLogService $workTimeLogService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('work-order.read');

        $user = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $reviewableDepartmentIds = Department::query()
            ->where('hod_user_id', $user->getKey())
            ->orWhere('manager_user_id', $user->getKey())
            ->pluck('id');

        $workOrders = WorkOrder::query()
            ->with(['requester', 'targetDepartment'])
            ->visibleFor($user, $reviewableDepartmentIds)
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(fn ($term) => $term
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('nomor_wo', 'like', "%{$search}%")),
            )
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(function (WorkOrder $workOrder): array {
                [$deadlineState, $deadlineLabel] = $this->deadlineState($workOrder);

                return [
                    'id' => $workOrder->getKey(),
                    'nomor_wo' => $workOrder->nomor_wo,
                    'title' => $workOrder->title,
                    'description' => $workOrder->description,
                    'category' => $workOrder->category->value,
                    'status' => $workOrder->status->value,
                    'requested_schedule_date' => $workOrder->requested_schedule_date?->toDateString(),
                    'scheduled_date' => $workOrder->scheduled_date?->toDateString(),
                    'deadline_date' => $workOrder->deadline_date?->toDateString(),
                    'created_at' => $workOrder->created_at?->toIso8601String(),
                    'requester' => $workOrder->requester ? [
                        'id' => $workOrder->requester->getKey(),
                        'name' => $workOrder->requester->name,
                    ] : null,
                    'target_department' => $workOrder->targetDepartment ? [
                        'id' => $workOrder->targetDepartment->getKey(),
                        'nama_department' => $workOrder->targetDepartment->nama_department,
                    ] : null,
                    'deadline_state' => $deadlineState,
                    'deadline_label' => $deadlineLabel,
                ];
            });

        return Inertia::render('work-orders/index', [
            'workOrders' => $workOrders,
        ]);
    }

    /**
     * Timeline — a scheduler calendar of scheduled Work Orders (FullCalendar
     * v7 dayGrid, redesign 2026-10-24 — see
     * docs/audit/workorder-timeline-design-decisions.md).
     *
     * The grid is a *day* grid, not a time-of-day grid. `work_orders.scheduled_date`
     * is a DATE column and the model carries no time-of-day column anywhere, so a
     * "08:30 / 09:00" gutter would have no data behind it.
     *
     * The view range travels as `start`/`end` query parameters (end exclusive,
     * the FullCalendar convention) so every view — week or month — is
     * deep-linkable and the browser back button works. The legacy `week`
     * parameter (Monday date) is still honored for old links.
     *
     * "Today" stays server-side (app timezone = Asia/Makassar, D2) and is sent
     * to the calendar as its `now` value, so the today highlight agrees with
     * WITA even when the browser timezone differs.
     *
     * Visibility mirrors index() via WorkOrder::scopeVisibleFor — the requester,
     * the departments the user heads or deputises for, and the assigned
     * pelaksana (D1).
     */
    public function timeline(Request $request): Response
    {
        $user = $request->user();

        [$start, $end, $view] = $this->resolveTimelineRange($request);

        $reviewableDepartmentIds = Department::query()
            ->where('hod_user_id', $user->getKey())
            ->orWhere('manager_user_id', $user->getKey())
            ->pluck('id');

        $workOrders = WorkOrder::query()
            ->with(['requester', 'targetDepartment'])
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [
                $start->toDateString(),
                $end->copy()->subDay()->toDateString(),
            ])
            ->visibleFor($user, $reviewableDepartmentIds)
            ->orderBy('scheduled_date')
            ->orderBy('nomor_wo')
            ->get();

        $events = $workOrders
            ->map(function (WorkOrder $workOrder): array {
                [$deadlineState, $deadlineLabel] = $this->deadlineState($workOrder);

                return [
                    'id' => $workOrder->getKey(),
                    // Fallback text for non-JSX renderings and screen readers;
                    // the visual card is custom JSX (eventContent).
                    'title' => $workOrder->nomor_wo.' — '.$workOrder->title,
                    'start' => $workOrder->scheduled_date->toDateString(),
                    // FullCalendar end is exclusive.
                    'end' => $workOrder->scheduled_date->copy()->addDay()->toDateString(),
                    'url' => route('work-orders.show', [
                        'tenant' => tenant()?->code,
                        'workOrder' => $workOrder->getKey(),
                    ]),
                    'extendedProps' => [
                        'nomor_wo' => $workOrder->nomor_wo,
                        'title' => $workOrder->title,
                        'status' => $workOrder->status->value,
                        'category' => $workOrder->category->value,
                        'deadline_state' => $deadlineState,
                        'deadline_label' => $deadlineLabel,
                        'department_name' => $workOrder->targetDepartment?->nama_department,
                    ],
                ];
            })
            ->values()
            ->all();

        return Inertia::render('work-orders/timeline', [
            'events' => $events,
            'totalScheduled' => count($events),
            'today' => Carbon::today()->toDateString(),
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'view' => $view,
            ],
        ]);
    }

    /**
     * Resolve the visible date range from the query string.
     *
     * `start` is inclusive and `end` exclusive, both `Y-m-d` (the ISO strings
     * FullCalendar emits are sliced client-side). A missing/invalid value, or
     * a range absurdly far from today, falls back to the current week rather
     * than erroring; the range is capped at ~9 weeks so a hand-crafted URL can
     * never force an unbounded query.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string} start, end (exclusive), view type
     */
    private function resolveTimelineRange(Request $request): array
    {
        $today = Carbon::today();
        $defaultStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $allowedViews = [
            'day',
            'week',
            'month',
            // Legacy FullCalendar buckets still echo correctly for old links.
            'dayGridWeek',
            'dayGridMonth',
        ];

        $view = in_array($request->query('view'), $allowedViews, true)
            ? (string) $request->query('view')
            : 'dayGridWeek';

        // Legacy deep links: ?week=<monday> stays valid when start is absent.
        $rawStart = $request->query('start');
        if (! is_string($rawStart) || trim($rawStart) === '') {
            $rawStart = $request->query('week');

            if (is_string($rawStart) && trim($rawStart) !== '') {
                $view = 'dayGridWeek';
            }
        }

        try {
            $start = is_string($rawStart) && trim($rawStart) !== ''
                ? Carbon::parse(trim($rawStart))->startOfDay()
                : $defaultStart->copy();
        } catch (\Throwable) {
            $start = $defaultStart->copy();
        }

        if ($start->lt($today->copy()->subYear()) || $start->gt($today->copy()->addYear())) {
            $start = $defaultStart->copy();
        }

        $rawEnd = $request->query('end');

        try {
            $end = is_string($rawEnd) && trim($rawEnd) !== ''
                ? Carbon::parse(trim($rawEnd))->startOfDay()
                : $start->copy()->addWeek();
        } catch (\Throwable) {
            $end = $start->copy()->addWeek();
        }

        // Cap the range; keep it at least one day so a degenerate URL still
        // renders a (single-day) grid instead of erroring.
        if ($end->lte($start)) {
            $end = $start->copy()->addDay();
        }

        if ($start->diffInDays($end) > 62) {
            $end = $start->copy()->addWeeks(9);
        }

        return [$start, $end, $view];
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
            return ['on_track', 'Sesuai jadwal'];
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
            default => ['on_track', 'Sesuai jadwal'],
        };
    }

    public function create(): Response
    {
        Gate::authorize('work-order.create');

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
        Gate::authorize('work-order.create');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::enum(WorkOrderCategory::class)],
            'target_department_id' => [
                'required',
                // FR-4.1: hanya department AKTIF — department yang dinonaktifkan
                // sync tidak boleh jadi tujuan WO baru (dropdown create juga
                // hanya menampilkan yang aktif).
                Rule::exists('departments', 'id')
                    ->where('tenant_id', tenant()?->getTenantKey())
                    ->where('is_active', true),
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
            ->map(fn ($file) => Storage::disk('s3')->putFile('work-orders/'.tenant()?->getTenantKey(), $file))
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
            ->map(fn ($file) => Storage::disk('s3')->putFile('work-orders/'.tenant()?->getTenantKey(), $file))
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

    /**
     * R2/R3: approver department pengaju menyetujui permintaan lintas-
     * department — WO rilis ke department tujuan dan alur lama berjalan.
     */
    public function approveRequest(Request $request): RedirectResponse
    {
        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $this->requestService->approve($request->user(), $workOrder);

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Permintaan WO '.$workOrder->nomor_wo.' disetujui — diteruskan ke department tujuan.');
    }

    /**
     * R4: tolak permintaan (alasan wajib) — WO berhenti sebelum dept tujuan.
     */
    public function rejectRequest(RejectWorkOrderRequest $request): RedirectResponse
    {
        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $this->requestService->reject($request->user(), $workOrder, $request->validated('reason'));

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Permintaan WO '.$workOrder->nomor_wo.' ditolak.');
    }

    /**
     * R12: revisi WO yang ditolak — hanya requester, saat RejectedByDept.
     */
    public function updateRequest(UpdateWorkOrderRequest $request): RedirectResponse
    {
        Gate::authorize('work-order.update');

        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $this->requestService->updateRejected($request->user(), $workOrder, $request->validated());

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Revisi WO '.$workOrder->nomor_wo.' tersimpan — silakan ajukan ulang.');
    }

    /**
     * R12: ajukan ulang setelah revisi — kembali ke gerbang approval.
     */
    public function resubmitRequest(Request $request): RedirectResponse
    {
        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $this->requestService->resubmit($request->user(), $workOrder);

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'WO '.$workOrder->nomor_wo.' diajukan ulang ke department pengaju.');
    }

    /**
     * R12: cancel — (a) requester/approver dept pengaju saat belum rilis,
     * (b) reviewer dept tujuan membatalkan duplikat saat review.
     */
    public function cancelRequest(CancelWorkOrderRequest $request): RedirectResponse
    {
        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $this->requestService->cancel(
            $request->user(),
            $workOrder,
            $request->validated('reason'),
            $request->validated('duplicate_of'),
        );

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.index', ['tenant' => tenant()?->code])
            ->with('success', 'WO '.$workOrder->nomor_wo.' dibatalkan.');
    }

    /**
     * R20: Approval inbox — permintaan WO lintas-department yang MENUNGGU
     * keputusan department PENGAJU, terpisah dari antrian WO masuk
     * department tujuan. Anggota rantai approver (ADR-0011) melihat
     * permintaan dari department-nya sendiri; keputusan tetap dipaksa di
     * WorkOrderRequestService (bukan cuma UI).
     */
    public function approvals(Request $request): Response
    {
        $user = $request->user();

        $approverDepartmentIds = Department::query()
            ->where('hod_user_id', $user->getKey())
            ->orWhere('manager_user_id', $user->getKey())
            ->pluck('id');

        if ($user->employee_id !== null
            && $user->hasAnyRole(['hod', 'staf_admin_dept'])) {
            $ownDepartmentId = $user->employee?->department_id;

            if ($ownDepartmentId !== null) {
                $approverDepartmentIds->push($ownDepartmentId);
            }
        }

        $workOrders = WorkOrder::query()
            ->with(['requester:id,name', 'targetDepartment:id,nama_department'])
            ->where('status', WorkOrderStatus::PendingDeptApproval->value)
            ->whereHas(
                'requester.employee',
                fn ($query) => $query->whereIn(
                    'department_id',
                    $approverDepartmentIds->unique()->values(),
                ),
            )
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $requests = $workOrders->through(fn (WorkOrder $workOrder): array => [
            'id' => $workOrder->getKey(),
            'nomor_wo' => $workOrder->nomor_wo,
            'title' => $workOrder->title,
            'description' => $workOrder->description,
            'category' => $workOrder->category->value,
            'requester_name' => $workOrder->requester?->name,
            'target_department' => $workOrder->targetDepartment?->nama_department,
            'created_at' => $workOrder->created_at->toIso8601String(),
            // OQ-4b: penanda kandidat duplikat — keputusan tetap manusia.
            'duplicate_numbers' => $this->requestService
                ->duplicateCandidates($workOrder, 3)
                ->pluck('nomor_wo')
                ->all(),
        ]);

        return Inertia::render('work-orders/approvals', [
            'requests' => $requests,
        ]);
    }

    /**
     * R15: "Tugasan Saya" — WO eksekusi aktif yang ditugaskan ke karyawan
     * ini (assigned/in_progress/revision), lengkap dengan penugasi, lokasi,
     * deadline proximity, dan timer durasi server-computed (R17). Akses
     * terbuka untuk akun ter-authentikasi; daftar hanya memuat WO yang
     * benar-benar menugaskannya (whereJsonContains assigned_employees).
     */
    public function my(Request $request): Response
    {
        $user = $request->user();
        $employeeId = (string) ($user->employee_id ?? '');

        $workOrders = WorkOrder::query()
            ->with(['requester:id,name', 'targetDepartment:id,nama_department'])
            ->whereIn('status', [
                WorkOrderStatus::Assigned->value,
                WorkOrderStatus::InProgress->value,
                WorkOrderStatus::Revision->value,
            ]);

        if ($employeeId === '') {
            $workOrders->whereRaw('1 = 0');
        } else {
            $workOrders->whereJsonContains('assigned_employees', $employeeId);
        }

        $workOrders = $workOrders
            ->orderBy('deadline_date')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $workOrderIds = $workOrders->getCollection()
            ->map(fn (WorkOrder $workOrder) => $workOrder->getKey())
            ->all();

        // Penugasi = pengguna pada event `assigned` terakhir per WO
        // (satu query untuk seluruh halaman, bukan N+1).
        $assigners = WorkOrderEvent::query()
            ->whereIn('work_order_id', $workOrderIds)
            ->where('action', 'assigned')
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('work_order_id')
            ->map(fn ($events) => $events->first()?->user?->name);

        // Timer milik karyawan ini untuk tiap WO (satu query).
        $timeLogs = $employeeId === ''
            ? collect()
            : WorkTimeLog::query()
                ->whereIn('work_order_id', $workOrderIds)
                ->where('employee_id', $employeeId)
                ->get()
                ->keyBy('work_order_id');

        $assignments = $workOrders->through(function (WorkOrder $workOrder) use ($assigners, $timeLogs): array {
            $timeLog = $timeLogs->get($workOrder->getKey());

            return [
                'id' => $workOrder->getKey(),
                'nomor_wo' => $workOrder->nomor_wo,
                'title' => $workOrder->title,
                'description' => $workOrder->description,
                'category' => $workOrder->category->value,
                'status' => $workOrder->status->value,
                'location' => $workOrder->location,
                'assignment_notes' => $workOrder->assignment_notes,
                'deadline_date' => $workOrder->deadline_date?->toDateString(),
                'deadline_state' => $this->deadlineState($workOrder)[0],
                'deadline_label' => $this->deadlineState($workOrder)[1],
                'target_department' => $workOrder->targetDepartment?->nama_department,
                'assigner_name' => $assigners->get($workOrder->getKey()),
                'attachments_count' => count($workOrder->attachments ?? []),
                // R17: dihitung server dari timestamp tersimpan — dasar
                // yang benar setelah refresh atau di perangkat lain.
                'time_log' => $timeLog === null ? null : [
                    'state' => $timeLog->timerState(),
                    'elapsed_seconds' => $timeLog->elapsedSeconds(),
                ],
            ];
        });

        return Inertia::render('work-orders/my', [
            'assignments' => $assignments,
        ]);
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

        Gate::authorize('work-order.view', $workOrderModel);

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

        // ADR-0011: gerbang approval — mirror server-side untuk panel
        // frontend (keputusan tetap dipaksa ulang di service).
        $canDecideRequest = $user !== null
            && $workOrderModel->status === WorkOrderStatus::PendingDeptApproval
            && $this->requestService->isApprover($user, $workOrderModel);

        $canCancelRequest = $user !== null
            && in_array($workOrderModel->status, [
                WorkOrderStatus::PendingDeptApproval,
                WorkOrderStatus::RejectedByDept,
            ], true)
            && ($workOrderModel->requester_user_id === $user->getKey()
                || $this->requestService->isApprover($user, $workOrderModel));

        $canEditRequest = $user !== null
            && $workOrderModel->status === WorkOrderStatus::RejectedByDept
            && $workOrderModel->requester_user_id === $user->getKey();

        $canCancelDuplicate = $user !== null
            && $workOrderModel->status === WorkOrderStatus::WaitingHod
            && $this->requestService->isTargetReviewer($user, $workOrderModel);

        $duplicateCandidates = ($canDecideRequest || $canCancelDuplicate)
            ? $this->requestService
                ->duplicateCandidates($workOrderModel)
                ->map(fn (WorkOrder $candidate): array => [
                    'id' => $candidate->getKey(),
                    'nomor_wo' => $candidate->nomor_wo,
                ])
                ->all()
            : [];

        // R12: dialog revisi memilih ulang department tujuan (aktif saja).
        $departments = $canEditRequest
            ? Department::query()
                ->where('is_active', true)
                ->orderBy('nama_department')
                ->get(['id', 'nama_department'])
            : collect();

        // ADR-0011 R15–R17: timer durasi untuk pelaksana — mirror
        // server-side; aksi tetap dipaksa ulang di WorkTimeLogService.
        $myEmployeeId = (string) ($user?->employee_id ?? '');
        $isAssignee = $myEmployeeId !== ''
            && in_array($myEmployeeId, $workOrderModel->assigned_employees ?? [], true);

        $myTimeLog = $isAssignee
            ? $this->workTimeLogService->logFor($workOrderModel, $myEmployeeId)
            : null;

        $executionStage = in_array($workOrderModel->status, [
            WorkOrderStatus::Assigned,
            WorkOrderStatus::InProgress,
        ], true);

        $canAcceptTime = $isAssignee && $executionStage
            && ($myTimeLog === null || $myTimeLog->accepted_at === null);
        $canStartTime = $isAssignee && $executionStage
            && $myTimeLog?->accepted_at !== null
            && $myTimeLog?->started_at === null;
        $canPauseTime = $isAssignee && $executionStage
            && $myTimeLog?->started_at !== null
            && $myTimeLog?->finished_at === null
            && $myTimeLog?->paused_at === null;
        $canResumeTime = $isAssignee && $executionStage
            && $myTimeLog?->paused_at !== null;

        $myTimeLogPayload = $myTimeLog === null ? null : [
            'state' => $myTimeLog->timerState(),
            'elapsed_seconds' => $myTimeLog->elapsedSeconds(),
            'accepted_at' => $myTimeLog->accepted_at?->toIso8601String(),
            'started_at' => $myTimeLog->started_at?->toIso8601String(),
            'finished_at' => $myTimeLog->finished_at?->toIso8601String(),
        ];

        // FR-2.4: Work Planning untuk WO Terjadwal — detail jadwal + status
        // extend schedule; visibilitas panel dihitung server-side.
        $planning = $workOrderModel->planning()->with('extendRequester:id,name')->first();

        $canUpdatePlanning = $user !== null
            && $planning !== null
            && $workOrderModel->status === WorkOrderStatus::Scheduled
            && ! $planning->isPending()
            && $user->can('work-planning.update');

        $canDecideSchedule = $user !== null
            && $planning !== null
            && $planning->isPending()
            && $planning->extend_requested_by !== $user?->getKey()
            && $user->can('work-planning.update')
            && ($user->is_super_admin || $user->hasAnyRole(['general_manager', 'deputy_general_manager']));

        [$deadlineState, $deadlineLabel] = $this->deadlineState($workOrderModel);
        $workOrderModel->setAttribute('deadline_state', $deadlineState);
        $workOrderModel->setAttribute('deadline_label', $deadlineLabel);

        return Inertia::render('work-orders/show', [
            'workOrder' => $workOrderModel,
            'employees' => $employees,
            'assignees' => $assignees,
            'events' => $events,
            'extend_requests' => $extendRequests,
            'can_request_extend' => $canRequestExtend,
            'can_decide_extend' => $canDecideExtend,
            'planning' => $planning === null ? null : [
                'id' => $planning->id,
                'tgl_jadwal' => $planning->tgl_jadwal?->toDateString(),
                'original_tgl_jadwal' => $planning->original_tgl_jadwal?->toDateString(),
                'proposed_tgl_jadwal' => $planning->proposed_tgl_jadwal?->toDateString(),
                'jam_mulai' => $planning->jam_mulai,
                'jam_selesai' => $planning->jam_selesai,
                'jenis_pekerjaan' => $planning->jenis_pekerjaan,
                'lama_pekerjaan_hari' => $planning->lama_pekerjaan_hari,
                'budget' => $planning->budget === null ? null : (float) $planning->budget,
                'status_jadwal' => $planning->status_jadwal,
                'catatan' => $planning->catatan,
                'extend_count' => $planning->extend_count,
                'extend_reason' => $planning->extend_reason,
                'requester_name' => $planning->extendRequester?->name,
                'pending' => $planning->isPending(),
            ],
            'can_update_planning' => $canUpdatePlanning,
            'can_decide_schedule' => $canDecideSchedule,
            'can_decide_request' => $canDecideRequest,
            'can_cancel_request' => $canCancelRequest,
            'can_edit_request' => $canEditRequest,
            'can_resubmit_request' => $canEditRequest,
            'can_cancel_duplicate' => $canCancelDuplicate,
            'is_requester' => $user?->getKey() === $workOrderModel->requester_user_id,
            'duplicate_candidates' => $duplicateCandidates,
            'departments' => $departments,
            'is_assignee' => $isAssignee,
            'my_time_log' => $myTimeLogPayload,
            'can_accept_time' => $canAcceptTime,
            'can_start_time' => $canStartTime,
            'can_pause_time' => $canPauseTime,
            'can_resume_time' => $canResumeTime,
        ]);
    }

    /**
     * Redirect browser to the S3 public URL for one attachment of a work order.
     *
     * Attachments now live on S3 (visibility: public). We still route through
     * this controller so the tenant middleware stack can verify ownership before
     * handing the browser a CDN URL — no unauthenticated cross-branch access.
     *
     * @return RedirectResponse
     */
    public function attachment(Request $request, string $index)
    {
        $workOrderId = $request->route('workOrder');

        $workOrderModel = WorkOrder::query()
            ->where('id', $workOrderId)
            ->firstOrFail();

        Gate::authorize('work-order.view', $workOrderModel);

        $path = match ($request->route('type')) {
            'result' => $workOrderModel->result_attachments[(int) $index] ?? null,
            default => $workOrderModel->attachments[(int) $index] ?? null,
        };

        abort_if($path === null, 404);

        abort_if(! Storage::disk('s3')->exists($path), 404);

        // Redirect to the public CDN URL — no streaming through Laravel.
        return redirect(Storage::disk('s3')->url($path));
    }
}
