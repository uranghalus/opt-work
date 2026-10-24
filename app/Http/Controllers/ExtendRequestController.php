<?php

namespace App\Http\Controllers;

use App\ExtendRequestStatus;
use App\Models\ExtendRequest;
use App\Models\WorkOrder;
use App\Models\WorkPlanning;
use App\Services\ExtendRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExtendRequestController extends Controller
{
    public function __construct(private ExtendRequestService $extendRequestService) {}

    /**
     * R21: inbox "Persetujuan Extend" untuk rantai approver — dua antrean
     * terpisah dari approval gerbang lintas-department (R20):
     * (a) extend deadline WO telat pada tahap yang bisa diputuskan user
     *     ini (TL → HOD, FR-2.3–2.5) — diputuskan lewat panel di halaman
     *     detail WO (server yang memaksa tahap dan rantainya);
     * (b) Extend Work Schedule menunggu DGM/GM (FR-2.4–2.5).
     */
    public function approvals(Request $request): Response
    {
        $user = $request->user();

        $deadlineExtends = ExtendRequest::query()
            ->whereIn('status', [
                ExtendRequestStatus::PendingTeamLeader->value,
                ExtendRequestStatus::PendingHod->value,
            ])
            // target_department_id wajib ikut — rantai approver lazy-load
            // department tujuan dari relasi WO.
            ->with(['workOrder:id,nomor_wo,title,target_department_id', 'requester:id,name'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->filter(
                fn (ExtendRequest $extendRequest): bool => $this->extendRequestService->canDecideExtend($user, $extendRequest),
            )
            ->values()
            ->map(fn (ExtendRequest $extendRequest): array => [
                'id' => $extendRequest->getKey(),
                'work_order_id' => $extendRequest->work_order_id,
                'nomor_wo' => $extendRequest->workOrder?->nomor_wo,
                'title' => $extendRequest->workOrder?->title,
                'days' => $extendRequest->days,
                'reason' => $extendRequest->reason,
                'stage' => $extendRequest->status->value,
                'requester_name' => $extendRequest->requester?->name,
                'created_at' => $extendRequest->created_at->toIso8601String(),
            ]);

        $canDecideSchedule = $user->isSuperAdmin()
            || $user->hasAnyRole(['general_manager', 'deputy_general_manager']);

        $scheduleExtends = collect();

        if ($canDecideSchedule) {
            $scheduleExtends = WorkPlanning::query()
                ->where('status_jadwal', WorkPlanning::STATUS_PENDING)
                ->with(['workOrder:id,nomor_wo,title', 'extendRequester:id,name'])
                ->orderByDesc('extend_requested_at')
                ->limit(50)
                ->get()
                // Pengaju tidak memutuskan pengajuannya sendiri — mirror
                // klausul can_decide_schedule di WorkOrderController::show.
                ->reject(
                    fn (WorkPlanning $planning): bool => $planning->extend_requested_by === $user->getKey(),
                )
                ->values()
                ->map(fn (WorkPlanning $planning): array => [
                    'work_order_id' => $planning->work_order_id,
                    'nomor_wo' => $planning->workOrder?->nomor_wo,
                    'title' => $planning->workOrder?->title,
                    'tgl_jadwal' => $planning->tgl_jadwal?->toDateString(),
                    'proposed_tgl_jadwal' => $planning->proposed_tgl_jadwal?->toDateString(),
                    'extend_reason' => $planning->extend_reason,
                    'requester_name' => $planning->extendRequester?->name,
                ]);
        }

        return Inertia::render('work-orders/extend-approvals', [
            'deadline_extends' => $deadlineExtends,
            'schedule_extends' => $scheduleExtends,
            'can_decide_schedule' => $canDecideSchedule,
        ]);
    }

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
