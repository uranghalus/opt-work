<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Division;
use App\Models\Position;
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
use Illuminate\Support\Str;
use Throwable;

#[Signature('app:sync-positions {--tenant= : ID tenant tujuan (default: semua tenant Optigate)}')]
#[Description('Sinkronisasi position dari Optigate Portal API per company.')]
class SyncPositions extends Command
{
    protected const PER_PAGE = 100;

    protected const MAX_PAGES = 100;

    public function handle(): int
    {
        $url = config('services.optigate_portal.url');
        $token = config('services.optigate_portal.token');

        if (blank($url) || blank($token)) {
            $this->error('Konfigurasi portal belum diatur.');

            return Command::FAILURE;
        }

        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Tidak ada tenant Optigate untuk disinkronkan.');

            return Command::SUCCESS;
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $processedTenantIds = [];

        foreach ($tenants as $tenant) {
            if (blank($tenant->optigate_company_id)) {
                continue;
            }

            try {
                $positions = $this->fetchAllPositions($url, $token, $tenant->optigate_company_id);
            } catch (Throwable $exception) {
                $this->error("Gagal mengambil position untuk tenant {$tenant->id}: ".$exception->getMessage());

                return Command::FAILURE;
            }

            [$created, $updated] = $this->upsertPositions($tenant, $positions);

            $totalCreated += $created;
            $totalUpdated += $updated;
            $processedTenantIds[] = $tenant->id;
        }

        // Persist last sync timestamp on each tenant that was successfully processed.
        if (! empty($processedTenantIds)) {
            Tenant::query()->whereIn('id', $processedTenantIds)->update([
                'last_position_synced_at' => now(),
            ]);
        }

        $this->info("Sinkronisasi selesai: {$totalCreated} dibuat, {$totalUpdated} diperbarui.");

        return Command::SUCCESS;
    }

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

    protected function fetchAllPositions(string $url, string $token, string $companyId): Collection
    {
        $positions = collect();
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
            $positions = $positions->merge($batch);

            $total = $response->json('meta.total');
            $lastPage = $response->json('meta.last_page');
            $page++;
        } while (
            $batch->isNotEmpty()
            && ($total === null || $positions->count() < $total)
            && ($lastPage === null || $page <= $lastPage)
            && $page <= self::MAX_PAGES
        );

        return $positions;
    }

    protected function fetchPage(string $url, string $token, string $companyId, int $page): Response
    {
        $maxAttempts = 3;

        for ($attempt = 1; ; $attempt++) {
            try {
                return Http::withToken($token)
                    ->timeout(10)
                    ->withOptions(['verify' => filter_var(config('services.optigate_portal.verify', true), FILTER_VALIDATE_BOOLEAN)])
                    ->get($url.'/api/companies/'.$companyId.'/positions', [
                        'paginate' => 'true',
                        'per_page' => self::PER_PAGE,
                        'page' => $page,
                    ]);
            } catch (ConnectionException $exception) {
                if ($attempt >= $maxAttempts) {
                    throw $exception;
                }

                usleep(500000 * $attempt);
            }
        }
    }

    protected function upsertPositions(Tenant $tenant, Collection $positions): array
    {
        $created = 0;
        $updated = 0;

        // Build portal department ID → local department ID map
        $departmentsByPortalId = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('optigate_department_id')
            ->get()
            ->keyBy(fn ($d) => (string) $d->optigate_department_id);

        // Build portal division ID → local division ID map
        $divisionsByPortalId = Division::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('optigate_division_id')
            ->get()
            ->keyBy(fn ($d) => (string) $d->optigate_division_id);

        // Build portal position ID → local position ID map (for parent_position_id)
        $positionsByPortalId = Position::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('optigate_position_id')
            ->get()
            ->keyBy(fn ($p) => (string) $p->optigate_position_id);

        DB::transaction(function () use ($tenant, $positions, $departmentsByPortalId, $divisionsByPortalId, $positionsByPortalId, &$created, &$updated) {
            foreach ($positions as $position) {
                if (blank($position['id'] ?? null)) {
                    continue;
                }

                $portalDepartmentId = $position['department_id'] ?? null;
                $portalDivisionId = $position['division_id'] ?? null;
                $portalParentPositionId = $position['parent_position_id'] ?? null;

                $localDepartmentId = $portalDepartmentId
                    ? $departmentsByPortalId->get((string) $portalDepartmentId)?->getKey()
                    : null;

                $localDivisionId = $portalDivisionId
                    ? $divisionsByPortalId->get((string) $portalDivisionId)?->getKey()
                    : null;

                $localParentPositionId = $portalParentPositionId
                    ? $positionsByPortalId->get((string) $portalParentPositionId)?->getKey()
                    : null;

                // Determine is_vacant from OptiGate or compute locally
                $isVacant = $position['is_vacant'] ?? true;
                // If OptiGate doesn't provide is_vacant, compute from employees_count
                if (! isset($position['is_vacant'])) {
                    $isVacant = ($position['users_count'] ?? $position['employees_count'] ?? 0) === 0;
                }

                $record = Position::withoutGlobalScopes()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'optigate_position_id' => (string) $position['id'],
                ]);

                $record->forceFill([
                    'tenant_id' => $tenant->id,
                    'optigate_position_id' => (string) $position['id'],
                    'nama_position' => $position['name'] ?? null,
                    'department_id' => $localDepartmentId,
                    'division_id' => $localDivisionId,
                    'level' => $position['level'] ?? null,
                    'level_label' => $position['level_label'] ?? null,
                    'parent_position_id' => $localParentPositionId,
                    'is_direct' => $position['is_direct'] ?? false,
                    'is_vacant' => $isVacant,
                ])->save();

                $record->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return [$created, $updated];
    }
}
