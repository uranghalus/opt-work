<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderCreated;
use App\WorkOrderStatus;
use Illuminate\Support\Facades\DB;

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
