<?php

namespace App\Notifications;

use App\Models\ExtendRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke rantai approver tahap berjalan (Team Leader atau HOD) saat
 * pengajuan extend deadline WO telat dibuat (FR-2.3). Tanpa WhatsApp —
 * ADR-0009 slice 1 tepat 4 event WO (created/reviewed/assigned/escalated).
 */
class ExtendRequestSubmitted extends Notification
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
            'type' => 'work_order.extend_requested',
            'extend_request_id' => $this->extendRequest->getKey(),
            'work_order_id' => $workOrder->getKey(),
            'nomor_wo' => $workOrder->nomor_wo,
            'title' => $workOrder->title,
            'days' => $this->extendRequest->days,
            'message' => sprintf(
                'Pengajuan extend %d hari untuk WO %s ("%s") menunggu persetujuan.',
                $this->extendRequest->days,
                $workOrder->nomor_wo,
                $workOrder->title,
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
