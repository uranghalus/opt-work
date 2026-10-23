<?php

namespace App\Console\Commands;

use App\Models\TenantWaSession;
use App\Services\WahaClient;
use App\TenantWaSessionStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sinkronkan status sesi WhatsApp per tenant dari WAHA (ADR-0009: polling
 * terjadwal — BUKAN webhook masuk, permukaan serang tetap nol).
 *
 * Ketahanan: bila instance tidak terjangkau, TIDAK ada status yang di-flip
 * massal (jangan alarm palsu) — status lama dipertahankan dan percobaan
 * berikutnya jalan pada jadwal cron berikutnya.
 */
#[Signature('app:waha-sync-sessions')]
#[Description('Sinkronkan status sesi WhatsApp per tenant dari instance WAHA (polling)')]
class WahaSyncSessions extends Command
{
    public function handle(WahaClient $client): int
    {
        if (! WahaClient::configured()) {
            $this->line('WAHA belum dikonfigurasi (WAHA_URL kosong) — sinkronisasi dilewati.');

            return self::SUCCESS;
        }

        $sessions = TenantWaSession::withoutGlobalScope('tenant')->get();

        if ($sessions->isEmpty()) {
            $this->line('Belum ada sesi WhatsApp tenant yang terdaftar.');

            return self::SUCCESS;
        }

        try {
            $byName = $this->fetchSessionsByName($client);
        } catch (ConnectionException|StrayRequestException $exception) {
            return $this->unreachable($exception);
        } catch (Throwable) {
            // Daftar sesi tidak tersedia (versi/endpoint beda) — fallback:
            // tanya status per baris; kegagalan koneksi di tengah jalan tetap
            // dianggap instance tak terjangkau (jangan flip massal).
            try {
                $byName = $this->fetchSessionsIndividually($client, $sessions);
            } catch (ConnectionException|StrayRequestException $connectionError) {
                return $this->unreachable($connectionError);
            }
        }

        $updated = 0;

        foreach ($sessions as $session) {
            $item = $byName[$session->session_name] ?? null;

            if ($item === null) {
                // Ada di database tetapi tidak muncul lagi di WAHA (sesi
                // dihapus dari luar / logout) — pasangkan ulang bila perlu.
                $session->forceFill([
                    'status' => TenantWaSessionStatus::Missing,
                    'last_synced_at' => now(),
                ])->save();

                $updated++;

                continue;
            }

            $status = TenantWaSessionStatus::fromWahaStatus((string) ($item['status'] ?? ''));
            $phone = $this->phoneFrom($item);
            $wasWorking = $session->status->isActive();
            $isWorking = $status->isActive();

            $session->forceFill([
                'status' => $status,
                'phone_number' => $phone ?? $session->phone_number,
                // Baru tercatat saat transisi MASUK working (bukan tiap poll).
                'last_connected_at' => $isWorking && ! $wasWorking ? now() : $session->last_connected_at,
                'last_synced_at' => now(),
            ])->save();

            $updated++;
        }

        $this->info(sprintf(
            'Sinkronisasi selesai: %d/%d sesi diperbarui.',
            $updated,
            $sessions->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Peta nama sesi → objek sesi dari daftar global. Melempar ConnectionException
     * bila instance tidak terjangkau (keputusan penanganan ada di pemanggil).
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchSessionsByName(WahaClient $client): array
    {
        $byName = [];

        foreach ($client->listSessions() as $item) {
            if (is_array($item) && filled($item['name'] ?? null)) {
                $byName[(string) $item['name']] = $item;
            }
        }

        return $byName;
    }

    /**
     * Fallback bila endpoint daftar tidak tersedia: tanya satu per satu.
     * null = tidak ada di WAHA; error HTTP per baris = lewati (status lama
     * dipertahankan), jangan menandai sesi sehat sebagai hilang.
     * ConnectionException/StrayRequestException (instance mati total)
     * diteruskan ke pemanggil — tanpa data sama sekali lebih baik daripada
     * flip massal ke Missing.
     *
     * @param  Collection<int, TenantWaSession>  $sessions
     * @return array<string, array<string, mixed>>
     */
    private function fetchSessionsIndividually(WahaClient $client, $sessions): array
    {
        $byName = [];

        foreach ($sessions as $session) {
            try {
                $item = $client->getSession($session->session_name);
            } catch (ConnectionException|StrayRequestException $exception) {
                throw $exception;
            } catch (Throwable) {
                continue;
            }

            if ($item !== null) {
                $byName[$session->session_name] = $item;
            }
        }

        return $byName;
    }

    private function unreachable(Throwable $exception): int
    {
        Log::warning('app:waha-sync-sessions: WAHA tidak terjangkau — status lama dipertahankan.', [
            'error' => $exception->getMessage(),
        ]);

        $this->warn('WAHA tidak terjangkau — tidak ada status yang diubah.');

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function phoneFrom(array $item): ?string
    {
        $tel = $item['me']['tel'] ?? null;

        return filled($tel) ? (string) $tel : null;
    }
}
