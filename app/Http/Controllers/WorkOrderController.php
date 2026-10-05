<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\WorkOrderCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
            'departments' => Department::query()->orderBy('nama_department')->get(['id', 'nama_department']),
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
            ->route('work-orders.show', ['tenant' => tenant()?->getTenantKey(), 'workOrder' => $workOrder])
            ->with('success', 'Work Order '.$workOrder->nomor_wo.' berhasil dibuat.');
    }

    public function show(WorkOrder $workOrder): Response
    {
        return Inertia::render('work-orders/show', [
            'workOrder' => $workOrder->load(['requester', 'targetDepartment']),
        ]);
    }
}
