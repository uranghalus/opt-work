<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkOrder\AssignWorkOrderRequest;
use App\Http\Requests\WorkOrder\ReviewWorkOrderRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Services\WorkOrderService;
use App\WorkOrderCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkOrderController extends Controller
{

    public function __construct(private WorkOrderService $workOrderService) {}

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

        return Inertia::render('work-orders/show', [
            'workOrder' => $workOrderModel,
            'employees' => $employees,
            'assignees' => $assignees,
            'events' => $events,
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
