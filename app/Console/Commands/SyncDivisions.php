<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Division;
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

#[Signature('app:sync-divisions {--tenant= : ID tenant tujuan (default: semua tenant Optigate)}')]
#[Description('Sinkronisasi division dari Optigate Portal API per company.')]
class SyncDivisions extends Command
{
    /** Divisions requested per page from the Optigate API. */
    protected const PER_PAGE = 100;

    /** Hard cap on pages. */
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
                $divisions = $this->fetchAllDivisions($url, $token, $tenant->optigate_company_id);
            } catch (Throwable $exception) {
                $this->error("Gagal mengambil division untuk tenant {$tenant->id}: ".$exception->getMessage());
                Log::error('Optigate division sync: gagal mengambil data division.', [
                    'tenant_id' => $tenant->id,
                    'exception' => $exception,
                ]);

                return Command::FAILURE;
            }

            [$created, $updated] = $this->upsertDivisions($tenant, $divisions);
            $deactivated = $this->deactivateMissingDivisions($tenant, $divisions);

            $totalCreated += $created;
            $totalUpdated += $updated;
            $totalDeactivated += $deactivated;
        }

        // Persist last sync timestamp on each tenant that was successfully processed.
        Tenant::query()->whereIn('id', $tenants->pluck('id'))->update([
            'last_division_synced_at' => now(),
        ]);

        $this->info("Sinkronisasi selesai: {$totalCreated} dibuat, {$totalUpdated} diperbarui, {$totalDeactivated} dinonaktifkan.");
        Log::info('Optigate division sync selesai.', [
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
     * Fetch every division of one company from the Optigate API, following
     * pagination.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function fetchAllDivisions(string $url, string $token, string $companyId): Collection
    {
        $divisions = collect();
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
            $divisions = $divisions->merge($batch);

            $total = $response->json('meta.total');
            $lastPage = $response->json('meta.last_page');
            $page++;
        } while (
            $batch->isNotEmpty()
            && ($total === null || $divisions->count() < $total)
            && ($lastPage === null || $page <= $lastPage)
            && $page <= self::MAX_PAGES
        );

        return $divisions;
    }

    /**
     * Fetch one page of divisions, retrying transient failures.
     */
    protected function fetchPage(string $url, string $token, string $companyId, int $page): Response
    {
        $maxAttempts = 3;

        for ($attempt = 1; ; $attempt++) {
            try {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->withOptions(['verify' => filter_var(config('services.optigate_portal.verify', true), FILTER_VALIDATE_BOOLEAN)])
                    ->get($url.'/api/companies/'.$companyId.'/divisions', [
                        'paginate' => 'true',
                        'per_page' => self::PER_PAGE,
                        'page' => $page,
                    ]);

                return $response;
            } catch (ConnectionException $exception) {
                if ($attempt >= $maxAttempts) {
                    throw $exception;
                }

                $this->warn("Retry {$attempt}/{$maxAttempts} untuk endpoint divisions tenant {$companyId}...");
                usleep(500000 * $attempt);
            }
        }
    }

    /**
     * Upsert divisions for one tenant.
     */
    protected function upsertDivisions(Tenant $tenant, Collection $divisions): array
    {
        $created = 0;
        $updated = 0;

        $departmentsByPortalId = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('optigate_department_id')
            ->get()
            ->keyBy(fn (Department $department) => (string) $department->optigate_department_id);

        DB::transaction(function () use ($tenant, $divisions, $departmentsByPortalId, &$created, &$updated) {
            foreach ($divisions as $div) {
                if (blank($div['id'] ?? null)) {
                    continue;
                }

                $portalDepartmentId = $div['department_id'] ?? null;
                $localDepartmentId = filled($portalDepartmentId)
                    ? $departmentsByPortalId->get((string) $portalDepartmentId)?->getKey()
                    : null;

                if (filled($portalDepartmentId) && $localDepartmentId === null) {
                    Log::warning('Optigate division sync: parent department not found.', [
                        'tenant_id' => $tenant->id,
                        'division_optigate_id' => (string) $div['id'],
                        'department_optigate_id' => (string) $portalDepartmentId,
                    ]);
                }

                // is_direct is explicit in the portal payload. When the flag
                // is absent, treat the division as unmapped rather than
                // guessing that it is direct.
                $isDirect = array_key_exists('is_direct', $div)
                    ? (bool) $div['is_direct']
                    : false;

                $record = Division::withoutGlobalScopes()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'optigate_division_id' => (string) $div['id'],
                ]);

                $record->forceFill([
                    'tenant_id' => $tenant->id,
                    'optigate_division_id' => (string) $div['id'],
                    'department_id' => $localDepartmentId,
                    'kode_division' => $div['code'] ?? null,
                    'nama_division' => $div['name'] ?? null,
                    'is_direct' => $isDirect,
                    'is_active' => true,
                ])->save();

                $record->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return [$created, $updated];
    }

    /**
     * Deactivate divisions whose portal row disappeared: set is_active = false.
     */
    protected function deactivateMissingDivisions(Tenant $tenant, Collection $divisions): int
    {
        $activeIds = $divisions
            ->filter(fn (array $div) => filled($div['id'] ?? null))
            ->map(fn (array $div) => (string) $div['id'])
            ->values();

        $stale = Division::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('optigate_division_id')
            ->whereNotIn('optigate_division_id', $activeIds->all())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return (int) $stale;
    }
}
