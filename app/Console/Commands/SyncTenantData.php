<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

#[Signature('app:sync-tenant-data {--tenant= : ID atau kode tenant (default: semua tenant)}')]
#[Description('Sinkronisasi data tenant: departments, divisions, positions, employees, lalu resolusi HOD.')]
class SyncTenantData extends Command
{
    /**
     * Child commands are ordered by dependency. Departments run twice: the
     * first pass creates the rows employees need, the final pass resolves HOD
     * after employees exist.
     */
    protected const SYNC_STEPS = [
        ['app:sync-departments', 'departments (create/update)'],
        ['app:sync-divisions', 'divisions'],
        ['app:sync-positions', 'positions'],
        ['app:sync-employees', 'employees'],
        ['app:sync-departments', 'departments (HOD resolution)'],
    ];

    public function handle(): int
    {
        $tenantKey = $this->option('tenant');

        $tenants = Tenant::query()
            ->when($tenantKey, fn ($query, $key) => $query->where(
                fn ($nested) => $nested->where('id', $key)->orWhere('code', $key)
            ))
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('Tidak ada tenant ditemukan.');

            return self::SUCCESS;
        }

        $errors = [];

        foreach ($tenants as $tenant) {
            $this->info("Memproses tenant {$tenant->code} ({$tenant->name})");

            foreach (self::SYNC_STEPS as [$command, $label]) {
                $exitCode = Artisan::call($command, ['--tenant' => $tenant->id]);

                if ($exitCode !== Command::SUCCESS) {
                    $errors[] = "{$label} gagal untuk tenant {$tenant->code} (exit {$exitCode}).";
                }
            }
        }

        if (class_exists('Database\\Seeders\\RoleAndPermissionSeeder')) {
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);
        }

        $email = config('auth.super_admin_email');
        $user = User::whereRaw('LOWER(email)=LOWER(?)', [$email])->first();

        if ($user) {
            $role = Role::where('name', 'super_admin')->first();

            if ($role) {
                $user->syncRoles([$role]);
                $this->info("Menetapkan role super_admin untuk {$email}");
            }
        } else {
            $this->warn("User superadmin dengan email {$email} tidak ditemukan.");
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Sinkronisasi tenant data selesai.');

        return self::SUCCESS;
    }
}
