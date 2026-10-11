<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

/**
 * Computes "the same page for another tenant".
 *
 * Given the current route name + parameters and the target tenant, this
 * service owns every rule of tenant switching:
 *  - record-level pages (show/edit/update/destroy) map to their parent list,
 *    because the record belongs to the old tenant;
 *  - the query string is never carried over (filters belong to old data);
 *  - anything unknown, non-tenant-scoped, or unbuildable falls back to the
 *    target tenant's dashboard.
 *
 * @return array{path: string, fell_back: bool} via resolve()
 */
class TenantSwitchTarget
{
    /** Route-name suffixes that address a single record of the old tenant. */
    private const RECORD_VERBS = ['show', 'edit', 'update', 'destroy'];

    /**
     * @param  array<string, string|int>  $routeParams  current route params (models already reduced to route keys)
     * @return array{path: string, fell_back: bool}
     */
    public function resolve(string $newTenantCode, ?string $routeName, array $routeParams): array
    {
        $tenant = Tenant::whereCode($newTenantCode)->first();

        if (! $tenant) {
            abort(404, 'Cabang tidak ditemukan.');
        }

        $targetName = $this->samePageRouteName($routeName);

        if ($targetName === null) {
            return $this->dashboard($tenant, fellBack: true);
        }

        $params = $this->targetParams($routeName, $targetName, $routeParams, $tenant->code);

        try {
            $path = route($targetName, $params, absolute: false);
        } catch (\Throwable) {
            // The target page needs a parameter the old page didn't carry —
            // the page effectively does not exist for the target tenant.
            return $this->dashboard($tenant, fellBack: true);
        }

        return ['path' => $path, 'fell_back' => false];
    }

    /**
     * The route name to land on, or null when the current page has no
     * equivalent for another tenant (non-tenant page, unknown route, or a
     * record-level page whose parent list does not exist).
     */
    private function samePageRouteName(?string $routeName): ?string
    {
        if ($routeName === null || ! Route::has($routeName)) {
            return null;
        }

        $route = Route::getRoutes()->getByName($routeName);

        if (! $route || ! str_starts_with($route->uri(), '{tenant}/')) {
            return null;
        }

        $verb = substr($routeName, (int) strrpos($routeName, '.') + 1);

        if (in_array($verb, self::RECORD_VERBS, true)) {
            $parentName = substr($routeName, 0, strrpos($routeName, '.')).'.index';

            return $this->tenantScoped($parentName) ? $parentName : null;
        }

        return $routeName;
    }

    private function tenantScoped(string $routeName): bool
    {
        $route = Route::getRoutes()->getByName($routeName);

        return $route !== null && str_starts_with($route->uri(), '{tenant}/');
    }

    /**
     * Parameters for the target route: the new tenant code, plus the old
     * route's non-record parameters. Record-level mappings restart clean —
     * every record identifier belongs to the old tenant.
     *
     * @param  array<string, string|int>  $routeParams
     * @return array<string, string|int>
     */
    private function targetParams(string $originalName, string $targetName, array $routeParams, string $code): array
    {
        if ($targetName !== $originalName) {
            return ['tenant' => $code];
        }

        return ['tenant' => $code] + collect($routeParams)
            ->except(['tenant'])
            ->all();
    }

    /**
     * @return array{path: string, fell_back: bool}
     */
    private function dashboard(Tenant $tenant, bool $fellBack): array
    {
        return [
            'path' => route('dashboard', ['tenant' => $tenant->code], absolute: false),
            'fell_back' => $fellBack,
        ];
    }
}
