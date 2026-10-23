<?php

namespace App\Http\Controllers;

use App\Models\ExtendRequest;
use App\Models\WorkOrder;
use App\Services\ExtendRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExtendRequestController extends Controller
{
    public function __construct(private ExtendRequestService $extendRequestService) {}

    /**
     * FR-2.3: simpan pengajuan extend deadline WO telat.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('work-order.extend');

        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->extendRequestService->request(
            $request->user(),
            $workOrder,
            (int) $validated['days'],
            $validated['reason'],
        );

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Pengajuan extend WO '.$workOrder->nomor_wo.' terkirim ke approver.');
    }

    /**
     * FR-2.3: setujui pengajuan pada tahap berjalan (TL meneruskan ke HOD,
     * HOD memfinalkan dan menggeser deadline).
     */
    public function approve(Request $request): RedirectResponse
    {
        Gate::authorize('work-order.extend-approve');

        [$workOrder, $extendRequest] = $this->resolvePair($request);

        $this->extendRequestService->approve($request->user(), $extendRequest);

        $workOrder->refresh();

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Pengajuan extend WO '.$workOrder->nomor_wo.' diproses.');
    }

    /**
     * FR-2.3: tolak pengajuan pada tahap berjalan — alasan wajib.
     */
    public function reject(Request $request): RedirectResponse
    {
        Gate::authorize('work-order.extend-approve');

        [$workOrder, $extendRequest] = $this->resolvePair($request);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->extendRequestService->reject($request->user(), $extendRequest, $validated['reason']);

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $workOrder])
            ->with('success', 'Pengajuan extend WO '.$workOrder->nomor_wo.' ditolak.');
    }

    /**
     * Resolusi manual pasangan WO + extend request (pola anti-implicit
     * binding pada grup web — lihat WorkOrderController).
     *
     * @return array{0: WorkOrder, 1: ExtendRequest}
     */
    private function resolvePair(Request $request): array
    {
        $workOrder = WorkOrder::query()
            ->where('id', $request->route('workOrder'))
            ->firstOrFail();

        $extendRequest = ExtendRequest::query()
            ->where('id', $request->route('extendRequest'))
            ->where('work_order_id', $workOrder->getKey())
            ->firstOrFail();

        return [$workOrder, $extendRequest];
    }
}
