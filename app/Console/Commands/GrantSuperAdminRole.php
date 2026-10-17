<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Per-release repair step for the platform account (ADR-0006). The
 * diagnosis behind this command: the account had its `super_admin` role but
 * was never linked to its karyawan record, so the Karyawan page reported
 * "Belum ada akun yang terhubung".
 *
 * - Additive only: no deletes, no truncates, never touches tenant_id, never
 *   overwrites an existing (or conflicting) employee link.
 * - Idempotent: safe to run any time; a second run changes nothing.
 * - --dry-run prints the plan without writing.
 */
#[Signature('app:grant-super-admin-role {--dry-run : Tampilkan rencana tanpa mengubah data.}')]
#[Description('Pastikan akun platform memiliki peran super_admin, flag is_super_admin, dan tautan karyawan (idempoten, hanya menambah).')]
class GrantSuperAdminRole extends Command
{
    public function handle(): int
    {
        $email = config('auth.super_admin_email');

        if (! is_string($email) || $email === '') {
            $this->error('config(auth.super_admin_email) kosong: tidak ada akun untuk diperbaiki.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->line($dryRun ? "[dry-run] {$email}" : "Memperbaiki {$email}");

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error('Akun platform belum ada: jalankan db:seed lebih dulu.');

            return self::FAILURE;
        }

        $this->line('  akun: '.$user->email.' (id '.$user->getKey().')');

        $this->ensureRole($user, $dryRun);
        $this->ensureFlag($user, $dryRun);
        $this->ensureEmployeeLink($user, $dryRun);

        if ($dryRun) {
            $this->line('[dry-run] tidak ada data yang diubah.');
        }

        return self::SUCCESS;
    }

    private function ensureRole(User $user, bool $dryRun): void
    {
        if ($user->roles()->where('name', 'super_admin')->exists()) {
            $this->line('  peran: super_admin sudah ada');

            return;
        }

        if ($dryRun) {
            $this->line('  peran: akan memberikan super_admin');

            return;
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole('super_admin');
        $this->line('  peran: super_admin diberikan');
    }

    private function ensureFlag(User $user, bool $dryRun): void
    {
        if ($user->is_super_admin) {
            $this->line('  flag: is_super_admin sudah aktif');

            return;
        }

        if ($dryRun) {
            $this->line('  flag: akan mengaktifkan is_super_admin');

            return;
        }

        $user->forceFill(['is_super_admin' => true])->save();
        $this->line('  flag: is_super_admin diaktifkan');
    }

    private function ensureEmployeeLink(User $user, bool $dryRun): void
    {
        if ($user->employee_id !== null) {
            $this->line('  tautan: sudah tertaut ke karyawan '.$user->employee_id);

            return;
        }

        $employee = Employee::withEmailAnyTenant($user->email);

        if ($employee === null) {
            $this->warn('  tautan: tidak ada karyawan dengan email ini — dilewati');

            return;
        }

        if ($dryRun) {
            $this->line('  tautan: akan menautkan karyawan '.$employee->getKey());

            return;
        }

        $user->forceFill(['employee_id' => $employee->getKey()])->save();
        $this->line('  tautan: karyawan '.$employee->getKey().' ditautkan');
    }
}
