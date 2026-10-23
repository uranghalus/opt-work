<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a work order deadline is approaching or has passed.
 * Staged escalation: H+3 → Team Leader, H+5 → HOD, H+6 → DGM/GM.
 */
class WorkOrderDeadlineEscalated extends Notification
{
    use Queueable;

    public function __construct(
        public WorkOrder $workOrder,
        public string $level, // 'h3', 'h5', 'h6'
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WahaWhatsAppChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $levelLabel = match ($this->level) {
            'h3' => 'Team Leader',
            'h5' => 'HOD',
            'h6' => 'DGM/GM',
            default => 'Unknown',
        };

        $deadline = $this->workOrder->deadline_date?->isoFormat('D MMMM Y');

        return [
            'type' => 'work_order.escalated',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'escalation_level' => $this->level,
            'escalation_target' => $levelLabel,
            'target_department_id' => $this->workOrder->target_department_id,
            'message' => sprintf(
                'WO %s ("%s") melewati deadline — segera tindak lanjuti. Eskalasi ke %s.%s',
                $this->workOrder->nomor_wo,
                $this->workOrder->title,
                $levelLabel,
                $deadline === null ? '' : ' Deadline: '.$deadline,
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (ADR-0009): eskalasi keterlambatan berjenjang.
     * Template sendiri dalam Bahasa Indonesia — payload in-app (toArray)
     * ikut Bahasa Indonesia sejak 2026-10-22.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $levelCode = match ($this->level) {
            'h3' => 'H+3',
            'h5' => 'H+5',
            'h6' => 'H+6',
            default => strtoupper($this->level),
        };
        $levelLabel = match ($this->level) {
            'h3' => 'Team Leader',
            'h5' => 'HOD',
            'h6' => 'DGM/GM',
            default => '-',
        };
        $deadline = $this->workOrder->deadline_date?->isoFormat('D MMMM Y');

        // Department di-fetch tanpa scope tenant: notifikasi eskalasi dikirim
        // dari command konsol tanpa tenant current (lihat CheckDeadlineEscalation).
        $department = $this->workOrder->targetDepartment()
            ->withoutGlobalScope('tenant')
            ->first();

        $lines = [
            '⚠️ *ESKALASI KETERLAMBATAN '.$levelCode.'*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
            '*Eskalasi ke:* '.$levelLabel,
            '*Departemen:* '.($department?->nama_department ?? '-'),
        ];

        if ($deadline !== null) {
            $lines[] = '*Deadline:* '.$deadline;
        }

        $lines[] = '';
        $lines[] = 'Work Order melewati tenggat — segera tindak lanjuti:';
        $lines[] = route('work-orders.show', [
            'tenant' => $this->workOrder->tenant?->code,
            'workOrder' => $this->workOrder->getKey(),
        ]);

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.escalated';
    }
}
