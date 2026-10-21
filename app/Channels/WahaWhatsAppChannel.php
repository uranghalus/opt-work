<?php

namespace App\Channels;

use App\Jobs\SendWaMessageJob;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaMessage;
use App\Models\WorkOrder;
use App\Services\WahaClient;
use App\WaMessageStatus;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Channel WhatsApp via WAHA (ADR-0009). Selalu terdaftar di via(); menjadi
 * no-op mutlak saat WAHA_URL kosong — nol baris outbox, nol HTTP (lini dev
 * tanpa gateway tidak berubah perilakunya).
 *
 * Satu kirim = satu baris `wa_messages` (outbox) + dispatch job after-commit.
 * Baris ditulis di dalam transaksi bisnis yang sama dengan notify(): rollback
 * membatalkan pesanan kirim sebelum sempat dieksekusi.
 *
 * Kontrak notifikasi: `toWhatsApp(object $notifiable): string` wajib ada;
 * opsional `waEvent(): string` (default = snake case nama kelas) dan properti
 * publik `$workOrder` untuk mengisi relasi outbox.
 */
class WahaWhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! WahaClient::configured() || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $text = (string) $notification->toWhatsApp($notifiable);
        $phone = $this->resolvePhone($notifiable);
        $chatId = $phone === null ? null : WahaClient::normalizeChatId($phone);

        $workOrder = $notifiable && isset($notification->workOrder) && $notification->workOrder instanceof WorkOrder
            ? $notification->workOrder
            : null;

        $message = WaMessage::create([
            'tenant_id' => $workOrder?->tenant_id
                ?? Tenant::current()?->id
                ?? ($notifiable instanceof User ? $notifiable->tenant_id : null),
            'work_order_id' => $workOrder?->getKey(),
            'recipient_user_id' => $notifiable instanceof User ? $notifiable->getKey() : null,
            'event' => $this->eventName($notification),
            'phone' => $phone,
            'chat_id' => $chatId,
            'payload' => $text,
            'status' => $chatId === null ? WaMessageStatus::Skipped : WaMessageStatus::Pending,
            'last_error' => $chatId === null
                ? 'Nomor WhatsApp penerima kosong atau tidak valid.'
                : null,
        ]);

        if ($message->status === WaMessageStatus::Pending) {
            SendWaMessageJob::dispatch($message->getKey());
        }
    }

    private function resolvePhone(object $notifiable): ?string
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $phone = $notifiable->routeNotificationFor('WahaWhatsApp');

        return filled($phone) ? (string) $phone : null;
    }

    private function eventName(Notification $notification): string
    {
        if (method_exists($notification, 'waEvent')) {
            return (string) $notification->waEvent();
        }

        return Str::snake(class_basename($notification));
    }
}
