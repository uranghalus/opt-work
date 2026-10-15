<?php

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['auth.super_admin_email' => 'superadmin@example.test']);
    (new RoleAndPermissionSeeder)->run();
});

it('restores removed defaults while keeping custom additions', function () {
    $hod = Role::findByName('hod');
    $hod->syncPermissions(['work-order.read', 'reports.read']);

    Artisan::call('app:grant-default-permissions');

    $names = Role::findByName('hod')->permissions->pluck('name')->sort()->values()->all();

    expect($names)->toContain('work-order.review', 'work-order.assign', 'work-order.verify', 'dashboard.read')
        ->and($names)->toContain('reports.read');
});

it('never removes permissions a role already has', function () {
    $hod = Role::findByName('hod');
    $before = $hod->permissions->pluck('name')->sort()->values()->all();

    Artisan::call('app:grant-default-permissions');

    $after = Role::findByName('hod')->permissions->pluck('name')->sort()->values()->all();

    expect($after)->toEqual(array_merge($before, array_diff($after, $before)))
        ->and(count($after))->toBeGreaterThanOrEqual(count($before));
});

it('never touches super_admin', function () {
    Role::findByName('super_admin')->givePermissionTo('dashboard.read');

    Artisan::call('app:grant-default-permissions');

    expect(Role::findByName('super_admin')->permissions->pluck('name')->all())->toBe(['dashboard.read']);
});

it('recreates a deleted unattached catalogue permission', function () {
    expect(Permission::where('name', 'role.read')->exists())->toBeTrue();

    Permission::where('name', 'role.read')->delete();
    Artisan::call('app:grant-default-permissions');

    expect(Permission::where('name', 'role.read')->where('guard_name', 'web')->exists())->toBeTrue();
});

it('is idempotent across repeated runs', function () {
    Artisan::call('app:grant-default-permissions');
    $permissionCount = Permission::count();
    $pivotCount = (int) DB::table('role_has_permissions')->count();

    Artisan::call('app:grant-default-permissions');

    expect(Permission::count())->toBe($permissionCount)
        ->and((int) DB::table('role_has_permissions')->count())->toBe($pivotCount);
});

it('dry-run reports the plan without writing', function () {
    Role::findByName('hod')->syncPermissions(['work-order.read']);

    Artisan::call('app:grant-default-permissions', ['--dry-run' => true]);

    expect(Artisan::output())->toContain('work-order.review')
        ->and(Role::findByName('hod')->permissions->pluck('name')->all())->toBe(['work-order.read']);
});

it('grants the akun defaults to admin_tenant', function () {
    Artisan::call('app:grant-default-permissions');

    $names = Role::findByName('admin_tenant')->permissions->pluck('name')->all();

    expect($names)->toContain('akun.read', 'akun.update', 'akun.create', 'akun.delete');
});

it('is self-sufficient on a database without the seeder', function () {
    // Fresh state: drop what the seeder created, keep the users table intact.
    foreach (Role::all() as $role) {
        $role->delete();
    }
    Permission::query()->delete();

    Artisan::call('app:grant-default-permissions');

    expect(Role::where('guard_name', 'web')->count())->toBe(count(RoleAndPermissionSeeder::SYSTEM_ROLES))
        ->and(Role::findByName('hod')->hasPermissionTo('work-order.assign'))->toBeTrue()
        ->and(Role::findByName('admin_tenant')->hasPermissionTo('tenant.delete'))->toBeTrue();
});
