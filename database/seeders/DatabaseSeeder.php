<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for the demo environment:
     * tenants → canonical RBAC (roles & permissions — owned by
     * RoleAndPermissionSeeder, not part of the demo dataset) → the full
     * demo dataset (organization, work orders, daily work, notifications).
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
            RoleAndPermissionSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
