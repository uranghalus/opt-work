<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Role bootstrap at SSO login (ADR-0006).
 *
 * Two rules, in this order, both inside one transaction and both idempotent:
 *
 * 1. The platform account (config `auth.super_admin_email`, env-fed — never
 *    an email literal in code) is self-healed: the `super_admin` role, the
 *    `is_super_admin` flag (which backs the platform surfaces
 *    TenantAccess/EnsurePlatformTenantAccess, while the role backs
 *    Gate::before — ADR-0004) and the link to the karyawan carrying the same
 *    email when the account is unlinked. `tenant_id` is never touched.
 * 2. Any other account with no role at all and no linked karyawan (Akun)
 *    gets the zero-permission `guest` role exactly once. Until the super
 *    admin grants real roles through Hak Akses → Peran Akun, such an account
 *    can do nothing. The guard is state-based ("no role, no link"), not
 *    creation-based: it is fail-closed — an account whose roles were all
 *    wiped re-lands as guest rather than being denied at login.
 *
 * The platform check runs first: a brand-new platform account has neither
 * flag nor role yet, so a role-based check alone would leak `guest` onto it.
 */
class LoginProvisioningService
{
    private const SUPER_ADMIN_ROLE = 'super_admin';

    private const GUEST_ROLE = 'guest';

    public function provision(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->ensurePlatformSuperAdmin($user);
            $this->ensureGuestRole($user);
        });

        $user->refresh();
    }

    private function ensurePlatformSuperAdmin(User $user): void
    {
        if (! $this->isPlatformAccount($user->email)) {
            return;
        }

        Role::firstOrCreate([
            'name' => self::SUPER_ADMIN_ROLE,
            'guard_name' => 'web',
        ]);

        $user->assignRole(self::SUPER_ADMIN_ROLE);

        if (! $user->is_super_admin) {
            $user->forceFill(['is_super_admin' => true])->save();
        }

        if ($user->employee_id !== null) {
            return;
        }

        $employee = Employee::withEmailAnyTenant($user->email);

        if ($employee !== null) {
            $user->forceFill(['employee_id' => $employee->getKey()])->save();
        }
    }

    private function ensureGuestRole(User $user): void
    {
        if ($this->isPlatformAccount($user->email)) {
            return;
        }

        if ($user->isSuperAdmin() || $user->employee_id !== null || $user->roles()->exists()) {
            return;
        }

        if (! Role::where('name', self::GUEST_ROLE)->where('guard_name', 'web')->exists()) {
            Log::critical('Login provisioning: the guest role is missing — run db:seed.', [
                'user_id' => $user->getKey(),
            ]);

            return;
        }

        $user->assignRole(self::GUEST_ROLE);
    }

    private function isPlatformAccount(string $email): bool
    {
        $configured = config('auth.super_admin_email');

        return is_string($configured)
            && $configured !== ''
            && strcasecmp($configured, $email) === 0;
    }
}
