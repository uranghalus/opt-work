<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantWaSession;
use App\Services\WahaClient;
use App\TenantWaSessionStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:waha-ping
        {--tenant= : Kode tenant untuk memakai sesi/nomor miliknya}
        {phone? : Nomor WhatsApp tujuan pesan test (wajib bersama --tenant)}')]
#[Description('Diagnostik gateway WA: status instance, sesi per tenant, dan pesan test (ADR-0009)')]
class WahaPing extends Command
{
    public function handle(WahaClient $client): int
    {
        $instance = $client->instanceStatus();

        if (! $instance['configured']) {
            $this->error('WAHA belum dikonfigurasi — set WAHA_URL di .env (lihat .env.example).');

            return self::FAILURE;
        }

        if ($instance['status'] === 'UNREACHABLE') {
            $this->error('Gateway tidak terjangkau. Cek WAHA_URL dan layanan WAHA-nya.');

            return self::FAILURE;
        }

        $this->info(sprintf('Instance OK — sesi terdaftar: %d', $instance['sessions'] ?? 0));

        $tenantCode = $this->option('tenant');

        if ($tenantCode === null) {
            // Tanpa --tenant: cek instance saja. Nomor global sudah dihapus
            // (ADR-0009 amendmen) — kirim test hanya lewat sesi tenant.
            $this->line('Kiriman test per tenant: php artisan app:waha-ping --tenant=<kode> [nomor].');

            return self::SUCCESS;
        }

        $tenant = Tenant::query()->where('code', (string) $tenantCode)->first();

        if ($tenant === null) {
            $this->error("Tenant dengan kode '{$tenantCode}' tidak ditemukan.");

            return self::FAILURE;
        }

        $session = TenantWaSession::forTenant($tenant->getKey());

        if ($session === null) {
            $this->error("Tenant {$tenantCode} belum mendaftarkan sesi WhatsApp — daftarkan lewat Pengaturan → WhatsApp.");

            return self::FAILURE;
        }

        try {
            $remote = $client->getSession($session->session_name);
        } catch (Throwable $exception) {
            $this->error('Gateway tidak terjangkau: '.$exception->getMessage());

            return self::FAILURE;
        }

        $remoteStatus = $remote === null
            ? 'TIDAK ADA DI WAHA'
            : (string) ($remote['status'] ?? 'TIDAK DIKETAHUI');

        $this->info(sprintf(
            'Sesi %s (tenant %s) — status: %s (lokal: %s)',
            $session->session_name,
            $tenantCode,
            $remoteStatus,
            $session->status->label(),
        ));

        $live = $remote === null
            ? TenantWaSessionStatus::Missing
            : TenantWaSessionStatus::fromWahaStatus((string) ($remote['status'] ?? ''));

        if (! $live->isActive()) {
            $this->error('Sesi tidak dalam kondisi WORKING — pesan test tidak dikirim. Buka Pengaturan → WhatsApp untuk pairing QR.');

            return self::FAILURE;
        }

        $phone = $this->argument('phone');

        if ($phone === null) {
            $this->line('Tambahkan nomor untuk kirim pesan test, mis. app:waha-ping --tenant='.$tenantCode.' 08123456789.');

            return self::SUCCESS;
        }

        $chatId = WahaClient::normalizeChatId((string) $phone);

        if ($chatId === null) {
            $this->error('Nomor tujuan tidak valid.');

            return self::FAILURE;
        }

        try {
            $client->send($chatId, 'Test kirim dari Opti-Work (app:waha-ping).', $session->session_name);
        } catch (Throwable $exception) {
            $this->error('Gagal mengirim: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Pesan test terkirim ke {$chatId} lewat sesi {$session->session_name}.");

        return self::SUCCESS;
    }
}
