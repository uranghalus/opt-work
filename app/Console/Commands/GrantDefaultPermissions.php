<?php

namespace App\Console\Commands;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Per-release RBAC step (ADR-0004). The seeder syncs role defaults only on
 * first role creation, so permissions added to the catalogue in a later
 * release never reach existing roles. This command closes that gap:
 *
 * - Creates any catalogue permission that is missing (same firstOrCreate
 *   semantics as the seeder — never renames, never deletes).
 * - Provisions the nine canonical roles if absent and keeps their is_system
 *   invariant (same as the seeder).
 * - Grants each system role the defaults it is missing — additive-only:
 *   Hak Akses customizations are preserved and nothing is ever detached.
 * - Never grants super_admin anything: its Gate bypass makes DB permissions
 *   decorative, so they stay empty by design.
 *
 * Safe to run any time; idempotent (a second run changes nothing). Use
 * --dry-run to preview the plan without writing.
 */
#[Signature('app:grant-default-permissions {--dry-run : Tampilkan rencana tanpa mengubah data.}')]
#[Description('Berikan izin default yang belum dimiliki peran sistem (hanya menambah, tidak pernah mencabut). Langkah per-release setelah katalog izin bertambah.')]
class GrantDefaultPermissions extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $createdPermissions = $this->ensureCatalogue($dryRun);
            [$grantedTotal, $touchedRoles] = $this->grantMissingDefaults($dryRun);
        } catch (Throwable $exception) {
            $this->error('Gagal memberikan izin default: '.$exception->getMessage());

            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: tidak ada perubahan yang ditulis.');
        } else {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->info("Selesai: {$createdPermissions} izin baru dibuat, {$grantedTotal} izin default diberikan ke {$touchedRoles} peran.");

        return Command::SUCCESS;
    }

    /**
     * Ensure every catalogue permission exists; report the ones created.
     *
     * @return int Number of permissions this run would create.
     */
    private function ensureCatalogue(bool $dryRun): int
    {
        $created = 0;

        foreach (RoleAndPermissionSeeder::catalogue() as $name) {
            $exists = Permission::query()
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists();

            if ($exists) {
                continue;
            }

            $created++;

            if (! $dryRun) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }

            $this->line("  izin baru: {$name}");
        }

        return $created;
    }

    /**
     * Grant each system role the default permissions it is missing.
     * Additive-only: existing (customized) permissions are never detached.
     *
     * @return array{0: int, 1: int} Total permissions granted, roles touched.
     */
    private function grantMissingDefaults(bool $dryRun): array
    {
        $grantedTotal = 0;
        $touchedRoles = 0;

        foreach (RoleAndPermissionSeeder::roleDefaults() as $roleName => $defaults) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            // Canonical roles are system roles (same invariant the seeder
            // enforces on every run) — never deletable via Hak Akses.
            if (! $role->is_system) {
                $role->forceFill(['is_system' => true])->save();
            }

            if ($roleName === 'super_admin') {
                $this->line('  lewati super_admin: izin DB dekoratif, bypass gate adalah satu-satunya sumber kekuasaannya (ADR-0004)');

                continue;
            }

            $existing = $role->permissions->pluck('name')->all();
            $missing = array_values(array_diff($defaults, $existing));

            if ($missing === []) {
                continue;
            }

            $grantedTotal += count($missing);
            $touchedRoles++;

            if (! $dryRun) {
                $role->givePermissionTo($missing);
            }

            $this->line("  {$roleName}: +".count($missing).' ('.implode(', ', $missing).')');
        }

        return [$grantedTotal, $touchedRoles];
    }
}
