<?php

namespace App\Channels;

use App\Jobs\SendWaMessageJob;
use App\Models\Tenant;
use App\Models\TenantWaSession;
use App\Models\User;
use App\Models\WaMessage;
use App\Models\WorkOrder;
use App\Services\WahaClient;
use App\WaMessageStatus;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Channel WhatsApp via WAHA (ADR-0009, amendmen slice 2: sesi/nomor per
 * tenant). Selalu terdaftar di via(); menjadi no-op mutlak saat WAHA_URL
 * kosong — nol baris outbox, nol HTTP (lini dev tanpa gateway tidak berubah
 * perilakunya).
 *
 * Satu kirim = satu baris `wa_messages` (outbox) + dispatch job after-commit.
 * Baris ditulis di dalam transaksi bisnis yang sama dengan notify(): rollback
 * membatalkan pesanan kirim sebelum sempat dieksekusi.
 *
 * Urutan guard isolasi tenant (resolusi sesi HANYA lewat
 * TenantWaSession::activeFor — tanpa fallback apa pun):
 *  1. WAHA_URL kosong → keluar tanpa baris (nol baris, nol HTTP).
 *  2. Tanpa nomor penerima → baris `skipped` (final; retry tak memperbaiki).
 *  3. Tanpa tenant → baris `failed`, tanpa dispatch.
 *  4. Tenant tanpa sesi aktif → baris `failed` + alasan status, tanpa
 *     dispatch — TIDAK PERNAH jatuh ke nomor global/sesi tenant lain.
 *  5. Sesi aktif → baris `pending` + snapshot `session_name`, dispatch job.
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

        $tenantId = $workOrder?->tenant_id
            ?? Tenant::current()?->id
            ?? ($notifiable instanceof User ? $notifiable->tenant_id : null);

        // null kecuali sesi tenant ini benar-benar aktif (working) — satu-satunya
        // jalur menjadi sesi pengirim, tanpa fallback ke tenant/nomor lain.
        $session = TenantWaSession::activeFor($tenantId);

        if ($chatId === null) {
            $status = WaMessageStatus::Skipped;
            $lastError = 'Nomor WhatsApp penerima kosong atau tidak valid.';
        } elseif ($tenantId === null) {
            $status = WaMessageStatus::Failed;
            $lastError = 'Pesanan tanpa tenant — tidak ada sesi nomor pengirim.';
        } elseif ($session === null) {
            $status = WaMessageStatus::Failed;
            $lastError = $this->noSessionReason($tenantId);
        } else {
            $status = WaMessageStatus::Pending;
            $lastError = null;
        }

        $message = WaMessage::create([
            'tenant_id' => $tenantId,
            'work_order_id' => $workOrder?->getKey(),
            'recipient_user_id' => $notifiable instanceof User ? $notifiable->getKey() : null,
            'event' => $this->eventName($notification),
            'phone' => $phone,
            'chat_id' => $chatId,
            'session_name' => $session?->session_name,
            'payload' => $text,
            'status' => $status,
            'last_error' => $lastError,
        ]);

        if ($message->status === WaMessageStatus::Pending) {
            SendWaMessageJob::dispatch($message->getKey());
        }
    }

    /**
     * Alasan "tenant tanpa sesi aktif" — sebut keadaan baris sesinya agar
     * jelas di outbox/UI (belum didaftarkan vs sesi sedang tidak hidup).
     */
    private function noSessionReason(?string $tenantId): string
    {
        $session = TenantWaSession::forTenant($tenantId);

        if ($session === null) {
            return 'Tenant ini belum mendaftarkan nomor WhatsApp (tidak ada sesi pengirim).';
        }

        return sprintf(
            'Sesi WhatsApp tenant ini tidak aktif (status: %s) — pesan tidak dikirim.',
            $session->status->label(),
        );
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
