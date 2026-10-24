<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Ke requester (R4/R11): permintaannya DITOLAK approver department-nya
 * dengan alasan wajib — WO tidak pernah sampai ke department tujuan.
 * Requester bisa merevisi lalu mengajukan ulang, atau membatalkan.
 */
class WorkOrderRequestRejected extends Notification
{
    use Queueable;

    public function __construct(
        public WorkOrder $workOrder,
        public string $reason,
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
        return [
            'type' => 'work_order.request_rejected',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'reason' => $this->reason,
            'message' => sprintf(
                'Permintaan WO %s ("%s") ditolak department Anda. Alasan: %s',
                $this->workOrder->nomor_wo,
                $this->workOrder->title,
                $this->reason,
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
            '❌ *PERMINTAAN WO DITOLAK*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
            '*Alasan:* '.$this->reason,
            '',
            'Revisi lalu ajukan ulang (atau batalkan) via aplikasi:',
            $this->detailUrl(),
        ];

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.request_rejected';
    }

    private function detailUrl(): string
    {
        return route('work-orders.show', [
            'tenant' => $this->workOrder->tenant?->code,
            'workOrder' => $this->workOrder->getKey(),
        ]);
    }
}
