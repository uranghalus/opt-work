<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to each assigned karyawan pelaksana through their linked akun —
 * the in-app assign notification opti-work2 never implemented.
 */
class WorkOrderAssigned extends Notification
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
        $deadline = $this->workOrder->deadline_date?->isoFormat('D MMMM Y');

        return [
            'type' => 'work_order.assigned',
            'work_order_id' => $this->workOrder->getKey(),
            'nomor_wo' => $this->workOrder->nomor_wo,
            'title' => $this->workOrder->title,
            'category' => $this->workOrder->category->value,
            'target_department_id' => $this->workOrder->target_department_id,
            'message' => sprintf(
                'Anda ditugaskan pada WO %s ("%s")%s.',
                $this->workOrder->nomor_wo,
                $this->workOrder->title,
                $deadline === null ? '' : ' — deadline '.$deadline,
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (ADR-0009): penugasan ke karyawan pelaksana — memuat
     * deadline SLA (tanggal assign) karena itu aksi yang diharapkan dari penerima.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $deadline = $this->workOrder->deadline_date?->isoFormat('D MMMM Y');

        $lines = [
            '👥 *ANDA DITUGASKAN WORK ORDER*',
            '',
            '*No:* '.$this->workOrder->nomor_wo,
            '*Judul:* '.$this->workOrder->title,
            '*Departemen:* '.($this->workOrder->targetDepartment?->nama_department ?? '-'),
        ];

        if ($deadline !== null) {
            $lines[] = '*Deadline:* '.$deadline;
        }

        if (filled($this->workOrder->assignment_notes)) {
            $lines[] = '*Catatan:* '.$this->workOrder->assignment_notes;
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
        return 'work_order.assigned';
    }
}
