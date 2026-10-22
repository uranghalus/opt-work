<?php

namespace App\Notifications;

use App\Models\ExtendRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke pengaju + pelaksana + requester WO saat pengajuan extend
 * deadline ditolak approver (FR-2.3). Tanpa WhatsApp — ADR-0009 slice 1
 * tepat 4 event WO.
 */
class ExtendRequestRejected extends Notification
{
    use Queueable;

    public function __construct(public ExtendRequest $extendRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $workOrder = $this->extendRequest->workOrder;

        return [
            'type' => 'work_order.extend_rejected',
            'extend_request_id' => $this->extendRequest->getKey(),
            'work_order_id' => $workOrder->getKey(),
            'nomor_wo' => $workOrder->nomor_wo,
            'title' => $workOrder->title,
            'days' => $this->extendRequest->days,
            'reason' => $this->extendRequest->rejected_reason,
            'message' => sprintf(
                'Pengajuan extend %d hari untuk WO %s ditolak.',
                $this->extendRequest->days,
                $workOrder->nomor_wo,
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
