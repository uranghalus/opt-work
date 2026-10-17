<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * One-off, idempotent repair for existing databases (`.scratch/guest-role`
 * issue 03). The diagnosis: the platform account had its `super_admin` role
 * but was never linked to its karyawan record, so the Karyawan page reported
 * "Belum ada akun yang terhubung".
 *
 * Guarantees: additive only — no deletes, no truncates, never touches
 * tenant_id, never overwrites an existing (or conflicting) employee link.
 * `--dry-run` prints the plan and writes nothing.
 */
class GrantSuperAdminRole extends Command
{
    protected $signature = 'app:grant-super-admin-role {--dry-run : Show the plan without writing}';

    protected $description = 'Ensure the platform account has the super_admin role, the is_super_admin flag, and a karyawan link (idempotent)';

    public function handle(): int
    {
        $email = config('auth.super_admin_email');

        if (! is_string($email) || $email === '') {
            $this->error('config(auth.super_admin_email) is empty: nothing to repair.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->line($dryRun ? "[dry-run] {$email}" : "Repairing {$email}");

        $user = User::where('email', $email)->first()
            ?? User::factory()->create([
                'name' => 'Super Admin',
                'email' => $email,
            ]);

        $this->line('  account: '.$user->email.' (id '.$user->getKey().')');

        $this->ensureRole($user, $dryRun);
        $this->ensureFlag($user, $dryRun);
        $this->ensureEmployeeLink($user, $dryRun);

        if ($dryRun) {
            $this->line('[dry-run] nothing was written.');
        }

        return self::SUCCESS;
    }

    private function ensureRole(User $user, bool $dryRun): void
    {
        if ($user->roles()->where('name', 'super_admin')->exists()) {
            $this->line('  role: super_admin already assigned');

            return;
        }

        if ($dryRun) {
            $this->line('  role: would assign super_admin');

            return;
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole('super_admin');
        $this->line('  role: assigned super_admin');
    }

    private function ensureFlag(User $user, bool $dryRun): void
    {
        if ($user->is_super_admin) {
            $this->line('  flag: is_super_admin already set');

            return;
        }

        if ($dryRun) {
            $this->line('  flag: would set is_super_admin');

            return;
        }

        $user->forceFill(['is_super_admin' => true])->save();
        $this->line('  flag: set is_super_admin');
    }

    private function ensureEmployeeLink(User $user, bool $dryRun): void
    {
        if ($user->employee_id !== null) {
            $this->line('  link: already linked to employee '.$user->employee_id);

            return;
        }

        // WithoutGlobalScope: the command runs without a current tenant and
        // the platform account is not tenant-scoped by definition.
        $employee = Employee::withoutGlobalScope('tenant')
            ->where('email', $user->email)
            ->first();

        if ($employee === null) {
            $this->warn('  link: no karyawan record with this email — skipped');

            return;
        }

        if ($dryRun) {
            $this->line('  link: would link employee '.$employee->getKey());

            return;
        }

        $user->forceFill(['employee_id' => $employee->getKey()])->save();
        $this->line('  link: linked employee '.$employee->getKey());
    }
}
