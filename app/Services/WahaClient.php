<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien WAHA (self-hosted WhatsApp HTTP API) — satu-satunya titik keluar
 * HTTP untuk pengiriman WA (ADR-0009). Pola mengikuti blueprint opti-work2
 * (endpoint /api/sendText, header X-Api-Key) tanpa membawa kelemahannya:
 * tanpa retry di sini (retry = tugas job dengan backoff), gagal selalu
 * melempar exception supaya outbox mencatat last_error.
 */
class WahaClient
{
    /**
     * Pengiriman aktif hanya jika WAHA_URL terisi — sebaliknya channel notifikasi
     * menjadi no-op (dev tanpa gateway = nol baris outbox, nol HTTP).
     */
    public static function configured(): bool
    {
        return filled(config('waha.url'));
    }

    /**
     * Peta nomor telepon → chatId WAHA. ChatId mentah (mengandung '@')
     * dilewatkan apa adanya; `08xx` dinormalisasi ke `628xx`; sisa digit
     * lain (mis. sudah `628…`) dipakai langsung.
     *
     * @return string|null null bila tidak ada satu pun digit yang valid
     */
    public static function normalizeChatId(string $phone): ?string
    {
        $trimmed = trim($phone);

        if ($trimmed === '') {
            return null;
        }

        if (str_contains($trimmed, '@')) {
            return $trimmed;
        }

        $digits = preg_replace('/\D/', '', $trimmed);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits.'@c.us';
    }

    /**
     * Kirim satu pesan teks. Melempar exception pada kegagalan apa pun
     * (connection, 4xx, 5xx) — pemanggil (job) yang menentukan retry dan
     * mencatat last_error ke outbox.
     *
     * @throws RuntimeException bila gateway belum dikonfigurasi
     */
    public function send(string $chatId, string $text): Response
    {
        if (! self::configured()) {
            throw new RuntimeException('WAHA belum dikonfigurasi (WAHA_URL kosong).');
        }

        return $this->client()
            ->post('/api/sendText', [
                'session' => (string) config('waha.session'),
                'chatId' => $chatId,
                'text' => $text,
            ])
            ->throw();
    }

    /**
     * Status session pengirim untuk diagnostik (app:waha-ping).
     *
     * @return array{configured: bool, session: string, status: string}
     */
    public function sessionStatus(): array
    {
        $session = (string) config('waha.session');

        if (! self::configured()) {
            return ['configured' => false, 'session' => $session, 'status' => 'NOT_CONFIGURED'];
        }

        try {
            $response = $this->client()->get('/api/sessions/'.$session);
        } catch (\Throwable) {
            return ['configured' => true, 'session' => $session, 'status' => 'UNREACHABLE'];
        }

        if ($response->notFound()) {
            return ['configured' => true, 'session' => $session, 'status' => 'STOPPED'];
        }

        if (! $response->successful()) {
            return ['configured' => true, 'session' => $session, 'status' => 'ERROR'];
        }

        return [
            'configured' => true,
            'session' => $session,
            'status' => (string) ($response->json('status') ?? 'UNKNOWN'),
        ];
    }

    private function client(): PendingRequest
    {
        $apiKey = config('waha.api_key');

        $client = Http::baseUrl(rtrim((string) config('waha.url'), '/'))
            ->connectTimeout((int) config('waha.connect_timeout'))
            ->timeout((int) config('waha.timeout'));

        if (filled($apiKey)) {
            $client = $client->withHeaders(['X-Api-Key' => (string) $apiKey]);
        }

        return $client;
    }
}
