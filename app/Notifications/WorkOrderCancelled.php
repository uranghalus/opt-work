<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Ke requester (R12): WO-nya dibatalkan — oleh dirinya sendiri, approver
 * department pengaju, atau reviewer department tujuan sebagai DUPLIKAT
 * (kasus isi WO sama dari dua department berbeda; nomor WO duplikatnya
 * ikut dilaporkan bila ada).
 */
class WorkOrderCancelled extends Notification
{
    use Queueable;

    public function __construct(
        public WorkOrder $workOrder,
        public ?string $reason = null,
        public ?string $duplicateOfNomor = null,
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
            'type' => 'work_order.cancelled',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'reason' => $this->reason,
            'duplicate_of' => $this->duplicateOfNomor,
            'message' => sprintf(
                'WO %s ("%s") dibatalkan.%s%s',
                $this->workOrder->nomor_wo,
                $this->workOrder->title,
                $this->duplicateOfNomor !== null ? ' Duplikat dari '.$this->duplicateOfNomor.'.' : '',
                filled($this->reason) ? ' Alasan: '.$this->reason : '',
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
            '🚫 *WORK ORDER DIBATALKAN*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
        ];

        if ($this->duplicateOfNomor !== null) {
            $lines[] = '*Duplikat dari:* '.$this->duplicateOfNomor;
        }

        if (filled($this->reason)) {
            $lines[] = '*Alasan:* '.$this->reason;
        }

        $lines[] = '';
        $lines[] = 'Detail:';
        $lines[] = $this->detailUrl();

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.cancelled';
    }

    private function detailUrl(): string
    {
        return route('work-orders.show', [
            'tenant' => $this->workOrder->tenant?->code,
            'workOrder' => $this->workOrder->getKey(),
        ]);
    }
}
