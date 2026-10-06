<?php

namespace App\Http\Middleware;

use App\Services\TenantAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform-level gate: branch record management only.
 *
 * Narrower than {@see EnsureTenantAccess}, which asks "may
 * this user act inside this branch". This asks "may this user administer the set
 * of branches" — branch administrators manage their own branch's data but not the
 * branch record itself.
 */
class EnsurePlatformTenantAccess
{
    public function __construct(private TenantAccess $tenantAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->tenantAccess->canManageTenants($user)) {
            abort(403);
        }

        return $next($request);
    }
}
