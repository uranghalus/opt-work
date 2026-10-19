<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

#[Signature('app:sync-departments {--tenant= : ID tenant tujuan (default: semua tenant Optigate)}')]
#[Description('Sinkronisasi department dari Optigate Portal API per company: buat, perbarui, nonaktifkan kode yang hilang.')]
class SyncDepartments extends Command
{
    /** Departments requested per page from the Optigate API. */
    protected const PER_PAGE = 100;

    /** Hard cap on pages, so a broken meta.total can never loop forever. */
    protected const MAX_PAGES = 100;

    public function handle(): int
    {
        $url = config('services.optigate_portal.url');
        $token = config('services.optigate_portal.token');

        if (blank($url) || blank($token)) {
            $this->error('Konfigurasi portal belum diatur. Tambahkan WEB_PORTAL_URL dan WEB_PORTAL_TOKEN di file .env.');

            return Command::FAILURE;
        }

        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Tidak ada tenant Optigate untuk disinkronkan.');

            return Command::SUCCESS;
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalDeactivated = 0;

        foreach ($tenants as $tenant) {
            if (blank($tenant->optigate_company_id)) {
                $this->warn("Tenant {$tenant->id} ({$tenant->name}) dilewati: tidak memiliki optigate_company_id.");

                continue;
            }

            try {
                $departments = $this->fetchAllDepartments($url, $token, $tenant->optigate_company_id);
            } catch (Throwable $exception) {
                $this->error("Gagal mengambil department untuk tenant {$tenant->id}: ".$exception->getMessage());
                Log::error('Optigate department sync: gagal mengambil data department.', [
                    'tenant_id' => $tenant->id,
                    'exception' => $exception,
                ]);

                return Command::FAILURE;
            }

            [$created, $updated] = $this->upsertDepartments($tenant, $departments);
            $deactivated = $this->deactivateMissingDepartments($tenant, $departments);

            $totalCreated += $created;
            $totalUpdated += $updated;
            $totalDeactivated += $deactivated;
        }

        // Persist last sync timestamp on each tenant that was successfully processed.
        Tenant::query()->whereIn('id', $tenants->pluck('id'))->update([
            'last_department_synced_at' => now(),
        ]);

        $this->info("Sinkronisasi selesai: {$totalCreated} dibuat, {$totalUpdated} diperbarui, {$totalDeactivated} dinonaktifkan.");
        Log::info('Optigate department sync selesai.', [
            'created' => $totalCreated,
            'updated' => $totalUpdated,
            'deactivated' => $totalDeactivated,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Resolve the tenants to sync: the one passed via --tenant, the current
     * tenant, or every tenant that has an Optigate company mapping.
     *
     * @return Collection<int, Tenant>
     */
    protected function resolveTenants(): Collection
    {
        $tenantId = $this->option('tenant') ?: Tenant::current()?->id;

        if ($tenantId) {
            $tenant = Tenant::query()->find($tenantId);

            return collect($tenant ? [$tenant] : []);
        }

        return Tenant::query()
            ->whereNotNull('optigate_company_id')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Fetch every department of one company from the Optigate API, following
     * pagination.
     *
     * Transient failures (connection errors, 5xx) are retried with a growing
     * backoff; 401/403 fail fast because retrying them cannot succeed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function fetchAllDepartments(string $url, string $token, string $companyId): Collection
    {
        $departments = collect();
        $page = 1;
        $total = null;
        $lastPage = null;

        do {
            $response = $this->fetchPage($url, $token, $companyId, $page);

            if ($response->unauthorized() || $response->forbidden()) {
                throw new RequestException($response);
            }

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status().': '.Str::limit($response->body(), 300));
            }

            $batch = collect($response->json('data', []));
            $departments = $departments->merge($batch);

            $total = $response->json('meta.total');
            $lastPage = $response->json('meta.last_page');
            $page++;
        } while (
            $batch->isNotEmpty()
            && ($total === null || $departments->count() < $total)
            && ($lastPage === null || $page <= $lastPage)
            && $page <= self::MAX_PAGES
        );

        return $departments;
    }

    /**
     * Fetch one page of departments, retrying transient failures.
     */
    protected function fetchPage(string $url, string $token, string $companyId, int $page): Response
    {
        $maxAttempts = 3;

        for ($attempt = 1; ; $attempt++) {
            $verifyConfig = config('services.optigate_portal.verify', true);
            $verifyOption = (is_string($verifyConfig) && ! in_array(strtolower(trim($verifyConfig)), ['true', 'false', '1', '0'], true) && file_exists($verifyConfig))
                ? $verifyConfig
                : filter_var($verifyConfig, FILTER_VALIDATE_BOOLEAN);

            try {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->withOptions(['verify' => $verifyOption])
                    ->get($url.'/api/companies/'.$companyId.'/departments', [
                        'paginate' => 'true',
                        'per_page' => self::PER_PAGE,
                        'page' => $page,
                        // Portal relasi HOD: user UUID pemegang jabatan
                        // pimpinan department (lihat optigate-complete-api-docs.md).
                        'with' => 'hod',
                    ]);
            } catch (ConnectionException $exception) {
                if ($attempt >= $maxAttempts) {
                    throw $exception;
                }

                usleep(500_000 * $attempt);

                continue;
            }

            // Auth failures and client errors are returned as-is; only server
            // errors are worth another attempt.
            if ($response->serverError() && $attempt < $maxAttempts) {
                usleep(500_000 * $attempt);

                continue;
            }

            return $response;
        }
    }

    /**
     * Upsert portal departments keyed by the portal department id.
     *
     * @param  Collection<int, array<string, mixed>>  $departments
     * @return array{0: int, 1: int} [created, updated]
     */
    protected function upsertDepartments(Tenant $tenant, Collection $departments): array
    {
        $created = 0;
        $updated = 0;

        // Portal mengidentifikasi personel dengan UUID; lokal menunjuk HOD
        // lewat users.id (bigint). Peta optigate_user_id (employee) ->
        // users.id dibangun sekali per tenant, jadi HOD hanya tertaut
        // kalau karyawan itu sudah ikut sync karyawan.
        $hodUserIds = Employee::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('optigate_user_id')
            ->whereHas('user')
            ->with('user:id,employee_id')
            ->get()
            ->filter(fn (Employee $employee) => $employee->user !== null)
            ->mapWithKeys(fn (Employee $employee) => [$employee->optigate_user_id => $employee->user->id]);

        DB::transaction(function () use ($tenant, $departments, $hodUserIds, &$created, &$updated) {
            foreach ($departments as $dept) {
                if (blank($dept['id'] ?? null)) {
                    continue;
                }

                // Idempotent key: the portal department id. tenant_id is filled
                // explicitly (no Tenant::current() on the landlord side).
                $record = Department::withoutGlobalScopes()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'optigate_department_id' => (string) $dept['id'],
                ]);

                $record->forceFill([
                    'tenant_id' => $tenant->id,
                    'optigate_department_id' => (string) $dept['id'],
                    'kode_department' => $dept['code'] ?? (string) $dept['id'],
                    'nama_department' => $dept['name'] ?? null,
                    // HOD portal (UUID) -> user lokal; null kalau belum ada.
                    'hod_user_id' => $hodUserIds[$dept['hod']['id'] ?? null] ?? null,
                    'is_active' => true,
                ])->save();

                $record->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return [$created, $updated];
    }

    /**
     * Deactivate departments whose portal row disappeared: set is_active =
     * false instead of renaming kode_department so employee FKs stay valid.
     */
    protected function deactivateMissingDepartments(Tenant $tenant, Collection $departments): int
    {
        $activeIds = $departments
            ->filter(fn (array $dept) => filled($dept['id'] ?? null))
            ->map(fn (array $dept) => (string) $dept['id'])
            ->values();

        $stale = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('optigate_department_id')
            ->whereNotIn('optigate_department_id', $activeIds->all())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return (int) $stale;
    }
}
