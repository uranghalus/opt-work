<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
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

        // The URL segment is the source of truth: a segment that resolves to
        // no tenant is an unknown page (404), never a tenant-less render —
        // those pages would crash every tenant route helper in the browser.
        if (! Tenant::whereCode($tenantCode)->exists()) {
            abort(404, 'Cabang tidak ditemukan.');
        }

        if (! $this->tenantAccess->canOperate($user, $tenantCode)) {
            abort(403);
        }

        return $next($request);
    }
}
