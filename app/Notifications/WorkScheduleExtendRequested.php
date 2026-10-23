<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkPlanning;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke rantai DGM/GM tenant saat HOD mengajukan Extend Work
 * Schedule (FR-2.4) — jadwal WO terjadwal ingin bergeser.
 */
class WorkScheduleExtendRequested extends Notification
{
    use Queueable;

    public function __construct(public WorkPlanning $planning) {}

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
            'type' => 'work_planning.extend_requested',
            'work_planning_id' => $this->planning->getKey(),
            'work_order_id' => $workOrder->getKey(),
            'nomor_wo' => $workOrder->nomor_wo,
            'title' => $workOrder->title,
            'tgl_jadwal' => $this->planning->tgl_jadwal?->toDateString(),
            'proposed_tgl_jadwal' => $this->planning->proposed_tgl_jadwal?->toDateString(),
            'message' => sprintf(
                'Extend jadwal WO %s ("%s"): %s → %s menunggu approval DGM/GM.',
                $workOrder->nomor_wo,
                $workOrder->title,
                $this->planning->tgl_jadwal?->toDateString() ?? '-',
                $this->planning->proposed_tgl_jadwal?->toDateString() ?? '-',
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
            '🗓 *PENGAJUAN EXTEND JADWAL WO*',
            '',
            '*No:* '.($workOrder?->nomor_wo ?? '-'),
            '*Judul:* '.($workOrder?->title ?? '-'),
            '*Jadwal sekarang:* '.($this->planning->tgl_jadwal?->toDateString() ?? '-'),
            '*Diajukan ke:* '.($this->planning->proposed_tgl_jadwal?->toDateString() ?? '-'),
            '*Alasan:* '.str((string) $this->planning->extend_reason)->limit(120),
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
        return 'work_planning.extend_requested';
    }
}
