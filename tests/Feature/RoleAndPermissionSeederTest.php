<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['auth.super_admin_email' => 'superadmin@example.test']);
});

it('seeds the hak akses permissions with the read vocabulary', function () {
    (new RoleAndPermissionSeeder)->run();

    foreach (['role.read', 'role.create', 'role.update', 'role.delete', 'permission.read', 'permission.create', 'permission.update', 'permission.delete'] as $name) {
        expect(Permission::where('name', $name)->where('guard_name', 'web')->exists())->toBeTrue(
            "Permission {$name} should be seeded"
        );
    }
});

it('does not seed the removed manage permissions', function () {
    (new RoleAndPermissionSeeder)->run();

    expect(Permission::where('name', 'rbac.manage')->exists())->toBeFalse();
    expect(Permission::where('name', 'tenant.manage')->exists())->toBeFalse();
});

it('creates exactly the eleven canonical system roles', function () {
    (new RoleAndPermissionSeeder)->run();

    expect(Role::where('is_system', true)->pluck('name')->sort()->values()->all())->toBe(
        collect(['super_admin', 'admin_tenant', 'general_manager', 'deputy_general_manager', 'hod', 'staf_admin_dept', 'team_leader', 'karyawan', 'field_staff', 'viewer', 'guest'])
            ->sort()
            ->values()
            ->all()
    );
});

it('leaves super_admin with zero database permissions', function () {
    (new RoleAndPermissionSeeder)->run();

    expect(Role::findByName('super_admin')->permissions)->toHaveCount(0);
});

it('creates guest as a system role with zero permissions', function () {
    (new RoleAndPermissionSeeder)->run();

    $guest = Role::findByName('guest');

    expect($guest->is_system)->toBeTruthy()
        ->and($guest->permissions)->toHaveCount(0);
});

it('gives karyawan the submit default but not field_staff', function () {
    (new RoleAndPermissionSeeder)->run();

    expect(Role::findByName('karyawan')->hasPermissionTo('work-order.submit'))->toBeTrue();
    expect(Role::findByName('field_staff')->hasPermissionTo('work-order.submit'))->toBeFalse();
});

it('syncs defaults only on first creation and preserves customizations on re-run', function () {
    (new RoleAndPermissionSeeder)->run();

    Role::findByName('hod')->syncPermissions(['work-order.read']);

    (new RoleAndPermissionSeeder)->run();

    expect(Role::findByName('hod')->permissions->pluck('name')->all())->toBe(['work-order.read']);
});

it('marks is_system idempotently and never marks custom roles', function () {
    (new RoleAndPermissionSeeder)->run();
    Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

    (new RoleAndPermissionSeeder)->run();

    expect((bool) Role::where('name', 'hod')->value('is_system'))->toBeTrue();
    expect((bool) Role::where('name', 'custom-role')->value('is_system'))->toBeFalse();
});

it('adopts a pre-existing role name deterministically without granting defaults', function () {
    Role::create(['name' => 'hod', 'guard_name' => 'web']);

    (new RoleAndPermissionSeeder)->run();

    expect(Role::where('name', 'hod')->count())->toBe(1);
    expect((bool) Role::where('name', 'hod')->value('is_system'))->toBeTrue();
    expect(Role::findByName('hod')->permissions)->toHaveCount(0);
});

it('is idempotent across repeated runs', function () {
    (new RoleAndPermissionSeeder)->run();
    $permissionCount = Permission::count();
    $roleCount = Role::count();

    (new RoleAndPermissionSeeder)->run();

    expect(Permission::count())->toBe($permissionCount);
    expect(Role::count())->toBe($roleCount);
});

it('bootstraps the super admin user with the is_super_admin flag', function () {
    (new RoleAndPermissionSeeder)->run();

    expect(User::where('email', 'superadmin@example.test')->where('is_super_admin', true)->exists())->toBeTrue();
});

it('fails loudly when auth.super_admin_email is empty', function () {
    config(['auth.super_admin_email' => null]);

    (new RoleAndPermissionSeeder)->run();
})->throws(RuntimeException::class);
