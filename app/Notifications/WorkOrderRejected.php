<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the assigned pelaksana and the requester when the HOD sends the
 * submitted result back for revision, with the HOD's notes attached
 * (FR-1.8).
 */
class WorkOrderRejected extends Notification
{
    use Queueable;

    public function __construct(
        public WorkOrder $workOrder,
        public string $notes,
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
            'type' => 'work_order.rejected',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'target_department_id' => $this->workOrder->target_department_id,
            'message' => sprintf(
                'Hasil WO %s ("%s") dikembalikan untuk revisi. Catatan HOD: %s',
                $this->workOrder->nomor_wo,
                $this->workOrder->title,
                $this->notes,
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (ADR-0009): hasil dikembalikan untuk revisi —
     * memuat catatan HOD agar pelaksana bisa langsung mengerjakan ulang.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $lines = [
            '🔁 *HASIL WO DITOLAK — REVISI*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
            '*Departemen:* '.($this->workOrder->targetDepartment?->nama_department ?? '-'),
            '*Catatan HOD:* '.$this->notes,
            '',
            'Kerjakan ulang lalu serahkan hasil revisi:',
            route('work-orders.show', [
                'tenant' => $this->workOrder->tenant?->code,
                'workOrder' => $this->workOrder->getKey(),
            ]),
        ];

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'work_order.rejected';
    }
}
