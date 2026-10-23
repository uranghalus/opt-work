<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien WAHA (self-hosted WhatsApp HTTP API) — satu-satunya titik keluar
 * HTTP untuk pengiriman & pengelolaan sesi (ADR-0009 amendmen slice 2:
 * instance satu, sesi/nomor per tenant dari tabel `tenant_wa_sessions`).
 * Pola endpoint mengikuti blueprint opti-work2 (/api/sendText,
 * /api/sessions/*, /api/{session}/auth/qr, header X-Api-Key) tanpa membawa
 * kelemahannya: tanpa retry di sini (retry = tugas job dengan backoff),
 * kegagalan kirim selalu melempar exception supaya outbox mencatat
 * `last_error`.
 *
 * Nama sesi SELALU dikirim eksplisit oleh pemanggil (hasil resolusi
 * `TenantWaSession::activeFor/forTenant`) — tidak ada lagi sesi global dan
 * tidak ada fallback: pesan tidak akan pernah keluar lewat nomor lain.
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
     * Kirim satu pesan teks lewat sesi tertentu. Melempar exception pada
     * kegagalan apa pun (connection, 4xx, 5xx) — pemanggil (job) yang
     * menentukan retry dan mencatat last_error ke outbox.
     *
     * @throws RuntimeException bila gateway belum dikonfigurasi
     */
    public function send(string $chatId, string $text, string $session): Response
    {
        if (! self::configured()) {
            throw new RuntimeException('WAHA belum dikonfigurasi (WAHA_URL kosong).');
        }

        return $this->client()
            ->post('/api/sendText', [
                'session' => $session,
                'chatId' => $chatId,
                'text' => $text,
            ])
            ->throw();
    }

    /**
     * Diagnostik instance (app:waha-ping tanpa --tenant): terjangkau dan
     * berapa sesi terdaftar. Tanpa pengiriman — nomor global sudah dihapus
     * (ADR-0009 amendmen), kirim test hanya per tenant.
     *
     * @return array{configured: bool, status: string, sessions: int|null}
     */
    public function instanceStatus(): array
    {
        if (! self::configured()) {
            return ['configured' => false, 'status' => 'NOT_CONFIGURED', 'sessions' => null];
        }

        try {
            $sessions = $this->listSessions();
        } catch (\Throwable) {
            return ['configured' => true, 'status' => 'UNREACHABLE', 'sessions' => null];
        }

        return ['configured' => true, 'status' => 'OK', 'sessions' => count($sessions)];
    }

    /**
     * Semua sesi di instance (dasar `app:waha-sync-sessions`). Melempar bila
     * instance tidak terjangkau atau merespons error — pemanggil memutuskan
     * apakah status lama dipertahankan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSessions(): array
    {
        $response = $this->client()->get('/api/sessions')->throw();

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Detail satu sesi (status + me). null = sesi tidak ada di WAHA (404);
     * error lain (5xx) melempar — pemanggil (sync) mempertahankan status lama.
     *
     * @return array<string, mixed>|null
     */
    public function getSession(string $session): ?array
    {
        $response = $this->client()->get('/api/sessions/'.$session);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Nyalakan sesi yang sudah ada, atau buat baru bila belum terdaftar
     * (pola blueprint opti-work2: start → fallback create).
     */
    public function startOrCreateSession(string $session): bool
    {
        if ($this->client()->post('/api/sessions/'.$session.'/start')->successful()) {
            return true;
        }

        return $this->client()->post('/api/sessions', ['name' => $session])->successful();
    }

    /**
     * QR pairing sebagai data URI PNG (endpoint blueprint opti-work2).
     * Toleran: kondisi non-2xx (belum ada QR, sesi belum dalam mode scan)
     * → null tanpa exception; hanya error koneksi yang melempar.
     */
    public function qrImage(string $session): ?string
    {
        $response = $this->client()->get('/api/'.$session.'/auth/qr?format=image');

        if (! $response->successful()) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($response->body());
    }

    /**
     * Putuskan sesi: utamakan POST .../stop (bisa /start lagi tanpa scan QR
     * ulang); fallback POST .../logout bila endpoint stop tidak tersedia
     * (WAHA versi lama) — logout = unpair, perlu pairing baru.
     */
    public function stopSession(string $session): bool
    {
        $response = $this->client()->post('/api/sessions/'.$session.'/stop');

        if ($response->successful()) {
            return true;
        }

        if ($response->notFound() || $response->methodNotAllowed()) {
            return $this->client()->post('/api/sessions/'.$session.'/logout')->successful();
        }

        return false;
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
