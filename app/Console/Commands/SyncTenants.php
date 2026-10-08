<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[Signature('app:sync-tenants')]
#[Description('Auto-sync tenant data from Optigate Portal API to local database')]
class SyncTenants extends Command
{
    public function handle(): void
    {
        $url = config('services.optigate_portal.url').'/api/companies';
        $token = config('services.optigate_portal.token');

        $tenantId = Tenant::current()?->id;

        if (! $tenantId) {
            $this->error('Tidak ada tenant aktif. Sinkronisasi dibatalkan.');

            return;
        }

        $this->info("Memulai sinkronisasi tenant untuk tenant aktif {$tenantId}...");

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->withOptions(['verify' => config('services.optigate_portal.verify')])
                ->get($url);

            if ($response->successful()) {
                $companies = $response->json('data') ?? $response->json();
                $count = 0;

                foreach ($companies as $company) {
                    Tenant::updateOrCreate(
                        ['id' => $company['id']],
                        [
                            'name' => $company['name'],
                        ]
                    );
                    $count++;
                }

                $this->info("Sinkronisasi selesai! {$count} data tenant berhasil diproses.");
                Log::info("Auto-sync Tenant berhasil: {$count} data.");
            } else {
                $errorMsg = 'Gagal mengambil data tenant dari API Portal ('.$response->status().'): '.$response->body();
                $this->error($errorMsg);
                Log::error($errorMsg);
            }
        } catch (\Throwable $th) {
            $errorMsg = 'Exception API Tenant: '.$th->getMessage();
            $this->error($errorMsg);
            Log::error($errorMsg);
        }
    }
}