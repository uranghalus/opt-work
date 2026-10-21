<?php

namespace App\Jobs;

use App\Models\TenantWaSession;
use App\Models\WaMessage;
use App\Services\WahaClient;
use App\WaMessageStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Throwable;

/**
 * Kirim satu baris outbox `wa_messages` ke WAHA (ADR-0009, G5; amendmen
 * slice 2: sesi per tenant + rate limit per sesi).
 *
 * Retry 3x dengan backoff; melempar ulang agar queue yang mengatur retry,
 * `last_error` dicatat di outbox pada tiap kegagalan. `failed()` (percobaan
 * terakhir habis) menandai baris `failed` — status tidak pernah kembali ke
 * pending.
 *
 * Guard status `pending` di handle() membuat job idempoten: dispatch ganda
 * (atau redelivery queue) tidak pernah mengirim dua kali.
 *
 * Isolasi tenant: sesi pengirim DI-RESOLVE ULANG saat job dieksekusi lewat
 * `TenantWaSession::activeFor(tenant_id baris)` — terikat tenant baris itu,
 * tanpa fallback. Sesi yang diputus/setelah dispatch menghasilkan `failed`
 * tanpa satu pun panggilan HTTP.
 *
 * NotTenantAware BY DESIGN: job hanya membaca outbox by id (tanpa scope
 * tenant) lalu memanggil gateway. Dispatch terjadi dari dalam notify() yang
 * bisa berjalan tanpa tenant current — mis. command eskalasi di konsol — dan
 * spatie v4 menghapus job tenant-aware yang tidak membawa tenantId.
 */
class SendWaMessageJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var array<int, int> */
    public $backoff = [30, 120];

    public function __construct(public string $waMessageId)
    {
        // Baris outbox ditulis di dalam transaksi bisnis; kirim hanya setelah
        // commit supaya tidak pernah membaca baris yang di-rollback.
        $this->afterCommit();
    }

    public function handle(WahaClient $client): void
    {
        $message = WaMessage::find($this->waMessageId);

        if ($message === null || $message->status !== WaMessageStatus::Pending) {
            return;
        }

        $chatId = WahaClient::normalizeChatId((string) $message->phone);

        if ($chatId === null) {
            // Defensif: channel seharusnya menandai skipped sejak awal.
            $message->forceFill([
                'status' => WaMessageStatus::Skipped,
                'last_error' => 'Nomor tidak dapat dinormalisasi.',
            ])->save();

            return;
        }

        // Sesi saat INI dipakai (bukan snapshot saat dispatch): bisa saja
        // sesi diputus atau diganti sementara job menunggu di queue. Selalu
        // terikat tenant_id baris ini — pesan tidak pernah keluar lewat sesi
        // tenant lain, dan tanpa sesi aktif job berhenti tanpa HTTP.
        $session = TenantWaSession::activeFor($message->tenant_id);

        if ($session === null) {
            $message->forceFill([
                'status' => WaMessageStatus::Failed,
                'last_error' => 'Sesi WhatsApp tenant tidak aktif saat job dieksekusi — pesan tidak dikirim.',
            ])->save();

            return;
        }

        // Kena batas = exception → antrian yang mengatur backoff, dan
        // `last_error` dicatat dulu supaya terlihat di outbox selama menunggu.
        // Pemakaian RateLimiter dicatat SEBELUM attempts HTTP, jadi rate limit
        // tidak menghabiskan kuota percobaan kirim.
        try {
            $this->enforceRateLimit($session);
        } catch (RuntimeException $exception) {
            $message->forceFill([
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            throw $exception;
        }

        $message->forceFill([
            'chat_id' => $chatId,
            'session_name' => $session->session_name,
            'attempts' => $message->attempts + 1,
        ])->save();

        try {
            $client->send($chatId, $message->payload, $session->session_name);
        } catch (Throwable $exception) {
            $message->forceFill([
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            throw $exception;
        }

        $message->forceFill([
            'status' => WaMessageStatus::Sent,
            'sent_at' => now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * Rate limit per sesi/nomor (bukan global): kunci terpisah per
     * `session_name` sehingga antrean panjang satu nomor TIDAK menahan nomor
     * tenant lain. Jeda antar pesan memakai window berdurasi `min_interval`;
     * kuota per menit memakai window 60 detik.
     *
     * @throws RuntimeException bila sesi sedang mencapai batas kirim
     */
    private function enforceRateLimit(TenantWaSession $session): void
    {
        $name = $session->session_name;
        $perMinute = max(1, (int) config('waha.rate_per_minute', 20));
        $minuteKey = "waha:send:minute:{$name}";

        if (RateLimiter::tooManyAttempts($minuteKey, $perMinute)) {
            throw new RuntimeException(sprintf(
                'Rate limit sesi %s tercapai (%d pesan/menit) — job dijadwalkan ulang.',
                $name,
                $perMinute,
            ));
        }

        $interval = max(0, (int) config('waha.min_interval_seconds', 2));
        $intervalKey = "waha:send:interval:{$name}";

        if ($interval > 0 && RateLimiter::tooManyAttempts($intervalKey, 1)) {
            throw new RuntimeException(sprintf(
                'Jeda minimal %d detik antar pesan sesi %s belum tercapai — job dijadwalkan ulang.',
                $interval,
                $name,
            ));
        }

        RateLimiter::hit($minuteKey, 60);

        if ($interval > 0) {
            RateLimiter::hit($intervalKey, $interval);
        }
    }

    /**
     * Percobaan terakhir habis (atau job di-fail-kan worker): baris outbox
     * menjadi `failed` dan tetap terlihat untuk diagnosis.
     */
    public function failed(?Throwable $exception): void
    {
        WaMessage::query()
            ->whereKey($this->waMessageId)
            ->where('status', WaMessageStatus::Pending->value)
            ->update([
                'status' => WaMessageStatus::Failed->value,
                'last_error' => mb_substr($exception?->getMessage() ?? 'Gagal mengirim pesan WhatsApp.', 0, 2000),
                'updated_at' => now(),
            ]);
    }
}
