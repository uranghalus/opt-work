<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the assigned pelaksana and the requester when the HOD approves
 * their submitted result and closes the work order (FR-1.8).
 */
class WorkOrderVerified extends Notification
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
            'type' => 'work_order.verified',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'target_department_id' => $this->workOrder->target_department_id,
            'message' => sprintf(
                'WO %s ("%s") terverifikasi dan ditutup. Terima kasih atas penyelesaiannya.',
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
     * Outbox WhatsApp (ADR-0009): penutupan WO setelah hasil terverifikasi.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $lines = [
            '✅ *WO TERVERIFIKASI*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
            '*Departemen:* '.($this->workOrder->targetDepartment?->nama_department ?? '-'),
            '',
            'Hasil pekerjaan telah diverifikasi HOD dan WO ditutup.',
            '',
            'Detail:',
            route('work-orders.show', [
                'tenant' => $this->workOrder->tenant?->code,
                'workOrder' => $this->workOrder->getKey(),
            ]),
        ];

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.verified';
    }
}
