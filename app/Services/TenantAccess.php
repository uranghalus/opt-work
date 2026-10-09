<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TenantAccess
{
    public function canOperate(User $user, ?string $tenantId): bool
    {
        if (! $tenantId) {
            return false;
        }

        if ($user->is_super_admin) {
            return true;
        }

        // tenantId can be either the ULID (from session/user) or code (from route)
        // Resolve to ULID for comparison
        $tenant = Tenant::where('id', $tenantId)
            ->orWhere('code', $tenantId)
            ->first();

        if (! $tenant) {
            return false;
        }

        return $user->tenant_id === $tenant->id;
    }

    public function operableTenants(User $user): Collection
    {
        if ($user->is_super_admin) {
            return Tenant::query()->get();
        }

        if (! $user->tenant_id) {
            return Tenant::query()->whereRaw('1 = 0')->get();
        }

        return Tenant::query()->where('id', $user->tenant_id)->get();
    }

    public function canManageTenants(User $user): bool
    {
        return (bool) $user->is_super_admin;
    }
}