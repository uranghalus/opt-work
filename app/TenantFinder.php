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
        // Prefer tenant from URL segment per Spatie v4 URL-based resolution
        $routeTenant = $request->route('tenant');

        if ($routeTenant) {
            $tenant = Tenant::find((string) $routeTenant);
            if ($tenant) {
                if ($request->hasSession()) {
                    $request->session()->put('current_tenant_id', $tenant->id);
                }
                return $tenant;
            }
            // If route tenant is present but not found, do not fall back to session
            return null;
        }

        if (! $request->hasSession()) {
            return null;
        }

        $tenantId = $request->session()->get('current_tenant_id');

        if ($tenantId) {
            return Tenant::find((string) $tenantId);
        }

        if ($request->user()?->tenant_id) {
            $tenant = Tenant::find((string) $request->user()->tenant_id);

            if ($tenant) {
                $request->session()->put('current_tenant_id', $tenant->id);

                return $tenant;
            }
        }

        return null;
    }
}