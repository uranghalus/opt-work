<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\ExtendRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke pengaju + pelaksana + requester WO saat pengajuan extend
 * deadline disetujui dan deadline resmi bergeser (FR-2.3). WhatsApp
 * outbound menyusul bersama fiturnya (ADR-0009) — kombinasi dari branch
 * feature/extend-deadline.
 */
class ExtendRequestApproved extends Notification
{
    use Queueable;

    public function __construct(public ExtendRequest $extendRequest) {}

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
        $workOrder = $this->extendRequest->workOrder;

        return [
            'type' => 'work_order.extend_approved',
            'extend_request_id' => $this->extendRequest->getKey(),
            'work_order_id' => $workOrder->getKey(),
            'nomor_wo' => $workOrder->nomor_wo,
            'title' => $workOrder->title,
            'days' => $this->extendRequest->days,
            'new_deadline' => $workOrder->deadline_date?->toDateString(),
            'message' => sprintf(
                'Extend %d hari untuk WO %s disetujui — deadline baru %s.',
                $this->extendRequest->days,
                $workOrder->nomor_wo,
                $workOrder->deadline_date?->toDateString() ?? '-',
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (ADR-0009): extend disetujui, deadline resmi baru.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $workOrder = $this->extendRequest->workOrder;

        $lines = [
            '🎉 *EXTEND DEADLINE DISETUJUI*',
            '',
            '*No:* '.($workOrder?->nomor_wo ?? '-'),
            '*Judul:* '.($workOrder?->title ?? '-'),
            '*Perpanjangan:* +'.$this->extendRequest->days.' hari kerja',
            '*Deadline baru:* '.($workOrder->deadline_date?->toDateString() ?? '-'),
        ];

        $lines[] = '';
        $lines[] = 'Detail WO:';
        $lines[] = route('work-orders.show', [
            'tenant' => $workOrder?->tenant?->code,
            'workOrder' => $workOrder?->getKey(),
        ]);

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.extend_approved';
    }
}
