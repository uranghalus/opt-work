<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Stancl\Tenancy\Database\Models\Tenant;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
        ]);

        $hq = Tenant::find('hq');

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'tenant_id' => $hq?->getKey(),
        ]);

        // Assigns super_admin to admin@example.com created above.
        $this->call([
            RoleAndPermissionSeeder::class,
        ]);
    }
}
