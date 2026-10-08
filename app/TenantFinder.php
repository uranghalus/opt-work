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