<?php

namespace App\Http\Middleware;

use App\TenantFinder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;

class IdentifyTenant
{
    public function __construct(
        protected TenantFinder $tenantFinder,
        protected MakeTenantCurrentAction $makeTenantCurrentAction,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $tenant = $this->tenantFinder->findForRequest($request);

            if ($tenant) {
                $this->makeTenantCurrentAction->execute($tenant);
            }
        } catch (\Throwable $e) {
            Log::warning('IdentifyTenant: '.$e->getMessage());
        }

        return $next($request);
    }
}
