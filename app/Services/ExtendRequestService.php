<?php

namespace App\Services;

use App\ExtendRequestStatus;
use App\Models\Employee;
use App\Models\ExtendRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Notifications\ExtendRequestApproved;
use App\Notifications\ExtendRequestRejected;
use App\Notifications\ExtendRequestSubmitted;
use App\WorkOrderStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FR-2.3–2.5: pengajuan extend deadline WO telat. Batas: maks 3 hari per
 * request, maks 3 approved per WO, satu pengajuan pending per WO (blokir
 * duplikat — perbaikan atas gap opti-work2). Approval berjenjang Team
 * Leader (jika department punya TL) → HOD; tanpa TL langsung HOD.
 */
class ExtendRequestService
{
    private const MAX_APPROVED_PER_WORK_ORDER = 3;

    private const MAX_DAYS_PER_REQUEST = 3;

    /**
     * Jendela "mendekati telat": extend sudah boleh diajukan saat deadline
     * tinggal ≤ N hari kerja (keputusan user 2026-10-22 di branch
     * feature/extend-deadline — lebih luas dari teks PRD "WO telat").
     */
    private const NEAR_DEADLINE_BUSINESS_DAYS = 2;

    /**
     * Status WO yang masih boleh diajukan extend-nya — sama dengan daftar
     * status aktif pada rantai eskalasi.
     *
     * @return array<int, WorkOrderStatus>
     */
    public static function extendableStatuses(): array
    {
        return [
            WorkOrderStatus::Assigned,
            WorkOrderStatus::InProgress,
            WorkOrderStatus::PendingVerify,
            WorkOrderStatus::Revision,
        ];
    }

    /**
     * @param  int  $days  1..3 hari kerja tambahan (FR-2.3: maks 3 per request)
     */
    public function request(User $requester, WorkOrder $workOrder, int $days, string $reason): ExtendRequest
    {
        return DB::transaction(function () use ($requester, $workOrder, $days, $reason): ExtendRequest {
            // Kunci baris WO (aggregate root) sebagai statement pertama —
            // serialisasi concurrent request per WO: cek pending-duplikat,
            // ceiling extend_count, dan insert tidak bisa interleave.
            $workOrder = WorkOrder::query()
                ->whereKey($workOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = $workOrder->status;

            if (! in_array($from, self::extendableStatuses(), true)) {
                abort(409, 'Extend hanya untuk WO dalam tahap eksekusi.');
            }

            // Telat ATAU mendekati telat (≤ NEAR_DEADLINE_BUSINESS_DAYS hari
            // kerja — keputusan user 2026-10-22); konsisten dengan ambang
            // eskalasi (`deadline_date < now()`), deadline hari ini ikut
            // dihitung telat.
            if ($workOrder->deadline_date === null || ! $this->withinExtendWindow($workOrder)) {
                abort(409, 'Extend hanya untuk WO yang sudah terlambat atau mendekati deadline.');
            }

            if ($workOrder->extend_count >= self::MAX_APPROVED_PER_WORK_ORDER) {
                abort(409, 'Batas maksimal '.self::MAX_APPROVED_PER_WORK_ORDER.' extend yang disetujui sudah tercapai.');
            }

            if ($days < 1 || $days > self::MAX_DAYS_PER_REQUEST) {
                throw ValidationException::withMessages([
                    'days' => 'Pengajuan extend maksimal '.self::MAX_DAYS_PER_REQUEST.' hari per request.',
                ]);
            }

            $pendingExists = ExtendRequest::query()
                ->where('work_order_id', $workOrder->getKey())
                ->whereIn('status', [
                    ExtendRequestStatus::PendingTeamLeader->value,
                    ExtendRequestStatus::PendingHod->value,
                ])
                ->exists();

            if ($pendingExists) {
                abort(409, 'Masih ada pengajuan extend yang menunggu persetujuan untuk WO ini.');
            }

            // TL yang mengaju sendiri tidak dihitung — rantai tidak boleh
            // buntu di tahap yang hanya bisa diputuskan pengajunya sendiri.
            $stage = $this->departmentTeamLeaders($workOrder)
                ->contains(fn (User $leader) => $leader->getKey() !== $requester->getKey())
                ? ExtendRequestStatus::PendingTeamLeader
                : ExtendRequestStatus::PendingHod;

            if (! $this->stageApprovers($workOrder, $stage)
                ->contains(fn (User $approver) => $approver->getKey() !== $requester->getKey())) {
                abort(409, 'Tidak ada approver yang tersedia untuk pengajuan extend ini.');
            }

            $extendRequest = ExtendRequest::create([
                'work_order_id' => $workOrder->getKey(),
                'requested_by_user_id' => $requester->getKey(),
                'days' => $days,
                'reason' => $reason,
                'status' => $stage,
            ]);

            WorkOrderEvent::record(
                $workOrder,
                $requester,
                'extend_requested',
                $from->value,
                $from->value,
                [
                    'extend_request_id' => $extendRequest->getKey(),
                    'days' => $days,
                    'reason' => $reason,
                    'stage' => $stage->value,
                ],
            );

            $this->stageApprovers($workOrder, $stage)
                ->each(fn (User $approver) => $approver->notify(new ExtendRequestSubmitted($extendRequest)));

            return $extendRequest;
        });
    }

    /**
     * Menyetujui pengajuan pada tahap berjalan. Tahap Team Leader hanya
     * mencatat persetujuan dan meneruskan ke rantai HOD; tahap HOD final —
     * deadline WO bergeser sebanyak N hari kerja dan batas 3 approved/WO
     * diperbarui.
     */
    public function approve(User $actor, ExtendRequest $extendRequest): ExtendRequest
    {
        return DB::transaction(function () use ($actor, $extendRequest): ExtendRequest {
            // Kunci WO dulu (urutan kunci konsisten dengan request()), lalu
            // baca ulang baris pengajuan di bawah lock — dua approve/reject
            // konkuren tidak bisa sama-sama lolos cek pending.
            $workOrder = WorkOrder::query()
                ->whereKey($extendRequest->work_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $extendRequest = ExtendRequest::query()
                ->whereKey($extendRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $extendRequest->status->isPending()) {
                abort(409, 'Pengajuan extend sudah diputuskan.');
            }

            // TL mengundurkan diri setelah pengajuan dibuat → tahap TL
            // diambil alih rantai HOD (semangat: "jika tidak ada TL,
            // langsung HOD").
            $stage = $extendRequest->status;

            if ($stage === ExtendRequestStatus::PendingTeamLeader
                && $this->departmentTeamLeaders($workOrder)->isEmpty()) {
                $stage = ExtendRequestStatus::PendingHod;
            }

            $this->assertDecider($actor, $extendRequest, $stage);

            if ($stage === ExtendRequestStatus::PendingTeamLeader) {
                $extendRequest->update([
                    'team_leader_user_id' => $actor->getKey(),
                    'team_leader_approved_at' => now(),
                    'status' => ExtendRequestStatus::PendingHod,
                ]);

                WorkOrderEvent::record(
                    $workOrder,
                    $actor,
                    'extend_tl_approved',
                    $workOrder->status->value,
                    $workOrder->status->value,
                    [
                        'extend_request_id' => $extendRequest->getKey(),
                        'days' => $extendRequest->days,
                        'reason' => $extendRequest->reason,
                    ],
                );

                $this->hodChain($workOrder)
                    ->each(fn (User $recipient) => $recipient->notify(new ExtendRequestSubmitted($extendRequest)));

                return $extendRequest;
            }

            // Keputusan final hanya relevan selama WO masih dieksekusi —
            // WO yang sudah closed tidak boleh deadline-nya bergeser.
            if (! in_array($workOrder->status, self::extendableStatuses(), true)) {
                abort(409, 'WO sudah meninggalkan tahap eksekusi — pengajuan extend tidak dapat disetujui.');
            }

            $newDeadline = app(DeadlineCalculator::class)
                ->addBusinessDays($workOrder->deadline_date, $extendRequest->days);

            $workOrder->update([
                'deadline_date' => $newDeadline,
                'extend_count' => $workOrder->extend_count + 1,
                'extend_reason' => $extendRequest->reason,
                'extended_at' => now(),
                // Deadline baru = siklus eskalasi baru; marker lama di-reset
                // agar H+3/H+5/H+6 menghitung ulang dari jadwal baru.
                'escalation_h3_sent_at' => null,
                'escalation_h5_sent_at' => null,
                'escalation_h6_sent_at' => null,
                'is_escalated' => false,
            ]);

            $extendRequest->update([
                'approved_by_user_id' => $actor->getKey(),
                'approved_at' => now(),
                'status' => ExtendRequestStatus::Approved,
            ]);

            WorkOrderEvent::record(
                $workOrder,
                $actor,
                'extend_approved',
                $workOrder->status->value,
                $workOrder->status->value,
                [
                    'extend_request_id' => $extendRequest->getKey(),
                    'days' => $extendRequest->days,
                    'reason' => $extendRequest->reason,
                    'new_deadline' => $newDeadline->toDateString(),
                ],
            );

            $this->decisionRecipients($extendRequest)
                ->each(fn (User $recipient) => $recipient->notify(new ExtendRequestApproved($extendRequest)));

            return $extendRequest;
        });
    }

    /**
     * Menolak pengajuan pada tahap berjalan — alasan wajib, WO tidak
     * tersentuh.
     */
    public function reject(User $actor, ExtendRequest $extendRequest, string $reason): ExtendRequest
    {
        return DB::transaction(function () use ($actor, $extendRequest, $reason): ExtendRequest {
            // Urutan kunci konsisten (WO → pengajuan) — lihat approve().
            $workOrder = WorkOrder::query()
                ->whereKey($extendRequest->work_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $extendRequest = ExtendRequest::query()
                ->whereKey($extendRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // TL keluar setelah filing → rantai HOD mengambil alih keputusan.
            $stage = $extendRequest->status;

            if ($stage === ExtendRequestStatus::PendingTeamLeader
                && $this->departmentTeamLeaders($workOrder)->isEmpty()) {
                $stage = ExtendRequestStatus::PendingHod;
            }

            $this->assertDecider($actor, $extendRequest, $stage);

            if (! $extendRequest->status->isPending()) {
                abort(409, 'Pengajuan extend sudah diputuskan.');
            }

            $extendRequest->update([
                'rejected_by_user_id' => $actor->getKey(),
                'rejected_at' => now(),
                'rejected_reason' => $reason,
                'status' => ExtendRequestStatus::Rejected,
            ]);

            WorkOrderEvent::record(
                $workOrder,
                $actor,
                'extend_rejected',
                $workOrder->status->value,
                $workOrder->status->value,
                [
                    'extend_request_id' => $extendRequest->getKey(),
                    'days' => $extendRequest->days,
                    'reason' => $reason,
                ],
            );

            $this->decisionRecipients($extendRequest)
                ->each(fn (User $recipient) => $recipient->notify(new ExtendRequestRejected($extendRequest)));

            return $extendRequest;
        });
    }

    /**
     * Approver yang berhak bertindak pada tahap berjalan — service
     * menegakkan posisi rantai di atas gate permission
     * work-order.extend-approve.
     *
     * @return Collection<int, User>
     */
    public function stageApprovers(WorkOrder $workOrder, ExtendRequestStatus $stage): Collection
    {
        return $stage === ExtendRequestStatus::PendingTeamLeader
            ? $this->departmentTeamLeaders($workOrder)
            : $this->hodChain($workOrder);
    }

    /**
     * Mirror server-side dari visibilitas panel pengajuan extend (dipakai
     * payload show) — endpoint tetap sumber kebenaran; prop ini hanya
     * menghindari kalkulasi jendela di browser.
     */
    public function canRequestExtend(User $user, WorkOrder $workOrder): bool
    {
        if (! $user->can('work-order.extend')) {
            return false;
        }

        if (! in_array($workOrder->status, self::extendableStatuses(), true)) {
            return false;
        }

        if ($workOrder->deadline_date === null
            || $workOrder->extend_count >= self::MAX_APPROVED_PER_WORK_ORDER) {
            return false;
        }

        $pendingExists = ExtendRequest::query()
            ->where('work_order_id', $workOrder->getKey())
            ->whereIn('status', [
                ExtendRequestStatus::PendingTeamLeader->value,
                ExtendRequestStatus::PendingHod->value,
            ])
            ->exists();

        if ($pendingExists) {
            return false;
        }

        return $this->withinExtendWindow($workOrder);
    }

    /**
     * Mirror server-side dari tombol keputusan: apakah user berada pada
     * rantai tahap berjalan (dengan fallback rantai HOD saat TL kosong —
     * sama dengan approve()).
     */
    public function canDecideExtend(User $user, ExtendRequest $extendRequest): bool
    {
        if (! $extendRequest->status->isPending()) {
            return false;
        }

        if ($user->getKey() === $extendRequest->requested_by_user_id) {
            return false;
        }

        $workOrder = $extendRequest->workOrder;

        $stage = $extendRequest->status;

        if ($stage === ExtendRequestStatus::PendingTeamLeader
            && $this->departmentTeamLeaders($workOrder)->isEmpty()) {
            $stage = ExtendRequestStatus::PendingHod;
        }

        return $this->stageApprovers($workOrder, $stage)
            ->contains(fn (User $approver) => $approver->getKey() === $user->getKey());
    }

    /**
     * Telat ATAU mendekati telat (≤ NEAR_DEADLINE_BUSINESS_DAYS hari kerja).
     */
    private function withinExtendWindow(WorkOrder $workOrder): bool
    {
        $isOverdue = $workOrder->deadline_date->isPast();

        $isNearDeadline = $workOrder->deadline_date->startOfDay()->lte(
            app(DeadlineCalculator::class)->addBusinessDays(now(), self::NEAR_DEADLINE_BUSINESS_DAYS),
        );

        return $isOverdue || $isNearDeadline;
    }

    /**
     * Pengaju tidak boleh memutuskan pengajuannya sendiri, dan aktor harus
     * berada pada rantai tahap berjalan (stage efektif — TL yang keluar
     * digantikan rantai HOD).
     */
    private function assertDecider(User $actor, ExtendRequest $extendRequest, ?ExtendRequestStatus $stage = null): void
    {
        if ($actor->getKey() === $extendRequest->requested_by_user_id) {
            abort(403, 'Pengaju tidak dapat menyetujui atau menolak pengajuannya sendiri.');
        }

        $effectiveStage = $stage ?? $extendRequest->status;

        $eligible = $this->stageApprovers($extendRequest->workOrder, $effectiveStage);

        if ($eligible->first(fn (User $user) => $user->getKey() === $actor->getKey()) === null) {
            abort(403, 'Anda tidak berada pada tahap approval pengajuan extend ini.');
        }
    }

    /**
     * Penerima keputusan extend: pengaju, pelaksana yang ter-link akun, dan
     * requester WO.
     *
     * @return Collection<int, User>
     */
    private function decisionRecipients(ExtendRequest $extendRequest): Collection
    {
        $workOrder = $extendRequest->workOrder;

        $assignees = Employee::query()
            ->whereIn('id', $workOrder->assigned_employees ?? [])
            ->with('user')
            ->get()
            ->map(fn (Employee $employee) => $employee->user);

        return $assignees
            ->push($extendRequest->requester)
            ->push($workOrder->requester)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Team Leader department target — department dianggap punya TL bila ada
     * user ber-role `team_leader` di department itu (settled OQ-5).
     *
     * @return Collection<int, User>
     */
    private function departmentTeamLeaders(WorkOrder $workOrder): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'team_leader'))
            ->whereHas('employee', fn ($query) => $query->where('department_id', $workOrder->target_department_id))
            ->get();
    }

    /**
     * Rantai HOD untuk tahap final — sama dengan fallback eskalasi/HOD:
     * hod_user_id → manager_user_id → role hod di department → super admin
     * tenant.
     *
     * @return Collection<int, User>
     */
    private function hodChain(WorkOrder $workOrder): Collection
    {
        return app(HodNotificationResolver::class)->resolve($workOrder->targetDepartment);
    }
}
