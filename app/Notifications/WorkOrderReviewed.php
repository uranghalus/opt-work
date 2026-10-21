<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class WorkOrderReviewed extends Notification
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
        $scheduled = $this->workOrder->scheduled_date?->isoFormat('D MMMM Y');

        return [
            'type' => 'work_order.reviewed',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'decision' => $this->workOrder->hod_action,
            'target_department_id' => $this->workOrder->target_department_id,
            'message' => $scheduled === null
                ? sprintf(
                    'WO %s ("%s") disetujui untuk dieksekusi langsung.',
                    $this->workOrder->nomor_wo,
                    $this->workOrder->title,
                )
                : sprintf(
                    'WO %s ("%s") dijadwalkan pada %s.',
                    $this->workOrder->nomor_wo,
                    $this->workOrder->title,
                    $scheduled,
                ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (ADR-0009): hasil review HOD ke pemohon. Keputusan diambil
     * dari state WO (bukan teks hardcode) — sama dengan pesan in-app.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $scheduled = $this->workOrder->scheduled_date?->isoFormat('D MMMM Y');
        $decision = $scheduled === null
            ? 'Disetujui — eksekusi langsung'
            : 'Dijadwalkan pada '.$scheduled;

        $lines = [
            '✅ *KEPUTUSAN REVIEW WORK ORDER*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
            '*Keputusan:* '.$decision,
        ];

        if (filled($this->workOrder->hod_notes)) {
            $lines[] = '*Catatan HOD:* '.$this->workOrder->hod_notes;
        }

        $lines[] = '';
        $lines[] = 'Detail:';
        $lines[] = route('work-orders.show', [
            'tenant' => $this->workOrder->tenant?->code,
            'workOrder' => $this->workOrder->getKey(),
        ]);

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.reviewed';
    }
}
