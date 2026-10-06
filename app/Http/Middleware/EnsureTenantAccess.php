<?php

namespace App\Http\Middleware;

use App\Services\TenantAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function __construct(private TenantAccess $tenantAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = tenant();

        if ($user === null || $tenant === null) {
            return $next($request);
        }

        if ($this->tenantAccess->canOperate($user, $tenant->getTenantKey())) {
            return $next($request);
        }

        Log::warning('Tenant access denied.', [
            'user_id' => $user->getKey(),
            'tenant_id' => $tenant->getTenantKey(),
            'url' => $request->fullUrl(),
        ]);

        abort(403);
    }
}
