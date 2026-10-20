<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderDeadlineEscalated;
use App\Services\DeadlineCalculator;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

#[Signature('app:check-deadline-escalation')]
#[Description('检查逾期 Work Order 并发送分级 escalations: H+3→Team Leader, H+5→HOD, H+6→DGM/GM')]
class CheckDeadlineEscalation extends Command
{
    private const ESCALATION_H3_DAYS = 3;

    private const ESCALATION_H5_DAYS = 5;

    private const ESCALATION_H6_DAYS = 6;

    public function handle(DeadlineCalculator $calculator): int
    {
        $activeStatuses = [
            WorkOrderStatus::Assigned->value,
            WorkOrderStatus::InProgress->value,
            WorkOrderStatus::PendingVerify->value,
            WorkOrderStatus::Revision->value,
        ];

        $overdueWOs = WorkOrder::withoutGlobalScope('tenant')
            ->where('deadline_date', '<', now())
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('assigned_at')
            ->whereNotNull('deadline_date')
            ->get();

        if ($overdueWOs->isEmpty()) {
            $this->info('没有逾期的 Work Order。');

            return Command::SUCCESS;
        }

        $this->info("检查到 {$overdueWOs->count()} 个逾期 WO，开始处理 escalations...");

        foreach ($overdueWOs as $workOrder) {
            $this->processEscalation($workOrder, $calculator);
        }

        $this->info('Escalation 检查完成。');

        return Command::SUCCESS;
    }

    private function processEscalation(WorkOrder $workOrder, DeadlineCalculator $calculator): void
    {
        $department = Department::withoutGlobalScope('tenant')
            ->find($workOrder->target_department_id);
        if (! $department) {
            Log::error('Escalation: 找不到部门', ['work_order_id' => $workOrder->id]);

            return;
        }

        $businessDaysElapsed = $this->calculateBusinessDaysElapsed(
            $workOrder->assigned_at,
            now(),
            $workOrder->category,
        );

        // H+3: Team Leader (if exists) → HOD
        if ($businessDaysElapsed >= self::ESCALATION_H3_DAYS
            && $workOrder->escalation_h3_sent_at === null
        ) {
            $this->sendEscalation($workOrder, $department, 'h3', $businessDaysElapsed);
        }

        // H+5: HOD
        if ($businessDaysElapsed >= self::ESCALATION_H5_DAYS
            && $workOrder->escalation_h5_sent_at === null
        ) {
            $this->sendEscalation($workOrder, $department, 'h5', $businessDaysElapsed);
        }

        // H+6: DGM/GM
        if ($businessDaysElapsed >= self::ESCALATION_H6_DAYS
            && $workOrder->escalation_h6_sent_at === null
        ) {
            $this->sendEscalation($workOrder, $department, 'h6', $businessDaysElapsed);
        }
    }

    private function sendEscalation(
        WorkOrder $workOrder,
        Department $department,
        string $level,
        int $businessDaysElapsed,
    ): void {
        $recipients = $this->resolveRecipients($workOrder, $department, $level);

        if ($recipients->isEmpty()) {
            Log::critical('Escalation: 没有可通知的接收者', [
                'work_order_id' => $workOrder->id,
                'level' => $level,
                'department_id' => $department->id,
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            $recipient->notify(new WorkOrderDeadlineEscalated($workOrder, $level));
        }

        // Update escalation markers
        match ($level) {
            'h3' => $workOrder->update(['escalation_h3_sent_at' => now()]),
            'h5' => $workOrder->update(['escalation_h5_sent_at' => now()]),
            'h6' => $workOrder->update(['escalation_h6_sent_at' => now()]),
        };

        $workOrder->update(['is_escalated' => true]);

        Log::info('Escalation sent', [
            'work_order_id' => $workOrder->id,
            'level' => $level,
            'recipients' => $recipients->pluck('id')->toArray(),
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipients(
        WorkOrder $workOrder,
        Department $department,
        string $level,
    ): Collection {
        if ($level === 'h3') {
            // H+3: Team Leader first, then HOD fallback
            return $this->getTeamLeaderOrFallback($department);
        }

        // H+5 and H+6: HOD fallback chain
        return $this->getHodFallbackRecipients($department);
    }

    /**
     * @return Collection<int, User>
     */
    private function getTeamLeaderOrFallback(Department $department): Collection
    {
        // Check for Team Leader in department
        $teamLeaders = User::withoutGlobalScope('tenant')
            ->whereHas('employee', function ($q) use ($department) {
                $q->withoutGlobalScope('tenant')->where('department_id', $department->getKey());
            })
            ->whereHasRoles('team_leader')
            ->get();

        if (! $teamLeaders->isEmpty()) {
            return $teamLeaders;
        }

        // Fallback to HOD chain
        return $this->getHodFallbackRecipients($department);
    }

    /**
     * HOD fallback chain: hod_user_id → manager_user_id → all hod-role users in department → super admins
     *
     * @return Collection<int, User>
     */
    private function getHodFallbackRecipients(Department $department): Collection
    {
        // 1. Direct HOD
        $recipients = collect();
        if ($department->hod_user_id) {
            $user = User::find($department->hod_user_id);
            if ($user) {
                $recipients->push($user);
            }
        }

        // 2. Manager fallback
        if ($recipients->isEmpty() && $department->manager_user_id) {
            $user = User::find($department->manager_user_id);
            if ($user) {
                $recipients->push($user);
            }
        }

        // 3. All users with hod role in department
        if ($recipients->isEmpty()) {
            $recipients = User::withoutGlobalScope('tenant')
                ->whereHas('employee', function ($q) use ($department) {
                    $q->withoutGlobalScope('tenant')->where('department_id', $department->getKey());
                })
                ->whereHasRoles('hod')
                ->get();
        }

        // 4. Super admin fallback + log critical
        if ($recipients->isEmpty()) {
            $recipients = User::withoutGlobalScope('tenant')
                ->where('is_super_admin', true)
                ->where('tenant_id', $department->tenant_id)
                ->get();

            Log::critical('Escalation HOD fallback: 没有合适的接收者，发送给 super admin', [
                'department_id' => $department->id,
                'tenant_id' => $department->tenant_id,
            ]);
        }

        return $recipients->unique('id');
    }

    private function calculateBusinessDaysElapsed(
        ?CarbonInterface $assignedAt,
        CarbonInterface $now,
        WorkOrderCategory $category,
    ): int {
        if ($assignedAt === null) {
            return 0;
        }

        $holidayLookup = array_flip(config('holidays', []));
        $businessDays = 0;
        $cursor = clone $assignedAt;
        $maxIterations = 365 * 5; // Max 5 years of days

        while ($cursor < $now && $maxIterations > 0) {
            $cursor->addDay();
            $maxIterations--;
            // Skip weekends
            if ($cursor->isWeekend()) {
                continue;
            }
            // Skip holidays
            $dateStr = $cursor->toDateString();
            $formatStr = $cursor->format('m-d');
            if (isset($holidayLookup[$dateStr]) || isset($holidayLookup[$formatStr])) {
                continue;
            }
            $businessDays++;
        }

        return $businessDays;
    }
}
