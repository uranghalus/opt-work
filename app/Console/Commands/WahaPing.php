<?php

namespace App\Console\Commands;

use App\Services\WahaClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:waha-ping
        {phone? : Nomor WhatsApp tujuan untuk pesan test (opsional)}')]
#[Description('Diagnostik gateway WA: konfigurasi, status session, dan pesan test (ADR-0009)')]
class WahaPing extends Command
{
    public function handle(WahaClient $client): int
    {
        $status = $client->sessionStatus();

        if (! $status['configured']) {
            $this->error('WAHA belum dikonfigurasi — set WAHA_URL di .env (lihat .env.example).');

            return self::FAILURE;
        }

        $this->info(sprintf('Session: %s — status: %s', $status['session'], $status['status']));

        if ($status['status'] === 'UNREACHABLE') {
            $this->error('Gateway tidak terjangkau. Cek WAHA_URL dan layanan WAHA-nya.');

            return self::FAILURE;
        }

        $phone = $this->argument('phone');

        if ($phone === null) {
            $this->line('Tambahkan nomor (contoh: app:waha-ping 08123456789) untuk mengirim pesan test.');

            return self::SUCCESS;
        }

        $chatId = WahaClient::normalizeChatId($phone);

        if ($chatId === null) {
            $this->error('Nomor tidak valid — tidak ada digit yang bisa dipakai.');

            return self::FAILURE;
        }

        try {
            $client->send($chatId, "🔧 *Test Pesan dari opti-works*\nJika Anda menerima pesan ini, gateway WAHA berfungsi.");
        } catch (\Throwable $exception) {
            $this->error('Gagal mengirim: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Pesan test terkirim ke '.$chatId.'.');

        return self::SUCCESS;
    }
}
