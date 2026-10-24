<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Ke requester (R11): permintaannya disetujui approver department-nya dan
 * dirilis ke department tujuan. WA outbound wajib (keputusan user, OQ-7).
 */
class WorkOrderRequestApproved extends Notification
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
            'type' => 'work_order.request_approved',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'message' => sprintf(
                'Permintaan WO %s ("%s") disetujui — diteruskan ke %s.',
                $this->workOrder->nomor_wo,
                $this->workOrder->title,
                $this->workOrder->targetDepartment?->nama_department ?? '-',
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toWhatsApp(object $notifiable): string
    {
        $lines = [
            '✅ *PERMINTAAN WO DISETUJUI*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
            '*Diteruskan ke:* '.($this->workOrder->targetDepartment?->nama_department ?? '-'),
            '',
            'Detail:',
            $this->detailUrl(),
        ];

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.request_approved';
    }

    private function detailUrl(): string
    {
        return route('work-orders.show', [
            'tenant' => $this->workOrder->tenant?->code,
            'workOrder' => $this->workOrder->getKey(),
        ]);
    }
}
