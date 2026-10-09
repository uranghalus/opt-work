<?php

namespace App;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder as BaseFinder;

class TenantFinder extends BaseFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        // 1. Primary: tenant from URL segment (signed switch or direct navigation)
        $routeTenant = $request->route('tenant');

        if ($routeTenant) {
            $tenant = Tenant::find((string) $routeTenant);

            if ($tenant) {
                // Store in session for same-tab convenience
                if ($request->hasSession()) {
                    $request->session()->put('current_tenant_id', $tenant->id);
                }
                return $tenant;
            }

            // If route tenant is present but not found, do not fall back to session
            return null;
        }

        // 2. Fallback: session (for same-tab navigation after initial switch)
        if ($request->hasSession()) {
            $tenantId = $request->session()->get('current_tenant_id');

            if ($tenantId) {
                return Tenant::find((string) $tenantId);
            }
        }

        // 3. Fallback: user's home tenant (only for non-super-admin)
        if ($request->user() && ! $request->user()->is_super_admin && $request->user()->tenant_id) {
            $tenant = Tenant::find((string) $request->user()->tenant_id);

            if ($tenant) {
                if ($request->hasSession()) {
                    $request->session()->put('current_tenant_id', $tenant->id);
                }

                return $tenant;
            }
        }

        return null;
    }
}