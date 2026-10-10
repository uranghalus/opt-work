<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

#[Signature('app:sync-tenants')]
#[Description('Sinkronisasi cabang (tenant) dari company di Optigate Portal API: buat, perbarui, nonaktifkan yang hilang, aktifkan kembali yang muncul lagi.')]
class SyncTenants extends Command
{
    /** Companies requested per page from the Optigate API. */
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

        try {
            $companies = $this->fetchAllCompanies($url, $token);
        } catch (Throwable $exception) {
            $this->error('Gagal mengambil data perusahaan dari Optigate: '.$exception->getMessage());
            Log::error('Optigate tenant sync: gagal mengambil data company.', ['exception' => $exception]);

            return Command::FAILURE;
        }

        [$created, $updated, $reactivated] = $this->upsertTenants($companies);
        $deactivated = $this->deactivateMissingTenants($companies);

        $this->info("Sinkronisasi selesai: {$created} dibuat, {$updated} diperbarui, {$reactivated} diaktifkan kembali, {$deactivated} dinonaktifkan.");
        Log::info('Optigate tenant sync selesai.', [
            'created' => $created,
            'updated' => $updated,
            'reactivated' => $reactivated,
            'deactivated' => $deactivated,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Fetch every company from the Optigate API, following pagination.
     *
     * Transient failures (connection errors, 5xx) are retried with a growing
     * backoff; 401/403 fail fast because retrying them cannot succeed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function fetchAllCompanies(string $url, string $token): Collection
    {
        $companies = collect();
        $page = 1;
        $total = null;

        do {
            $response = $this->fetchPage($url, $token, $page);

            if ($response->unauthorized() || $response->forbidden()) {
                throw new RequestException($response);
            }

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status().': '.Str::limit($response->body(), 300));
            }

            $batch = collect($response->json('data', []));
            $companies = $companies->merge($batch);

            $total = $response->json('meta.total');
            $page++;
        } while ($batch->isNotEmpty() && ($total === null || $companies->count() < $total) && $page <= self::MAX_PAGES);

        return $companies;
    }

    /**
     * Fetch one page of companies, retrying transient failures.
     */
    protected function fetchPage(string $url, string $token, int $page): Response
    {
        $maxAttempts = 3;

        for ($attempt = 1; ; $attempt++) {
            try {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->withOptions(['verify' => (bool) config('services.optigate_portal.verify', true)])
                    ->get($url.'/api/companies', [
                        'paginate' => 'true',
                        'per_page' => self::PER_PAGE,
                        'page' => $page,
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
     * Create or update one tenant per company, keyed by the immutable
     * optigate_company_id. A deactivated tenant whose company is in the API
     * again is reactivated.
     *
     * @param  Collection<int, array<string, mixed>>  $companies
     * @return array{0: int, 1: int, 2: int} created, updated, reactivated
     */
    protected function upsertTenants(Collection $companies): array
    {
        $created = $updated = $reactivated = 0;

        foreach ($companies as $company) {
            $optigateCompanyId = $company['id'] ?? null;
            $code = isset($company['code']) ? Str::lower(trim((string) $company['code'])) : null;
            $name = $company['name'] ?? null;

            if (! $optigateCompanyId || ! $code || ! $name) {
                $this->warn("Melewatkan company tanpa id/code/name (company_id: {$optigateCompanyId}).");
                Log::warning('Optigate tenant sync: company tidak lengkap, dilewati.', ['company_id' => $optigateCompanyId]);

                continue;
            }

            $tenant = Tenant::query()->firstOrNew(['optigate_company_id' => $optigateCompanyId]);
            $isNew = ! $tenant->exists;
            $wasInactive = ! $isNew && ! $tenant->is_active;

            $tenant->fill(['code' => $code, 'name' => $name]);

            if ($wasInactive) {
                $tenant->forceFill(['is_active' => true, 'deactivated_at' => null]);
            }

            if ($isNew) {
                $created++;
            } elseif ($wasInactive) {
                $reactivated++;
            } elseif ($tenant->isDirty()) {
                $updated++;
            }

            $tenant->save();
        }

        return [$created, $updated, $reactivated];
    }

    /**
     * Deactivate (never delete) API-managed tenants whose company is no
     * longer present in the API. Tenants without an optigate_company_id are
     * not managed by this sync and are left untouched.
     *
     * @param  Collection<int, array<string, mixed>>  $companies
     */
    protected function deactivateMissingTenants(Collection $companies): int
    {
        $seenIds = $companies->pluck('id')->filter()->all();

        $missing = Tenant::query()
            ->whereNotNull('optigate_company_id')
            ->where('is_active', true)
            ->when($seenIds !== [], fn (Builder $query) => $query->whereNotIn('optigate_company_id', $seenIds))
            ->get();

        foreach ($missing as $tenant) {
            $tenant->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();
            $this->warn("Tenant dinonaktifkan karena tidak ada lagi di Optigate: {$tenant->code}.");
            Log::warning('Optigate tenant sync: tenant dinonaktifkan.', [
                'tenant_id' => $tenant->id,
                'optigate_company_id' => $tenant->optigate_company_id,
                'code' => $tenant->code,
            ]);
        }

        return $missing->count();
    }
}
