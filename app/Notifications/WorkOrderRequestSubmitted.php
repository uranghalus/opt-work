<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke rantai approver department PENGAJU saat permintaan WO
 * lintas-department masuk gerbang (R2/R11) — atau saat resubmit. Dept
 * tujuan TIDAK menerima apa pun sampai approve (R8). WA outbound wajib
 * untuk event ini (keputusan user, OQ-7 — pola ADR-0009).
 */
class WorkOrderRequestSubmitted extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $duplicateNumbers  nomor WO kandidat duplikat
     *                                                (penanda, keputusan tetap
     *                                                manusia — OQ-4b)
     */
    public function __construct(
        public WorkOrder $workOrder,
        public array $duplicateNumbers = [],
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
            'type' => 'work_order.request_submitted',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'requester_name' => $this->workOrder->requester?->name,
            'duplicate_numbers' => $this->duplicateNumbers,
            'message' => sprintf(
                'Permintaan WO baru "%s" (%s) dari %s menunggu persetujuan Anda.%s',
                $this->workOrder->title,
                $this->workOrder->nomor_wo,
                $this->workOrder->requester?->name ?? '-',
                $this->duplicateNumbers === []
                    ? ''
                    : ' Kemungkinan duplikat: '.implode(', ', $this->duplicateNumbers).'.',
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
            '📮 *PERMINTAAN WO — BUTUH PERSETUJUAN*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
            '*Pemohon:* '.($this->workOrder->requester?->name ?? '-'),
            '*Dituju ke:* '.($this->workOrder->targetDepartment?->nama_department ?? '-'),
            '*Kategori:* '.$this->workOrder->category->label(),
        ];

        if ($this->duplicateNumbers !== []) {
            $lines[] = '⚠️ *Kemungkinan duplikat:* '.implode(', ', $this->duplicateNumbers);
        }

        $lines[] = '';
        $lines[] = 'Setujui / tolak via aplikasi:';
        $lines[] = $this->detailUrl();

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.request_submitted';
    }

    private function detailUrl(): string
    {
        return route('work-orders.show', [
            'tenant' => $this->workOrder->tenant?->code,
            'workOrder' => $this->workOrder->getKey(),
        ]);
    }
}
