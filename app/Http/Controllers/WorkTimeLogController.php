<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use App\Services\WorkTimeLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Aksi timer durasi kerja pelaksana (R15–R18): terima → mulai →
 * jeda/lanjut. Selesai (finish) dipicu otomatis oleh submit hasil.
 * Authorization (hanya pelaksana tercantum) dipaksa di
 * WorkTimeLogService::assertAssignee — server-side, bukan UI.
 */
class WorkTimeLogController extends Controller
{
    public function __construct(
        private WorkTimeLogService $workTimeLogService,
    ) {}

    public function accept(Request $request): RedirectResponse
    {
        $workOrder = $this->resolveWorkOrder($request);

        $this->workTimeLogService->accept($request->user(), $workOrder);

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'WO '.$workOrder->nomor_wo.' diterima — silakan mulai bekerja.');
    }

    public function start(Request $request): RedirectResponse
    {
        $workOrder = $this->resolveWorkOrder($request);

        $this->workTimeLogService->start($request->user(), $workOrder);

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Timer WO '.$workOrder->nomor_wo.' mulai berjalan.');
    }

    public function pause(Request $request): RedirectResponse
    {
        $workOrder = $this->resolveWorkOrder($request);

        $this->workTimeLogService->pause($request->user(), $workOrder);

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Timer WO '.$workOrder->nomor_wo.' dipause.');
    }

    public function resume(Request $request): RedirectResponse
    {
        $workOrder = $this->resolveWorkOrder($request);

        $this->workTimeLogService->resume($request->user(), $workOrder);

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Timer WO '.$workOrder->nomor_wo.' dilanjutkan.');
    }

    /**
     * Resolusi manual mengikuti pola WorkOrderController::show — binding
     * implisit jalan sebelum IdentifyTenant, jadi scope tenant harus
     * diterapkan lewat query eksplisit di sini (404 lintas cabang).
     */
    private function resolveWorkOrder(Request $request): WorkOrder
    {
        return WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();
    }
}
