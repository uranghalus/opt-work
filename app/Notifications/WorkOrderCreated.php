<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class WorkOrderCreated extends Notification
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
        return [
            'type' => 'work_order.created',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'target_department_id' => $this->workOrder->target_department_id,
            'message' => sprintf(
                'WO baru "%s" (%s) menunggu review Anda.',
                $this->workOrder->title,
                $this->workOrder->nomor_wo,
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (ADR-0009): permintaan review ke HOD. Template khusus
     * event ini — tanpa instruksi balasan perintah (command-bot = future work).
     */
    public function toWhatsApp(object $notifiable): string
    {
        $lines = [
            '🎫 *WORK ORDER BARU — BUTUH REVIEW*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
            '*Kategori:* '.$this->workOrder->category->label(),
            '*Departemen:* '.($this->workOrder->targetDepartment?->nama_department ?? '-'),
            '*Pemohon:* '.($this->workOrder->requester?->name ?? '-'),
            '',
            'Detail dan review via aplikasi:',
            $this->detailUrl(),
        ];

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.created';
    }

    private function detailUrl(): string
    {
        return route('work-orders.show', [
            'tenant' => $this->workOrder->tenant?->code,
            'workOrder' => $this->workOrder->getKey(),
        ]);
    }
}
