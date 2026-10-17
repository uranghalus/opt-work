<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Notifications\WorkOrderCreated;
use App\Notifications\WorkOrderReviewed;
use App\WorkOrderStatus;
use Illuminate\Support\Facades\DB;
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
