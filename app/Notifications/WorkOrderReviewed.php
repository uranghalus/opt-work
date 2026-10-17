<?php

namespace App\Notifications;

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
        return ['database', 'broadcast'];
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
}
