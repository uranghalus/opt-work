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
        $tenant = $request->route('tenant');
        $user = $request->user();

        if (! $tenant || ! $user) {
            return $next($request);
        }

        if (! $this->tenantAccess->canOperate($user, $tenant)) {
            abort(403);
        }

        return $next($request);
    }
}