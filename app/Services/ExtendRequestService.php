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
            $from = $workOrder->status;

            if (! in_array($from, self::extendableStatuses(), true)) {
                abort(409, 'Extend hanya untuk WO dalam tahap eksekusi.');
            }

            // Telat = deadline sudah terlewat; konsisten dengan ambang
            // eskalasi (`deadline_date < now()`), deadline hari ini ikut
            // dihitung telat.
            if ($workOrder->deadline_date === null || ! $workOrder->deadline_date->isPast()) {
                abort(409, 'Extend deadline hanya untuk WO yang sudah terlambat.');
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

            $stage = $this->departmentTeamLeaders($workOrder)->isNotEmpty()
                ? ExtendRequestStatus::PendingTeamLeader
                : ExtendRequestStatus::PendingHod;

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
            $extendRequest->loadMissing('workOrder');
            $workOrder = $extendRequest->workOrder;

            $this->assertDecider($actor, $extendRequest);

            if (! $extendRequest->status->isPending()) {
                abort(409, 'Pengajuan extend sudah diputuskan.');
            }

            if ($extendRequest->status === ExtendRequestStatus::PendingTeamLeader) {
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

            $newDeadline = app(DeadlineCalculator::class)
                ->addBusinessDays($workOrder->deadline_date, $extendRequest->days);

            $workOrder->update([
                'deadline_date' => $newDeadline,
                'extend_count' => $workOrder->extend_count + 1,
                'extend_reason' => $extendRequest->reason,
                'extended_at' => now(),
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
            $extendRequest->loadMissing('workOrder');
            $workOrder = $extendRequest->workOrder;

            $this->assertDecider($actor, $extendRequest);

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
     * Pengaju tidak boleh memutuskan pengajuannya sendiri, dan aktor harus
     * berada pada rantai tahap berjalan.
     */
    private function assertDecider(User $actor, ExtendRequest $extendRequest): void
    {
        if ($actor->getKey() === $extendRequest->requested_by_user_id) {
            abort(403, 'Pengaju tidak dapat menyetujui atau menolak pengajuannya sendiri.');
        }

        $eligible = $this->stageApprovers($extendRequest->workOrder, $extendRequest->status);

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
