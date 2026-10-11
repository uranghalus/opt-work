<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[Signature('app:sync-employees {--tenant= : ID tenant tujuan (default: tenant aktif)}')]
#[Description('Auto-sync employees data from Optigate Portal API to local database')]
class SyncEmployees extends Command
{
    public function handle(): int
    {
        $url = config('services.optigate_portal.url').'/api/users';
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

        // Landlord-side sync (runs from the scheduler and the sync button):
        // there is no Tenant::current() here, so the tenant and its Optigate
        // company are resolved explicitly.
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->error("Tenant {$tenantId} tidak ditemukan.");

            return Command::FAILURE;
        }

        if (blank($tenant->optigate_company_id)) {
            $this->error('Tenant tidak memiliki optigate_company_id — tarik per company agar karyawan tidak tercampur antar cabang.');

            return Command::FAILURE;
        }

        $this->info("Memulai sinkronisasi employee untuk tenant {$tenantId}...");

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->withOptions(['verify' => config('services.optigate_portal.verify')])
                ->get($url, ['company_id' => $tenant->optigate_company_id]);

            if (! $response->successful()) {
                $errorMsg = 'Gagal mengambil data employee dari API Portal: '.$response->body();
                $this->error($errorMsg);
                Log::error($errorMsg);

                return Command::FAILURE;
            }

            $employees = $response->json('data') ?? $response->json();
            $count = 0;

            foreach ($employees as $employee) {
                if (blank($employee['id'] ?? null)) {
                    continue;
                }

                // Idempotent key: the portal user uuid. tenant_id is
                // force-filled explicitly (it is not fillable, and there is
                // no Tenant::current() to backfill it here). Local-only state
                // (banned_at) and the linked akun are never touched.
                $record = Employee::withoutGlobalScopes()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'optigate_user_id' => $employee['id'],
                ]);

                $record->forceFill([
                    'tenant_id' => $tenant->id,
                    'optigate_user_id' => $employee['id'],
                    'nik_employee' => $employee['nik'] ?? null,
                    'nama_employee' => $employee['name'],
                    'email' => $employee['email'] ?? null,
                    'number' => $employee['whatsapp_number'] ?? null,
                    'photo_url' => $employee['photo_url'] ?? null,
                    'last_login_ip' => $employee['last_login_ip'] ?? null,
                ])->save();
                $count++;
            }

            $this->info("Sinkronisasi selesai! $count data berhasil diproses.");
            Log::info("Auto-sync Employee berhasil: $count data.");

            return Command::SUCCESS;
        } catch (\Throwable $th) {
            $errorMsg = 'Exception API Employee: '.$th->getMessage();
            $this->error($errorMsg);
            Log::error($errorMsg);

            return Command::FAILURE;
        }
    }
}
