<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkPlanning;
use App\Services\WorkPlanningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * FR-2.4 + FR-2.5: Work Planning — update detail jadwal (tanpa tgl) oleh
 * HOD + Extend Work Schedule dengan approval DGM/GM.
 */
class WorkPlanningController extends Controller
{
    public function __construct(private WorkPlanningService $workPlanningService) {}

    /**
     * HOD mengajukan perpanjangan jadwal — menunggu approval DGM/GM.
     */
    public function extend(Request $request): RedirectResponse
    {
        Gate::authorize('work-planning.update');

        $planning = $this->resolvePlanning($request);

        $validated = $request->validate([
            'tgl_jadwal' => ['required', 'date', 'after:today'],
            'extend_reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->workPlanningService->requestExtend($request->user(), $planning, $validated);

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $planning->workOrder])
            ->with('success', 'Pengajuan extend jadwal terkirim — menunggu approval DGM/GM.');
    }

    /**
     * DGM/GM menyetujui: jadwal resmi bergeser.
     */
    public function approve(Request $request): RedirectResponse
    {
        Gate::authorize('work-planning.update');

        $planning = $this->resolvePlanning($request);

        $this->assertManagement($request->user());

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workPlanningService->decideExtend($request->user(), $planning, true, $validated['notes'] ?? null);

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $planning->workOrder])
            ->with('success', 'Extend jadwal disetujui — jadwal WO diperbarui.');
    }

    /**
     * DGM/GM menolak: usulan dibatalkan, audit tetap tercatat.
     */
    public function reject(Request $request): RedirectResponse
    {
        Gate::authorize('work-planning.update');

        $planning = $this->resolvePlanning($request);

        $this->assertManagement($request->user());

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workPlanningService->decideExtend($request->user(), $planning, false, $validated['notes'] ?? null);

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $planning->workOrder])
            ->with('success', 'Extend jadwal ditolak — jadwal WO tidak berubah.');
    }

    /**
     * HOD memperbarui detail jadwal — tgl_jadwal sengaja tidak bisa lewat
     * sini: perpindahan tanggal hanya lewat extend ber-approval DGM/GM.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('work-planning.update');

        $planning = $this->resolvePlanning($request);

        $validated = $request->validate([
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i', 'after:jam_mulai'],
            'jenis_pekerjaan' => ['nullable', 'string', 'max:100'],
            'lama_pekerjaan_hari' => ['nullable', 'integer', 'min:1'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workPlanningService->updateDetails($request->user(), $planning, $validated);

        return redirect()
            ->route('work-orders.show', ['tenant' => tenant()?->code, 'workOrder' => $planning->workOrder])
            ->with('success', 'Detail jadwal WO '.$planning->workOrder->nomor_wo.' tersimpan.');
    }

    /**
     * Resolusi manual (pola anti-implicit binding pada grup web).
     */
    private function resolvePlanning(Request $request): WorkPlanning
    {
        return WorkPlanning::query()
            ->where('id', $request->route('workPlanning'))
            ->firstOrFail();
    }

    /**
     * Posisi rantai Extend Work Schedule: hanya DGM/GM (super admin
     * bypass Gate dan tetap boleh).
     */
    private function assertManagement(User $user): void
    {
        if ($user->is_super_admin) {
            return;
        }

        if (! $user->hasAnyRole(['general_manager', 'deputy_general_manager'])) {
            abort(403, 'Hanya DGM/GM yang dapat memproses approval perpanjangan jadwal.');
        }
    }
}
