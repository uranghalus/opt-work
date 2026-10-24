<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Notifications\WorkOrderCancelled;
use App\Notifications\WorkOrderCreated;
use App\Notifications\WorkOrderRequestApproved;
use App\Notifications\WorkOrderRequestRejected;
use App\Notifications\WorkOrderRequestSubmitted;
use App\WorkOrderStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Gerbang approval lintas-department (R1–R12): WO yang ditujukan ke
 * department lain menunggu persetujuan department PENGAJU sebelum dirilis
 * ke department tujuan. Setelah rilis, alur berjalan persis seperti
 * sebelumnya (review → assign → eksekusi → verifikasi).
 *
 * Aturan kunci:
 * - R2/R9: approver = rantai department pengaju (hod_user_id →
 *   manager_user_id → role `hod` → role `staf_admin_dept`); requester
 *   TIDAK PERNAH bisa memutuskan permintaannya sendiri, bahkan bila
 *   ber-role HOD. Super admin beroleh lewat bypass Gate (ADR-0004).
 * - R5: WO satu department sendiri (dan requester tanpa department,
 *   mis. akun admin) melewati gerbang ini.
 * - R8: department tujuan tidak melihat WO sebelum released_at terisi —
 *   dipaksa di scopeVisibleFor + Gate work-order.view, bukan cuma UI.
 * - R12: cancel = terminal; dua kasus — (a) requester/approver department
 *   pengaju saat belum rilis, (b) reviewer department tujuan membatalkan
 *   sebagai DUPLIKAT saat review (kasus WO yang sama diterima dari dua
 *   department berbeda) — wajib menunjuk WO duplikatnya.
 */
class WorkOrderRequestService
{
    public function __construct(
        private HodNotificationResolver $hodNotificationResolver,
    ) {}

    /**
     * Department milik requester via karyawan ter-link. Null = akun tanpa
     * department (mis. Admin Tenant) — gerbang dilewati untuk akun ini
     * karena ia otoritas tingkat cabang, bukan staf department.
     */
    public function requesterDepartment(User $requester): ?Department
    {
        $employee = $requester->employee;

        if ($employee === null || $employee->department_id === null) {
            return null;
        }

        return $employee->department;
    }

    /**
     * R5: gerbang hanya untuk lintas department.
     */
    public function needsApproval(User $requester, Department $targetDepartment): bool
    {
        $source = $this->requesterDepartment($requester);

        return $source !== null && $source->getKey() !== $targetDepartment->getKey();
    }

    /**
     * Rantai approver department pengaju. Requester selalu dikeluarkan dari
     * setiap lapis (R9) — bila HOD adalah requester sendiri, lapisan
     * berikutnya (deputy → role hod → staf admin) yang mengambil alih.
     * Fallback terakhir: Admin Tenant cabang + log kritikal (mirror rantai
     * notifikasi OQ-6).
     *
     * @return Collection<int, User>
     */
    public function approversFor(User $requester): Collection
    {
        $department = $this->requesterDepartment($requester);

        if ($department === null) {
            return collect();
        }

        $withoutRequester = fn (Collection $users): Collection => $users
            ->reject(fn (User $user) => $user->getKey() === $requester->getKey())
            ->unique('id')
            ->values();

        $recipients = $withoutRequester(collect()
            ->merge($this->usersById($department->hod_user_id))
            ->merge($this->usersById($department->manager_user_id)));

        if ($recipients->isEmpty()) {
            $recipients = $withoutRequester($this->usersWithRoleInDepartment($department, 'hod'));
        }

        if ($recipients->isEmpty()) {
            $recipients = $withoutRequester($this->usersWithRoleInDepartment($department, 'staf_admin_dept'));
        }

        if ($recipients->isEmpty()) {
            $recipients = User::query()
                ->where('is_super_admin', true)
                ->where('tenant_id', $department->tenant_id)
                ->get();

            Log::critical('Gerbang approval: tidak ada approver untuk department pengaju.', [
                'department_id' => $department->getKey(),
                'tenant_id' => $department->tenant_id,
            ]);
        }

        return $recipients->values();
    }

    /**
     * Keanggotaan murni di rantai approver department pengaju (tanpa cek
     * status WO) — dipakai Gate work-order.view untuk WO yang belum rilis.
     */
    public function isApprover(User $user, WorkOrder $workOrder): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->approversFor($workOrder->requester)
            ->contains(fn (User $approver) => $approver->getKey() === $user->getKey());
    }

    /**
     * Boleh memutuskan permintaan: anggota rantai approver dept pengaju DAN
     * WO masih pending. R9: requester sendiri tidak pernah lolos.
     */
    public function canDecide(User $user, WorkOrder $workOrder): bool
    {
        if ($workOrder->status !== WorkOrderStatus::PendingDeptApproval) {
            return false;
        }

        return $this->isApprover($user, $workOrder);
    }

    /**
     * Keanggotaan rantai reviewer department TUJUAN (untuk cancel-duplikat
     * saat review): hod_user_id / manager_user_id, atau pemegang role
     * `hod` / `staf_admin_dept` yang karyawannya di department itu.
     */
    public function isTargetReviewer(User $user, WorkOrder $workOrder): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $department = $workOrder->targetDepartment;

        if ($department === null) {
            return false;
        }

        if ($department->hod_user_id === $user->getKey()
            || $department->manager_user_id === $user->getKey()) {
            return true;
        }

        if ($user->employee_id === null) {
            return false;
        }

        return User::query()
            ->whereKey($user->getKey())
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['hod', 'staf_admin_dept']))
            ->whereHas('employee', fn ($query) => $query->where('department_id', $department->getKey()))
            ->exists();
    }

    /**
     * Kandidat duplikat (OQ-4b): judul sama (case-insensitive) dalam 30
     * hari terakhir, TANPA memandang department tujuan — menangkap kasus
     * WO yang sama diterima dari dua department berbeda. Hanya penanda:
     * keputusan cancel tetap manusia.
     *
     * @return Collection<int, WorkOrder>
     */
    public function duplicateCandidates(WorkOrder $workOrder, int $limit = 5): Collection
    {
        return WorkOrder::query()
            ->whereKeyNot($workOrder->getKey())
            ->whereNotIn('status', [
                WorkOrderStatus::Cancelled->value,
                WorkOrderStatus::RejectedByDept->value,
                WorkOrderStatus::Draft->value,
            ])
            ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower(trim($workOrder->title))])
            ->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * R3: approve → WO rilis ke department tujuan; alur setelahnya
     * persis alur lama (notifikasi WorkOrderCreated baru dikirim di sini —
     * R11: dept tujuan baru tahu setelah approve).
     */
    public function approve(User $approver, WorkOrder $workOrder): WorkOrder
    {
        return DB::transaction(function () use ($approver, $workOrder): WorkOrder {
            if ($workOrder->status !== WorkOrderStatus::PendingDeptApproval) {
                abort(409, 'Work Order tidak dalam tahap persetujuan department pengaju.');
            }

            if (! $this->isApprover($approver, $workOrder)) {
                abort(403, 'Hanya approver department pengaju yang dapat menyetujui permintaan ini.');
            }

            $from = $workOrder->status;
            $workOrder->update([
                'status' => WorkOrderStatus::WaitingHod->value,
                'released_at' => now(),
            ]);

            WorkOrderEvent::record(
                $workOrder,
                $approver,
                'request_approved',
                $from->value,
                WorkOrderStatus::WaitingHod->value,
            );

            $this->hodNotificationResolver
                ->resolve($workOrder->targetDepartment)
                ->each(fn (User $recipient) => $recipient->notify(new WorkOrderCreated($workOrder)));

            $workOrder->requester->notify(new WorkOrderRequestApproved($workOrder));

            return $workOrder;
        });
    }

    /**
     * R4: reject wajib alasan — WO berhenti, tak pernah sampai ke
     * department tujuan; requester dinotifikasi dan bisa revisi/resubmit
     * atau cancel.
     */
    public function reject(User $approver, WorkOrder $workOrder, string $reason): WorkOrder
    {
        return DB::transaction(function () use ($approver, $workOrder, $reason): WorkOrder {
            if ($workOrder->status !== WorkOrderStatus::PendingDeptApproval) {
                abort(409, 'Work Order tidak dalam tahap persetujuan department pengaju.');
            }

            if (! $this->isApprover($approver, $workOrder)) {
                abort(403, 'Hanya approver department pengaju yang dapat menolak permintaan ini.');
            }

            $from = $workOrder->status;
            $workOrder->update([
                'status' => WorkOrderStatus::RejectedByDept->value,
                'request_rejection_reason' => $reason,
            ]);

            WorkOrderEvent::record(
                $workOrder,
                $approver,
                'request_rejected',
                $from->value,
                WorkOrderStatus::RejectedByDept->value,
                ['reason' => $reason],
            );

            $workOrder->requester->notify(new WorkOrderRequestRejected($workOrder, $reason));

            return $workOrder;
        });
    }

    /**
     * R12: revisi WO yang ditolak — hanya requester, hanya pada status
     * RejectedByDept. Resubmit terpisah (dengan atau tanpa edit).
     */
    public function updateRejected(User $requester, WorkOrder $workOrder, array $data): WorkOrder
    {
        return DB::transaction(function () use ($requester, $workOrder, $data): WorkOrder {
            $this->assertRequester($requester, $workOrder);

            if ($workOrder->status !== WorkOrderStatus::RejectedByDept) {
                abort(409, 'Hanya Work Order yang ditolak department pengaju yang dapat direvisi.');
            }

            $workOrder->update($data);

            WorkOrderEvent::record(
                $workOrder,
                $requester,
                'request_updated',
                WorkOrderStatus::RejectedByDept->value,
                WorkOrderStatus::RejectedByDept->value,
                ['fields' => array_keys($data)],
            );

            return $workOrder;
        });
    }

    /**
     * R12: ajukan ulang — kembali ke gerbang approval (tanpa batas
     * percobaan, keputusan OQ-5), alasan tolak lama dibersihkan.
     */
    public function resubmit(User $requester, WorkOrder $workOrder): WorkOrder
    {
        return DB::transaction(function () use ($requester, $workOrder): WorkOrder {
            $this->assertRequester($requester, $workOrder);

            if ($workOrder->status !== WorkOrderStatus::RejectedByDept) {
                abort(409, 'Hanya Work Order yang ditolak department pengaju yang bisa diajukan ulang.');
            }

            $from = $workOrder->status;
            $workOrder->update([
                'status' => WorkOrderStatus::PendingDeptApproval->value,
                'request_rejection_reason' => null,
            ]);

            WorkOrderEvent::record(
                $workOrder,
                $requester,
                'resubmitted',
                $from->value,
                WorkOrderStatus::PendingDeptApproval->value,
            );

            $this->notifyApprovers($workOrder);

            return $workOrder;
        });
    }

    /**
     * R12: cancel = terminal. Dua kasus —
     * (a) belum rilis (Pending/Rejected): requester pemilik ATAU approver
     *     department pengaju;
     * (b) saat review (WaitingHod): reviewer department tujuan, HANYA
     *     sebagai duplikat — wajib menunjuk WO duplikatnya (kasus WO yang
     *     sama diterima dari dua department berbeda).
     */
    public function cancel(User $actor, WorkOrder $workOrder, ?string $reason = null, ?string $duplicateOfWorkOrderId = null): WorkOrder
    {
        return DB::transaction(function () use ($actor, $workOrder, $reason, $duplicateOfWorkOrderId): WorkOrder {
            $from = $workOrder->status;
            $isRequester = $workOrder->requester_user_id === $actor->getKey();

            $allowed = match (true) {
                $from === WorkOrderStatus::PendingDeptApproval,
                $from === WorkOrderStatus::RejectedByDept => $isRequester || $this->isApprover($actor, $workOrder),
                $from === WorkOrderStatus::WaitingHod => $duplicateOfWorkOrderId !== null
                    && $this->isTargetReviewer($actor, $workOrder),
                default => false,
            };

            if (! $allowed) {
                abort(403, 'Anda tidak berhak membatalkan Work Order ini pada tahap ini.');
            }

            $meta = ['reason' => $reason];

            if ($duplicateOfWorkOrderId !== null) {
                $duplicate = WorkOrder::query()->whereKey($duplicateOfWorkOrderId)->first();

                if ($duplicate === null || $duplicate->getKey() === $workOrder->getKey()) {
                    throw ValidationException::withMessages([
                        'duplicate_of' => 'Work Order duplikat tidak ditemukan.',
                    ]);
                }

                $meta['duplicate_of'] = $duplicate->nomor_wo;
                $meta['duplicate_of_id'] = $duplicate->getKey();
            }

            $workOrder->update(['status' => WorkOrderStatus::Cancelled->value]);

            WorkOrderEvent::record(
                $workOrder,
                $actor,
                'cancelled',
                $from->value,
                WorkOrderStatus::Cancelled->value,
                $meta,
            );

            $workOrder->requester->notify(new WorkOrderCancelled(
                $workOrder,
                $reason,
                $meta['duplicate_of'] ?? null,
            ));

            return $workOrder;
        });
    }

    /**
     * R11: permintaan baru / hasil resubmit → notifikasi ke rantai approver
     * department pengaju, dilengkapi penanda kandidat duplikat.
     */
    public function notifyApprovers(WorkOrder $workOrder): void
    {
        $duplicates = $this->duplicateCandidates($workOrder)
            ->pluck('nomor_wo')
            ->all();

        $this->approversFor($workOrder->requester)
            ->each(fn (User $recipient) => $recipient->notify(
                new WorkOrderRequestSubmitted($workOrder, $duplicates),
            ));
    }

    private function assertRequester(User $user, WorkOrder $workOrder): void
    {
        if ($workOrder->requester_user_id !== $user->getKey()) {
            abort(403, 'Hanya pemohon yang dapat melakukan aksi ini.');
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function usersById(?int $userId): Collection
    {
        if ($userId === null) {
            return collect();
        }

        return User::query()->whereKey($userId)->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function usersWithRoleInDepartment(Department $department, string $role): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', $role))
            ->whereHas('employee', fn ($query) => $query->where('department_id', $department->getKey()))
            ->get();
    }
}
