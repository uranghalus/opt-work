<?php

namespace App\Http\Middleware;

use App\Services\TenantAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function __construct(
        protected TenantAccess $tenantAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Route parameter 'tenant' is the code (slug)
        $tenantCode = $request->route('tenant');
        $user = $request->user();

        if (! $tenantCode || ! $user) {
            return $next($request);
        }

        if (! $this->tenantAccess->canOperate($user, $tenantCode)) {
            abort(403);
        }

        return $next($request);
    }
}