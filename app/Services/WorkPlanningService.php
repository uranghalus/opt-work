<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkPlanning;
use App\Notifications\WorkScheduleExtendDecided;
use App\Notifications\WorkScheduleExtendRequested;
use App\WorkOrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FR-2.4 + FR-2.5: Work Planning + Extend Work Schedule — jadwal WO
 * Terjadwal HANYA berpindah lewat pengajuan extend dengan approval
 * DGM/GM (alur terpisah dari extend WO telat FR-2.3). Detail lain
 * (jam, jenis pekerjaan, durasi, budget) bebas diperbarui HOD.
 */
class WorkPlanningService
{
    /**
     * Dipanggil dari WorkOrderService::review() saat HOD memilih
     * 'schedule' — satu planning per WO (unique work_order_id), sehingga
     * re-review saat WO kembali ke Scheduled memakai updateOrCreate, bukan
     * create yang akan menabrak constraint sebagai QueryException 500.
     */
    public function createForWorkOrder(WorkOrder $workOrder): WorkPlanning
    {
        return WorkPlanning::query()->updateOrCreate(
            ['work_order_id' => $workOrder->getKey()],
            [
                'tgl_jadwal' => $workOrder->scheduled_date,
                'status_jadwal' => WorkPlanning::STATUS_PLANNED,
                'proposed_tgl_jadwal' => null,
            ],
        );
    }

    /**
     * HOD membatalkan keputusan jadwal (re-review memilih eksekusi langsung):
     * planning yang tersisa dihapus agar tidak ada jadwal yatim di belakang
     * WO yang tidak lagi terjadwal.
     */
    public function discardForWorkOrder(WorkOrder $workOrder): void
    {
        WorkPlanning::query()
            ->where('work_order_id', $workOrder->getKey())
            ->where('status_jadwal', '!=', WorkPlanning::STATUS_PENDING)
            ->delete();
    }

    /**
     * HOD mengajukan perpanjangan jadwal: tanggal baru HARUS setelah
     * jadwal berjalan; jadwal berjalan tidak berubah sampai DGM/GM
     * menyetujui.
     *
     * @param  array<string, mixed>  $data  validated: tgl_jadwal, extend_reason
     */
    public function requestExtend(User $actor, WorkPlanning $planning, array $data): WorkPlanning
    {
        return DB::transaction(function () use ($actor, $planning, $data): WorkPlanning {
            $planning = WorkPlanning::query()
                ->whereKey($planning->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $workOrder = $planning->workOrder;

            if ($workOrder->status !== WorkOrderStatus::Scheduled) {
                abort(409, 'Extend jadwal hanya untuk WO yang sedang terjadwal.');
            }

            if ($planning->isPending()) {
                abort(409, 'Masih ada pengajuan extend jadwal yang menunggu approval DGM/GM.');
            }

            $proposed = Carbon::parse($data['tgl_jadwal'])->startOfDay();

            if ($proposed->lte($planning->tgl_jadwal)) {
                throw ValidationException::withMessages([
                    'tgl_jadwal' => 'Tanggal baru harus setelah jadwal berjalan ('.$planning->tgl_jadwal->toDateString().').',
                ]);
            }

            $planning->update([
                'proposed_tgl_jadwal' => $proposed,
                'original_tgl_jadwal' => $planning->original_tgl_jadwal ?? $planning->tgl_jadwal,
                'extend_reason' => $data['extend_reason'],
                'extend_requested_by' => $actor->getKey(),
                'extend_requested_at' => now(),
                'status_jadwal' => WorkPlanning::STATUS_PENDING,
            ]);

            WorkOrderEvent::record(
                $workOrder,
                $actor,
                'schedule_extend_requested',
                $workOrder->status->value,
                $workOrder->status->value,
                [
                    'work_planning_id' => $planning->getKey(),
                    'tgl_jadwal' => $planning->tgl_jadwal->toDateString(),
                    'proposed_tgl_jadwal' => $proposed->toDateString(),
                    'reason' => $data['extend_reason'],
                ],
            );

            $this->managementApprovers($workOrder)
                ->each(fn (User $approver) => $approver->notify(new WorkScheduleExtendRequested($planning)));

            return $planning;
        });
    }

    /**
     * DGM/GM memutuskan pengajuan extend jadwal. Approve: jadwal WO
     * resmi bergeser; reject: usulan dibatalkan, audit tetap tercatat.
     *
     * @param  array<string, mixed>  $data  validated: notes (nullable)
     */
    public function decideExtend(User $approver, WorkPlanning $planning, bool $approve, ?string $notes = null): WorkPlanning
    {
        return DB::transaction(function () use ($approver, $planning, $approve, $notes): WorkPlanning {
            $planning = WorkPlanning::query()
                ->whereKey($planning->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $workOrder = $planning->workOrder;

            // Konsisten dengan requestExtend: perpindahan jadwal hanya untuk
            // WO yang masih terjadwal — WO in-execution/closed tidak boleh
            // digeser jadwalnya oleh approval yang datang terlambat.
            if ($workOrder->status !== WorkOrderStatus::Scheduled) {
                abort(409, 'Extend jadwal hanya berlaku untuk WO yang sedang terjadwal.');
            }

            if (! $planning->isPending()) {
                abort(409, 'Tidak ada pengajuan extend jadwal yang menunggu keputusan.');
            }

            $requestedBy = $planning->extend_requested_by;

            // Konsisten dengan extend deadline: pengaju tidak memutuskan
            // pengajuannya sendiri.
            if ($requestedBy !== null && $requestedBy === $approver->getKey()) {
                abort(403, 'Pengaju tidak dapat menyetujui atau menolak pengajuannya sendiri.');
            }

            if ($approve) {
                $planning->update([
                    'tgl_jadwal' => $planning->proposed_tgl_jadwal,
                    'status_jadwal' => WorkPlanning::STATUS_RESCHEDULED,
                    'extend_count' => $planning->extend_count + 1,
                    'extend_approved_by' => $approver->getKey(),
                    'extend_approved_at' => now(),
                    'extend_approval_notes' => $notes,
                ]);

                // Jadwal resmi WO mengikuti planning yang disetujui.
                $workOrder->update(['scheduled_date' => $planning->tgl_jadwal]);

                WorkOrderEvent::record(
                    $workOrder,
                    $approver,
                    'schedule_extended',
                    $workOrder->status->value,
                    $workOrder->status->value,
                    [
                        'work_planning_id' => $planning->getKey(),
                        'new_tgl_jadwal' => $planning->tgl_jadwal->toDateString(),
                        'notes' => $notes,
                    ],
                );
            } else {
                $planning->update([
                    'status_jadwal' => WorkPlanning::STATUS_PLANNED,
                    'proposed_tgl_jadwal' => null,
                    'extend_approval_notes' => $notes,
                ]);

                WorkOrderEvent::record(
                    $workOrder,
                    $approver,
                    'schedule_extend_rejected',
                    $workOrder->status->value,
                    $workOrder->status->value,
                    [
                        'work_planning_id' => $planning->getKey(),
                        'proposed_tgl_jadwal' => $planning->original_tgl_jadwal?->toDateString(),
                        'notes' => $notes,
                    ],
                );
            }

            if ($requestedBy !== null) {
                $planning->refresh();
                User::query()->whereKey($requestedBy)->first()
                    ?->notify(new WorkScheduleExtendDecided($planning, $approve));
            }

            return $planning;
        });
    }

    /**
     * FR-2.4: detail planning diperbarui HOD — `tgl_jadwal` sengaja tidak
     * ada di sini: perpindahan tanggal HANYA lewat extend ber-approval
     * DGM/GM, kalau tidak approval jadi tempelan.
     *
     * @param  array<string, mixed>  $data  validated detail fields
     */
    public function updateDetails(User $actor, WorkPlanning $planning, array $data): WorkPlanning
    {
        return DB::transaction(function () use ($planning, $data): WorkPlanning {
            $planning = WorkPlanning::query()
                ->whereKey($planning->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $workOrder = $planning->workOrder;

            if ($workOrder->status !== WorkOrderStatus::Scheduled) {
                abort(409, 'Detail jadwal hanya untuk WO yang sedang terjadwal.');
            }

            if ($planning->isPending()) {
                abort(409, 'Detail jadwal terkunci saat pengajuan extend menunggu approval DGM/GM.');
            }

            $planning->update($data);

            return $planning;
        });
    }

    /**
     * Approver Extend Work Schedule: user ber-role general_manager /
     * deputy_general_manager di tenant (permission work-planning.update
     * sudah di-gate di endpoint; posisi rantai ditegakkan di sini).
     *
     * @return Collection<int, User>
     */
    public function managementApprovers(WorkOrder $workOrder): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['general_manager', 'deputy_general_manager']))
            ->get();
    }
}
