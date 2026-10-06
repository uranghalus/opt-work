<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The single authority on which branches a user may act within.
 *
 * Authorization in OptiWorks is tenant-level, not permission-level: any
 * authenticated user may perform any action inside their own branch, and
 * cross-branch work requires an explicit flag. This class is deliberately the
 * ONLY place that answers that question — middleware, controllers, and the UI
 * all defer here.
 *
 * That constraint is what keeps the deferred RBAC work small. When Spatie
 * permission returns, `canOperate()` and `operableTenants()` become the only
 * methods that need to change; callers are unaffected. See
 * `.scratch/tenancy-reconfig/issues/02`.
 */
class TenantAccess
{
    /**
     * May this user act within this branch?
     *
     * A user always operates their home branch. Platform-level users
     * (`is_super_admin`) operate any branch.
     */
    public function canOperate(User $user, ?string $tenantId): bool
    {
        if ($tenantId === null) {
            return false;
        }

        if ($user->is_super_admin) {
            return true;
        }

        return $user->tenant_id !== null && (string) $user->tenant_id === (string) $tenantId;
    }

    /**
     * Branches this user may switch into.
     *
     * A platform-level user sees every branch; everyone else sees exactly their
     * home branch. Consumers must not treat this as a display concern — it is
     * the same set `canOperate()` enforces, so a branch missing here is one the
     * user cannot reach even by hand-typing its URL.
     *
     * @return Collection<int, Tenant>
     */
    public function operableTenants(User $user): Collection
    {
        if ($user->is_super_admin) {
            return Tenant::query()->orderBy('name')->get();
        }

        if ($user->tenant_id === null) {
            return collect();
        }

        return Tenant::query()->whereKey($user->tenant_id)->get();
    }

    /**
     * May this user manage branch records themselves (create, edit, archive)?
     *
     * Branch administrators administer their own branch's data but not the
     * branch record itself, so this is narrower than {@see canOperate()}.
     */
    public function canManageTenants(User $user): bool
    {
        return (bool) $user->is_super_admin;
    }
}
