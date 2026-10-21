<?php

namespace Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi probe dengan satu saluran broadcast — dipakai untuk
 * mereproduksi cacat pre-existing: notify() tanpa tenant current melempar
 * BroadcastNotificationCreated ke queue tanpa tenantId di payload.
 */
class BroadcastProbeNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['probe' => true];
    }
}
