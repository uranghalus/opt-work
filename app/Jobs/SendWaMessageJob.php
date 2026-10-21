<?php

namespace App\Jobs;

use App\Models\WaMessage;
use App\Services\WahaClient;
use App\WaMessageStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Throwable;

/**
 * Kirim satu baris outbox `wa_messages` ke WAHA (ADR-0009, G5).
 *
 * Retry 3x dengan backoff; melempar ulang agar queue yang mengatur retry,
 * `last_error` dicatat di outbox pada tiap kegagalan. `failed()` (percobaan
 * terakhir habis) menandai baris `failed` — status tidak pernah kembali ke
 * pending.
 *
 * Guard status `pending` di handle() membuat job idempoten: dispatch ganda
 * (atau redelivery queue) tidak pernah mengirim dua kali.
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

        $message->forceFill([
            'chat_id' => $chatId,
            'attempts' => $message->attempts + 1,
        ])->save();

        try {
            $client->send($chatId, $message->payload);
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
