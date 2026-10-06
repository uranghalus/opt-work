<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $masterDataPermissions = [
            'division.create', 'division.read', 'division.update', 'division.delete',
            'department.create', 'department.read', 'department.update', 'department.delete',
            'employee.create', 'employee.read', 'employee.update', 'employee.delete',
            'tenant.create', 'tenant.read', 'tenant.update', 'tenant.delete',
        ];

        $workOrderPermissions = [
            'work-order.create', 'work-order.read', 'work-order.update', 'work-order.delete',
            'work-order.review', 'work-order.assign', 'work-order.submit', 'work-order.verify',
        ];

        $workPlanningPermissions = [
            'work-planning.create', 'work-planning.read', 'work-planning.update', 'work-planning.delete',
        ];

        $workDataPermissions = [
            'work-data.create', 'work-data.read', 'work-data.update', 'work-data.delete',
        ];

        $dailyWorkPermissions = [
            'daily-work.create', 'daily-work.read', 'daily-work.update',
        ];

        $systemPermissions = [
            'rbac.manage', 'tenant.manage', 'dashboard.read', 'reports.read',
        ];

        $allPermissions = array_merge(
            $masterDataPermissions,
            $workOrderPermissions,
            $workPlanningPermissions,
            $workDataPermissions,
            $dailyWorkPermissions,
            $systemPermissions,
        );

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $readMasterData = fn (array $permissions) => array_map(
            fn (string $p) => str_replace(['.create', '.update', '.delete'], '.read', $p),
            $permissions,
        );

        // Super Admin
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($allPermissions);
        User::where('email', 'admin@example.com')->first()?->assignRole('super_admin');

        // Akun superadmin operasional. Akses penuh datang dari role ini
        // (Gate::before di AppServiceProvider), bukan dari hardcode email.
        $superadmin = User::where('email', 'superadmin@appdutamall.com')->first()
            ?? User::factory()->create([
                'name' => 'Super Admin',
                'email' => 'superadmin@appdutamall.com',
            ]);
        $superadmin->assignRole($superAdmin);

        // Admin Tenant / Cabang
        $adminTenant = Role::firstOrCreate(['name' => 'admin_tenant', 'guard_name' => 'web']);
        $adminTenant->syncPermissions(array_merge(
            $masterDataPermissions,
            $workOrderPermissions,
            $workPlanningPermissions,
            $workDataPermissions,
            $dailyWorkPermissions,
            ['dashboard.read', 'reports.read'],
        ));

        // General Manager
        $gm = Role::firstOrCreate(['name' => 'general_manager', 'guard_name' => 'web']);
        $gm->syncPermissions(array_merge(
            $readMasterData($masterDataPermissions),
            ['work-order.read', 'work-order.review', 'work-order.verify'],
            $workPlanningPermissions,
            $workDataPermissions,
            $dailyWorkPermissions,
            ['dashboard.read', 'reports.read'],
        ));

        // Deputy General Manager
        $dgm = Role::firstOrCreate(['name' => 'deputy_general_manager', 'guard_name' => 'web']);
        $dgm->syncPermissions($gm->permissions->pluck('name')->toArray());

        // Head of Department
        $hod = Role::firstOrCreate(['name' => 'hod', 'guard_name' => 'web']);
        $hod->syncPermissions(array_merge(
            ['division.read', 'department.read', 'department.update', 'employee.read'],
            ['work-order.read', 'work-order.review', 'work-order.assign', 'work-order.verify'],
            $workPlanningPermissions,
            $workDataPermissions,
            $dailyWorkPermissions,
            ['dashboard.read', 'reports.read'],
        ));

        // Team Leader
        $teamLeader = Role::firstOrCreate(['name' => 'team_leader', 'guard_name' => 'web']);
        $teamLeader->syncPermissions(array_merge(
            ['division.read', 'department.read', 'employee.read'],
            ['work-order.read', 'work-order.review', 'work-order.assign'],
            $workDataPermissions,
            $dailyWorkPermissions,
            ['dashboard.read'],
        ));

        // Karyawan (Requester capability — bukan role eksklusif)
        $karyawan = Role::firstOrCreate(['name' => 'karyawan', 'guard_name' => 'web']);
        $karyawan->syncPermissions(array_merge(
            ['division.read', 'department.read', 'employee.read'],
            ['work-order.create', 'work-order.read', 'work-order.submit'],
            $workDataPermissions,
            ['daily-work.read', 'daily-work.update'],
            ['dashboard.read'],
        ));

        // Field Staff (Karyawan Pelaksana)
        $fieldStaff = Role::firstOrCreate(['name' => 'field_staff', 'guard_name' => 'web']);
        $fieldStaff->syncPermissions(array_merge(
            ['division.read', 'department.read', 'employee.read'],
            ['work-order.read', 'work-order.submit'],
            $workDataPermissions,
            $dailyWorkPermissions,
            ['dashboard.read'],
        ));

        // Viewer / Auditor
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions(array_merge(
            $readMasterData($masterDataPermissions),
            $readMasterData($workOrderPermissions),
            $readMasterData($workPlanningPermissions),
            $readMasterData($workDataPermissions),
            $readMasterData($dailyWorkPermissions),
            ['dashboard.read', 'reports.read'],
        ));
    }
}
