<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderDeadlineEscalated;
use App\WorkOrderStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

#[Signature('app:check-deadline-escalation')]
#[Description('Cek Work Order terlambat dan kirim eskalasi berjenjang: H+3→Team Leader, H+5→HOD, H+6→DGM/GM (H+N = hari kalender melewati deadline — PRD FR-2.2)')]
class CheckDeadlineEscalation extends Command
{
    private const ESCALATION_H3_DAYS = 3;

    private const ESCALATION_H5_DAYS = 5;

    private const ESCALATION_H6_DAYS = 6;

    public function handle(): int
    {
        $activeStatuses = [
            WorkOrderStatus::Assigned->value,
            WorkOrderStatus::InProgress->value,
            WorkOrderStatus::PendingVerify->value,
            WorkOrderStatus::Revision->value,
        ];

        $overdueWOs = WorkOrder::withoutGlobalScope('tenant')
            ->with('targetDepartment')
            ->where('deadline_date', '<', now())
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('assigned_at')
            ->whereNotNull('deadline_date')
            ->get();

        if ($overdueWOs->isEmpty()) {
            $this->info('Tidak ada Work Order terlambat.');

            return Command::SUCCESS;
        }

        $this->info("Ditemukan {$overdueWOs->count()} WO terlambat, memproses eskalasi...");

        foreach ($overdueWOs as $workOrder) {
            $this->processEscalation($workOrder);
        }

        $this->info('Pemeriksaan eskalasi selesai.');

        return Command::SUCCESS;
    }

    private function processEscalation(WorkOrder $workOrder): void
    {
        $department = $workOrder->targetDepartment
            ?? Department::withoutGlobalScope('tenant')->find($workOrder->target_department_id);

        if (! $department) {
            Log::error('Escalation: departemen tidak ditemukan', ['work_order_id' => $workOrder->id]);

            return;
        }

        // PRD FR-2.2: "Hari telat ke-N" = hari kalender MELEWATI deadline —
        // bukan hari kerja sejak assign. Menghitung dari assigned_at membuat
        // WO normal (SLA 6 hari kerja) menembak ketiga level sekaligus pada
        // hari telat pertamanya (≥7 hari kerja sejak assign). Konvensi sama
        // dengan DeadlineChip ("Telat H+N") di WorkOrderController.
        // (deadline -> today: diffInDays bertanda di Carbon 3, positif di sini.)
        $daysLate = (int) $workOrder->deadline_date->startOfDay()->diffInDays(
            Carbon::today()->startOfDay(),
        );

        // H+3: Team Leader (if exists) → HOD
        if ($daysLate >= self::ESCALATION_H3_DAYS
            && $workOrder->escalation_h3_sent_at === null
        ) {
            $this->sendEscalation($workOrder, $department, 'h3', $daysLate);
        }

        // H+5: HOD
        if ($daysLate >= self::ESCALATION_H5_DAYS
            && $workOrder->escalation_h5_sent_at === null
        ) {
            $this->sendEscalation($workOrder, $department, 'h5', $daysLate);
        }

        // H+6: DGM/GM
        if ($daysLate >= self::ESCALATION_H6_DAYS
            && $workOrder->escalation_h6_sent_at === null
        ) {
            $this->sendEscalation($workOrder, $department, 'h6', $daysLate);
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
            Log::critical('Escalation: tidak ada penerima yang bisa diberitahu', [
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

        if ($level === 'h6') {
            // H+6: DGM/GM in the WO's tenant first, then the HOD chain
            return $this->getManagementOrFallbackRecipients($workOrder, $department);
        }

        // H+5: HOD fallback chain
        return $this->getHodFallbackRecipients($department);
    }

    /**
     * H+6 recipients: users holding general_manager/deputy_general_manager
     * roles in the work order's tenant (inclusive — mirroring the hod-role
     * fallback below); empty → the HOD fallback chain.
     *
     * @return Collection<int, User>
     */
    private function getManagementOrFallbackRecipients(WorkOrder $workOrder, Department $department): Collection
    {
        // NB: whereHas('roles'), never whereHasRoles() — that name degrades to a
        // dynamic `has_roles` where: empty on sqlite, QueryException on MySQL.
        $management = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $workOrder->tenant_id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['general_manager', 'deputy_general_manager']))
            ->get();

        if (! $management->isEmpty()) {
            return $management;
        }

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
            ->whereHas('roles', fn ($q) => $q->where('name', 'team_leader'))
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
                ->whereHas('roles', fn ($q) => $q->where('name', 'hod'))
                ->get();
        }

        // 4. Super admin fallback + log critical
        if ($recipients->isEmpty()) {
            $recipients = User::withoutGlobalScope('tenant')
                ->where('is_super_admin', true)
                ->where('tenant_id', $department->tenant_id)
                ->get();

            Log::critical('Escalation HOD fallback: tidak ada penerima yang cocok, mengirim ke super admin', [
                'department_id' => $department->id,
                'tenant_id' => $department->tenant_id,
            ]);
        }

        return $recipients->unique('id');
    }
}
