<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

#[Signature('app:sync-divisions {--tenant= : ID tenant tujuan (default: tenant aktif)}')]
#[Description('Auto-sync divisions data from Optigate Portal API to local database')]
class SyncDivisions extends Command
{
    public function handle(): int
    {
        $url = config('services.optigate_portal.url');
        $token = config('services.optigate_portal.token');

        if (blank($url) || blank($token)) {
            $this->error('Konfigurasi portal belum diatur. Tambahkan WEB_PORTAL_URL dan WEB_PORTAL_TOKEN di file .env.');

            return Command::FAILURE;
        }

        $tenantId = $this->option('tenant') ?: Tenant::current()?->id;

        if (! $tenantId) {
            $this->error('Tidak ada tenant aktif. Gunakan --tenant=<id> atau aktifkan tenant terlebih dahulu.');

            return Command::FAILURE;
        }

        $this->info("Memulai sinkronisasi division untuk tenant {$tenantId}...");

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->withOptions(['verify' => config('services.optigate_portal.verify')])
                ->get($url.'/api/divisions');

            if (! $response->successful()) {
                $errorMsg = 'Gagal mengambil data division dari API Portal ('.$response->status().'): '.$response->body();
                $this->error($errorMsg);
                Log::error($errorMsg);

                return Command::FAILURE;
            }

            $divisions = $response->json('data') ?? $response->json();
            $count = 0;

            foreach ($divisions as $div) {
                Division::withoutGlobalScopes()->updateOrCreate(
                    ['id_division' => $div['id']],
                    [
                        'tenant_id' => $tenantId,
                        'nama_division' => $div['name'],
                        'kode_division' => $div['code'] ?? null,
                    ]
                );
                $count++;
            }

            $this->info("Sinkronisasi selesai! $count data berhasil diproses.");
            Log::info("Auto-sync Division berhasil: $count data.");

            return Command::SUCCESS;
        } catch (\Throwable $th) {
            $errorMsg = 'Exception API Division: '.$th->getMessage();
            $this->error($errorMsg);
            Log::error($errorMsg);

            return Command::FAILURE;
        }
    }
}
