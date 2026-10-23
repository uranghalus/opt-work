<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkPlanning;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke pengaju (HOD) setelah DGM/GM memutuskan Extend Work
 * Schedule — disetujui (jadwal resmi bergeser) atau ditolak.
 */
class WorkScheduleExtendDecided extends Notification
{
    use Queueable;

    public function __construct(
        public WorkPlanning $planning,
        public bool $approved,
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
        $workOrder = $this->planning->workOrder;

        return [
            'type' => 'work_planning.extend_decided',
            'work_planning_id' => $this->planning->getKey(),
            'work_order_id' => $workOrder->getKey(),
            'nomor_wo' => $workOrder->nomor_wo,
            'title' => $workOrder->title,
            'approved' => $this->approved,
            'tgl_jadwal' => $this->planning->tgl_jadwal?->toDateString(),
            'message' => $this->approved
                ? sprintf(
                    'Extend jadwal WO %s disetujui — jadwal baru %s.',
                    $workOrder->nomor_wo,
                    $this->planning->tgl_jadwal?->toDateString() ?? '-',
                )
                : sprintf(
                    'Extend jadwal WO %s ditolak — jadwal tetap %s.',
                    $workOrder->nomor_wo,
                    $this->planning->tgl_jadwal?->toDateString() ?? '-',
                ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (ADR-0009: event menyusul bersama fiturnya).
     */
    public function toWhatsApp(object $notifiable): string
    {
        $workOrder = $this->planning->workOrder;

        $lines = [
            $this->approved ? '🎉 *EXTEND JADWAL WO DISETUJUI*' : '⛔ *EXTEND JADWAL WO DITOLAK*',
            '',
            '*No:* '.($workOrder?->nomor_wo ?? '-'),
            '*Judul:* '.($workOrder?->title ?? '-'),
            '*Jadwal berlaku:* '.($this->planning->tgl_jadwal?->toDateString() ?? '-'),
        ];

        if (filled($this->planning->extend_approval_notes)) {
            $lines[] = '*Catatan:* '.str((string) $this->planning->extend_approval_notes)->limit(120);
        }

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
        return 'work_planning.extend_decided';
    }
}
