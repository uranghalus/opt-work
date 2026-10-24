<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkData;
use App\Models\WorkDataPekerja;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Notifications\WorkOrderAssigned;
use App\Notifications\WorkOrderCreated;
use App\Notifications\WorkOrderRejected;
use App\Notifications\WorkOrderReviewed;
use App\Notifications\WorkOrderSubmitted;
use App\Notifications\WorkOrderVerified;
use App\WorkOrderStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function __construct(
        private HodNotificationResolver $hodNotificationResolver,
        private WorkPlanningService $workPlanningService,
        private WorkOrderRequestService $requestService,
        private WorkTimeLogService $workTimeLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $attachments
     */
    public function create(User $requester, Department $targetDepartment, array $data, array $attachments = []): WorkOrder
    {
        return DB::transaction(function () use ($requester, $targetDepartment, $data, $attachments): WorkOrder {
            // R5: gerbang approval department pengaju hanya untuk WO lintas
            // department — requester tanpa department (akun admin) dan WO
            // satu department sendiri langsung masuk alur lama (R7: SLA
            // tetap dari tanggal assign, bukan dari rilis).
            $needsApproval = $this->requestService->needsApproval($requester, $targetDepartment);

            // Nomor WO dihitung dari max(nomor_wo) tanpa lock — dua store()
            // bersamaan bisa menghitung nomor sama; unique(tenant_id, nomor_wo)
            // menolak yang kedua. Ulangi dengan nomor segar (max kini terlihat
            // karena transaksi pertama sudah commit saat INSERT kedua gagal).
            $attempt = 0;

            while (true) {
                try {
                    $workOrder = WorkOrder::create([
                        'nomor_wo' => $this->generateNomorWo($targetDepartment),
                        'requester_user_id' => $requester->getKey(),
                        'target_department_id' => $targetDepartment->getKey(),
                        'category' => $data['category'],
                        'title' => $data['title'],
                        'description' => $data['description'],
                        'location' => $data['location'] ?? null,
                        'attachments' => $attachments === [] ? null : $attachments,
                        'status' => $needsApproval
                            ? WorkOrderStatus::PendingDeptApproval->value
                            : WorkOrderStatus::WaitingHod->value,
                        // R8: belum rilis = dept tujuan buta terhadap WO ini
                        // sampai approver department pengaju menyetujui.
                        'released_at' => $needsApproval ? null : now(),
                        'requested_schedule_date' => $data['requested_schedule_date'] ?? null,
                    ]);

                    break;
                } catch (QueryException $exception) {
                    $attempt++;

                    if ($attempt >= 5 || ! $this->isDuplicateKey($exception)) {
                        throw $exception;
                    }
                }
            }

            if ($needsApproval) {
                // R11: notifikasi hanya ke approver department pengaju —
                // dept tujuan baru diberi tahu saat approve.
                $this->requestService->notifyApprovers($workOrder);
            } else {
                $this->hodNotificationResolver
                    ->resolve($targetDepartment)
                    ->each(fn (User $recipient) => $recipient->notify(new WorkOrderCreated($workOrder)));
            }

            return $workOrder;
        });
    }

    /**
     * Unique-constraint violation across drivers: MySQL 1062, sqlite 19/2067.
     */
    private function isDuplicateKey(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000' && in_array($driverCode, [1062, 19, 2067], true);
    }

    /**
     * The HOD execution decision (FR-1.x review): immediate execution or a
     * schedule date. Urgent by Accident is forced to immediate execution —
     * the FormRequest cannot check the category, so the guard lives here.
     * Re-review is allowed until the WO is assigned to personnel.
     *
     * @param  array<string, mixed>  $data  validated ReviewWorkOrderRequest payload
     */
    public function review(User $hod, WorkOrder $workOrder, array $data): WorkOrder
    {
        return DB::transaction(function () use ($hod, $workOrder, $data): WorkOrder {
            $from = $workOrder->status;

            if (! in_array($from, [
                WorkOrderStatus::WaitingHod,
                WorkOrderStatus::HodApproved,
                WorkOrderStatus::Scheduled,
            ], true)) {
                abort(409, 'Work Order sudah meninggalkan tahap review.');
            }

            $action = (string) $data['action'];

            if ($action === 'schedule' && ! $workOrder->category->allowsScheduling()) {
                throw ValidationException::withMessages([
                    'hod_action' => 'Urgent by Accident wajib dieksekusi langsung — tidak dapat dijadwalkan.',
                ]);
            }

            $to = $action === 'schedule' ? WorkOrderStatus::Scheduled : WorkOrderStatus::HodApproved;

            $workOrder->update([
                'hod_action' => $action,
                'scheduled_date' => $data['scheduled_date'] ?? null,
                'hod_notes' => $data['hod_notes'] ?? null,
                'status' => $to,
            ]);

            // FR-2.4: planning row lahir bersama keputusan jadwal (idempoten —
            // unique work_order_id). Bila HOD berbalik ke eksekusi langsung,
            // planning yang tersisa dihapus agar tidak ada jadwal yatim.
            if ($action === 'schedule') {
                $this->workPlanningService->createForWorkOrder($workOrder);
            } else {
                $this->workPlanningService->discardForWorkOrder($workOrder);
            }

            WorkOrderEvent::record(
                $workOrder,
                $hod,
                'reviewed',
                $from->value,
                $to->value,
                ['action' => $action, 'scheduled_date' => $data['scheduled_date'] ?? null],
            );

            $workOrder->requester->notify(new WorkOrderReviewed($workOrder));

            return $workOrder;
        });
    }

    /**
     * Assign 1+ karyawan pelaksana to a reviewed work order (FR-1.x):
     * `assigned_at` and the business-day deadline are set exactly once —
     * from the FIRST assign (revisi penugasan replaces the people, never
     * the SLA clock; extending is the separate extend flow, issue 06).
     * Assignees without a linked akun still get assigned; they are recorded
     * in the history meta so the HOD knows no in-app notification reached
     * them (Q14, grill 2026-10-17).
     *
     * @param  array<string, mixed>  $data  validated AssignWorkOrderRequest payload
     */
    public function assign(User $assigner, WorkOrder $workOrder, array $data): WorkOrder
    {
        return DB::transaction(function () use ($assigner, $workOrder, $data): WorkOrder {
            $from = $workOrder->status;

            if ($from === WorkOrderStatus::WaitingHod) {
                abort(409, 'Review HOD dulu sebelum menugaskan karyawan.');
            }

            // Re-assign pasc-assign diizinkan saat status masih `assigned`
            // (grill 2026-10-23, keputusan 0a): mengganti orang tanpa
            // menggeser SLA — `assigned_at`/deadline tetap dari assign pertama.
            if (! in_array($from, [
                WorkOrderStatus::HodApproved,
                WorkOrderStatus::Scheduled,
                WorkOrderStatus::Assigned,
            ], true)) {
                abort(409, 'Work Order sudah meninggalkan tahap penugasan.');
            }

            $employees = Employee::query()
                ->with('user')
                ->whereIn('id', $data['employees'])
                ->get();

            if ($employees->count() !== count(array_unique($data['employees']))) {
                throw ValidationException::withMessages([
                    'employees' => 'Ada karyawan yang tidak ditemukan di cabang ini.',
                ]);
            }

            $outside = $employees->first(
                fn (Employee $employee): bool => $employee->department_id !== $workOrder->target_department_id,
            );

            if ($outside !== null) {
                throw ValidationException::withMessages([
                    'employees' => 'Karyawan '.$outside->nama_employee.' berada di luar department target WO.',
                ]);
            }

            // Q7: a banned karyawan is a record-level block — assignment is
            // execution, so it refuses here with the same form-error shape.
            $banned = $employees->first(fn (Employee $employee): bool => $employee->isBanned());

            if ($banned !== null) {
                throw ValidationException::withMessages([
                    'employees' => 'Karyawan '.$banned->nama_employee.' sedang diblokir dan tidak dapat ditugaskan.',
                ]);
            }

            $firstAssignment = $workOrder->assigned_at === null;

            $previousIds = collect($workOrder->assigned_employees ?? []);

            $workOrder->update([
                'assigned_employees' => $employees->modelKeys(),
                'assignment_notes' => $data['assignment_notes'] ?? null,
                'status' => WorkOrderStatus::Assigned,
                'assigned_at' => $firstAssignment ? now() : $workOrder->assigned_at,
                'deadline_date' => $firstAssignment
                    ? app(DeadlineCalculator::class)->calculate($workOrder->category, now())
                    : $workOrder->deadline_date,
            ]);

            $employeeIds = $employees->modelKeys();
            $removedIds = $previousIds->diff($employeeIds)->values()->all();
            $recipients = $employees
                ->map(fn (Employee $employee) => $employee->user)
                ->filter();
            $unlinkedIds = $employees
                ->filter(fn (Employee $employee) => $employee->user === null)
                ->values()
                ->modelKeys();

            WorkOrderEvent::record(
                $workOrder,
                $assigner,
                'assigned',
                $from->value,
                WorkOrderStatus::Assigned->value,
                [
                    'employee_ids' => $employeeIds,
                    'removed_employee_ids' => $removedIds,
                    'notified_user_ids' => $recipients->pluck('id')->all(),
                    'unlinked_employee_ids' => $unlinkedIds,
                    'assignment_notes' => $data['assignment_notes'] ?? null,
                    'deadline_date' => $workOrder->deadline_date?->toDateString(),
                ],
            );

            if ($unlinkedIds !== []) {
                Log::warning('WO assigned to employees without a linked akun; no in-app notification possible.', [
                    'work_order_id' => $workOrder->getKey(),
                    'unlinked_employee_ids' => $unlinkedIds,
                ]);
            }

            $recipients->each(
                fn (User $recipient) => $recipient->notify(new WorkOrderAssigned($workOrder)),
            );

            return $workOrder;
        });
    }

    /**
     * FR-1.7: pelaksana menyerahkan hasil pekerjaan — catatan wajib, foto
     * opsional (maks 3). From assigned or revision → pending_verify; hasil
     * attempt sebelumnya ditimpa (histori attempt = FR-3.x Work Data).
     * Authorization is permission-based (work-order.submit), same shape as
     * review/assign — not restricted to the recorded assignees.
     *
     * @param  array<int, string>  $attachments  stored result photo paths
     */
    public function submit(User $actor, WorkOrder $workOrder, string $notes, array $attachments = []): WorkOrder
    {
        return DB::transaction(function () use ($actor, $workOrder, $notes, $attachments): WorkOrder {
            $from = $workOrder->status;

            if (! in_array($from, [WorkOrderStatus::Assigned, WorkOrderStatus::InProgress, WorkOrderStatus::Revision], true)) {
                abort(409, 'Work Order belum ditugaskan atau sedang menunggu hasil verifikasi.');
            }

            $workOrder->update([
                'result_notes' => $notes,
                'result_attachments' => $attachments === [] ? null : $attachments,
                'status' => WorkOrderStatus::PendingVerify->value,
            ]);

            WorkOrderEvent::record(
                $workOrder,
                $actor,
                'submitted',
                $from->value,
                WorkOrderStatus::PendingVerify->value,
                [
                    'notes_preview' => str($notes)->limit(120)->toString(),
                    'attachment_count' => count($attachments),
                ],
            );

            // R17/R18: menyerahkan hasil = mengakhiri timer pelaksana
            // pengaju (segmen jeda yang terbuka ikut ditutup).
            if ($actor->employee_id !== null) {
                $this->workTimeLogService->finish($workOrder, (string) $actor->employee_id);
            }

            $this->hodNotificationResolver
                ->resolve($workOrder->targetDepartment)
                ->each(fn (User $recipient) => $recipient->notify(new WorkOrderSubmitted($workOrder)));

            return $workOrder;
        });
    }

    /**
     * FR-1.8: HOD menyetujui hasil submit → closed. Notifications reach
     * the assigned pelaksana and the requester (Q2, grill 2026-10-22).
     */
    public function approve(User $hod, WorkOrder $workOrder): WorkOrder
    {
        return DB::transaction(function () use ($hod, $workOrder): WorkOrder {
            $from = $workOrder->status;

            if ($from !== WorkOrderStatus::PendingVerify) {
                abort(409, 'Work Order tidak dalam tahap verifikasi hasil.');
            }

            $workOrder->update(['status' => WorkOrderStatus::Closed->value]);

            WorkOrderEvent::record(
                $workOrder,
                $hod,
                'verified',
                $from->value,
                WorkOrderStatus::Closed->value,
            );

            // FR-3.1: satu record Work Data per WO closed — snapshot data
            // turunan WO. OQ: alur revisi post-closing (PRD FR-3.3) belum
            // settled — WO closed bersifat terminal di MVP (tanpa
            // reopen/revisi pasca-close), Work Data hanya menerima satu
            // finalisasi analitik (append-only).
            $this->generateWorkData($workOrder, $hod);

            $this->verifyResultRecipients($workOrder)
                ->each(fn (User $recipient) => $recipient->notify(new WorkOrderVerified($workOrder)));

            return $workOrder;
        });
    }

    /**
     * FR-1.8: HOD menolak hasil → revision, catatan revisi wajib. The
     * deadline is NOT reset — the SLA clock keeps running; extending is
     * the separate extend flow (issue 06).
     */
    public function reject(User $hod, WorkOrder $workOrder, string $notes): WorkOrder
    {
        return DB::transaction(function () use ($hod, $workOrder, $notes): WorkOrder {
            $from = $workOrder->status;

            if ($from !== WorkOrderStatus::PendingVerify) {
                abort(409, 'Work Order tidak dalam tahap verifikasi hasil.');
            }

            $workOrder->update([
                'status' => WorkOrderStatus::Revision->value,
                'hod_notes' => $notes,
            ]);

            WorkOrderEvent::record(
                $workOrder,
                $hod,
                'rejected',
                $from->value,
                WorkOrderStatus::Revision->value,
                ['notes' => $notes],
            );

            $this->verifyResultRecipients($workOrder)
                ->each(fn (User $recipient) => $recipient->notify(new WorkOrderRejected($workOrder, $notes)));

            return $workOrder;
        });
    }

    /**
     * Verify-result notification receivers: the assigned pelaksana with a
     * linked akun, plus the requester (Q2, grill 2026-10-22).
     *
     * @return Collection<int, User>
     */
    private function verifyResultRecipients(WorkOrder $workOrder): Collection
    {
        $assignees = Employee::query()
            ->whereIn('id', $workOrder->assigned_employees ?? [])
            ->with('user')
            ->get()
            ->map(fn (Employee $employee) => $employee->user);

        return $assignees
            ->push($workOrder->requester)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * FR-3.1: snapshot Work Data saat WO closed — no kerja, department,
     * jam pengerjaan (assigned → closed), status akhir, gambar
     * sebelum/sesudah, dan pelaksana. Analitik post-mortem diisi lewat
     * finalisasi sekali di WorkDataController (append-only).
     */
    private function generateWorkData(WorkOrder $workOrder, User $hod): void
    {
        $employees = Employee::query()
            ->whereIn('id', $workOrder->assigned_employees ?? [])
            ->get();

        $workData = WorkData::create([
            'work_order_id' => $workOrder->getKey(),
            'no_kerja' => $workOrder->nomor_wo,
            'department_id' => $workOrder->target_department_id,
            'status_pekerjaan' => 'selesai',
            'tgl_mulai_aktual' => $workOrder->assigned_at,
            'tgl_selesai_aktual' => now(),
            'jam_pengerjaan' => $workOrder->assigned_at?->floatDiffInHours(now()),
            'gambar_sebelum' => $workOrder->attachments ?? [],
            'gambar_sesudah' => $workOrder->result_attachments ?? [],
            'pic_lead_user_id' => $hod->getKey(),
        ]);

        foreach ($employees as $employee) {
            WorkDataPekerja::create([
                'work_data_id' => $workData->getKey(),
                'employee_id' => $employee->getKey(),
                'nama_employee' => $employee->nama_employee,
                'role_pekerja' => 'pelaksana',
            ]);
        }
    }

    private function generateNomorWo(Department $targetDepartment): string
    {
        $tenantCode = $targetDepartment->tenant->code;
        $prefix = 'WO-'.strtoupper($tenantCode).'-'.now()->format('Ymd').'-';

        $last = WorkOrder::query()
            ->where('tenant_id', $targetDepartment->tenant_id)
            ->where('nomor_wo', 'like', $prefix.'%')
            ->max('nomor_wo');

        $sequence = $last === null ? 1 : ((int) str($last)->afterLast('-')->toString() + 1);

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
