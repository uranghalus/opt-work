<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\ExtendRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke rantai approver tahap berjalan (Team Leader atau HOD) saat
 * pengajuan extend deadline WO telat dibuat (FR-2.3). WhatsApp outbound
 * menyusul bersama fiturnya (ADR-0009: "event lain menyusul bersama
 * fiturnya") — kombinasi dari branch feature/extend-deadline.
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
        return ['database', 'broadcast', WahaWhatsAppChannel::class];
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

    /**
     * Outbox WhatsApp (ADR-0009): permohonan extend menunggu keputusan
     * approver tahap berjalan.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $workOrder = $this->extendRequest->workOrder;

        $lines = [
            '⏳ *PERMOHONAN EXTEND DEADLINE*',
            '',
            '*No:* '.($workOrder?->nomor_wo ?? '-'),
            '*Judul:* '.($workOrder?->title ?? '-'),
            '*Departemen:* '.($workOrder?->targetDepartment?->nama_department ?? '-'),
            '*Perpanjangan:* +'.$this->extendRequest->days.' hari kerja',
            '*Alasan:* '.str((string) $this->extendRequest->reason)->limit(120),
        ];

        $lines[] = '';
        $lines[] = 'Setujui / Tolak:';
        $lines[] = route('work-orders.show', [
            'tenant' => $workOrder?->tenant?->code,
            'workOrder' => $workOrder?->getKey(),
        ]);

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.extend_requested';
    }
}
