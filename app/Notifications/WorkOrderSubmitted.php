<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the department HOD chain when a karyawan pelaksana submits
 * execution results (FR-1.7) — verifying that result is the HOD's next
 * action (FR-1.8).
 */
class WorkOrderSubmitted extends Notification
{
    use Queueable;

    public function __construct(public WorkOrder $workOrder) {}

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
        return [
            'type' => 'work_order.submitted',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'target_department_id' => $this->workOrder->target_department_id,
            'message' => sprintf(
                'Pelaksana menyerahkan hasil WO %s ("%s") — menunggu verifikasi HOD.',
                $this->workOrder->nomor_wo,
                $this->workOrder->title,
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (ADR-0009): penyerahan hasil pelaksanaan — pemicu
     * verifikasi HOD.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $lines = [
            '📝 *HASIL WO DISERAHKAN*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
            '*Departemen:* '.($this->workOrder->targetDepartment?->nama_department ?? '-'),
        ];

        if (filled($this->workOrder->result_notes)) {
            $lines[] = '*Catatan hasil:* '.str($this->workOrder->result_notes)->limit(120);
        }

        $lines[] = '';
        $lines[] = 'Verifikasi:';
        $lines[] = route('work-orders.show', [
            'tenant' => $this->workOrder->tenant?->code,
            'workOrder' => $this->workOrder->getKey(),
        ]);

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.submitted';
    }
}
