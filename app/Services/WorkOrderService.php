<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Notifications\WorkOrderAssigned;
use App\Notifications\WorkOrderCreated;
use App\Notifications\WorkOrderRejected;
use App\Notifications\WorkOrderReviewed;
use App\Notifications\WorkOrderSubmitted;
use App\Notifications\WorkOrderVerified;
use App\WorkOrderStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function __construct(private HodNotificationResolver $hodNotificationResolver) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $attachments
     */
    public function create(User $requester, Department $targetDepartment, array $data, array $attachments = []): WorkOrder
    {
        return DB::transaction(function () use ($requester, $targetDepartment, $data, $attachments): WorkOrder {
            $workOrder = WorkOrder::create([
                'nomor_wo' => $this->generateNomorWo($targetDepartment),
                'requester_user_id' => $requester->getKey(),
                'target_department_id' => $targetDepartment->getKey(),
                'category' => $data['category'],
                'title' => $data['title'],
                'description' => $data['description'],
                'attachments' => $attachments === [] ? null : $attachments,
                'status' => WorkOrderStatus::WaitingHod->value,
                'requested_schedule_date' => $data['requested_schedule_date'] ?? null,
            ]);

            $this->hodNotificationResolver
                ->resolve($targetDepartment)
                ->each(fn (User $recipient) => $recipient->notify(new WorkOrderCreated($workOrder)));

            return $workOrder;
        });
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

            if (! in_array($from, [WorkOrderStatus::Assigned, WorkOrderStatus::Revision], true)) {
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
