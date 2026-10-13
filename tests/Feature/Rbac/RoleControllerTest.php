<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
});

/** A user holding exactly the given hak-akses permissions. */
function roleManager(array $permissions): User
{
    $user = createUser();
    $user->givePermissionTo($permissions);

    return $user;
}

it('redirects guests away from the roles index', function () {
    $this->get('/settings/roles')->assertRedirect(route('saml.redirect'));
});

it('denies the roles index without role.read', function () {
    $this->actingAs(createUser())
        ->get('/settings/roles')
        ->assertForbidden();
});

it('renders the roles index for a user with role.read', function () {
    $this->actingAs(roleManager(['role.read']))
        ->get('/settings/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/roles/index')
            ->has('roles.data')
            ->where('filters.search', ''));
});

it('renders the roles index for a super admin holding zero permissions', function () {
    $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/settings/roles')
        ->assertOk();
});

it('searches roles by name', function () {
    Role::create(['name' => 'supervisor-gudang', 'guard_name' => 'web']);

    $this->actingAs(roleManager(['role.read']))
        ->get('/settings/roles?search=supervisor')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'supervisor')
            ->has('roles.data', 1));
});

it('stores a role for a user with role.create', function () {
    $this->actingAs(roleManager(['role.create']))
        ->post('/settings/roles', ['name' => 'supervisor-gudang'])
        ->assertRedirect();

    $role = Role::where('name', 'supervisor-gudang')->firstOrFail();

    expect($role->guard_name)->toBe('web');
    expect($role->is_system)->toBeFalse();
});

it('stores a role together with its permissions', function () {
    $this->actingAs(roleManager(['role.create']))
        ->post('/settings/roles', [
            'name' => 'supervisor-gudang',
            'permissions' => ['work-order.read', 'dashboard.read'],
        ])
        ->assertRedirect();

    expect(Role::findByName('supervisor-gudang')->permissions->pluck('name')->all())
        ->toBe(['work-order.read', 'dashboard.read']);
});

it('rejects a duplicate role name', function () {
    $this->actingAs(roleManager(['role.create']))
        ->post('/settings/roles', ['name' => 'hod'])
        ->assertSessionHasErrors('name');
});

it('rejects an unknown permission name with a validation error, not an exception', function () {
    $this->actingAs(roleManager(['role.create']))
        ->post('/settings/roles', [
            'name' => 'supervisor-gudang',
            'permissions' => ['nope.missing'],
        ])
        ->assertSessionHasErrors('permissions.0');
});

it('renames a non-system role', function () {
    $role = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

    $this->actingAs(roleManager(['role.update']))
        ->patch('/settings/roles/'.$role->id, ['name' => 'renamed-role'])
        ->assertRedirect();

    expect($role->fresh()->name)->toBe('renamed-role');
});

it('refuses to rename a system role', function () {
    $hod = Role::findByName('hod');

    $this->actingAs(roleManager(['role.update']))
        ->patch('/settings/roles/'.$hod->id, ['name' => 'kepala-bagian'])
        ->assertSessionHasErrors('name');

    expect($hod->fresh()->name)->toBe('hod');
});

it('allows permission sync on a system role', function () {
    $hod = Role::findByName('hod');

    $this->actingAs(roleManager(['role.update']))
        ->patch('/settings/roles/'.$hod->id, ['name' => 'hod', 'permissions' => ['work-order.read']])
        ->assertRedirect();

    expect($hod->fresh()->permissions->pluck('name')->all())->toBe(['work-order.read']);
});

it('rejects renaming a role to an existing name', function () {
    $role = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

    $this->actingAs(roleManager(['role.update']))
        ->patch('/settings/roles/'.$role->id, ['name' => 'hod'])
        ->assertSessionHasErrors('name');
});

it('deletes a non-system role without users', function () {
    $role = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

    $this->actingAs(roleManager(['role.delete']))
        ->delete('/settings/roles/'.$role->id)
        ->assertRedirect();

    expect(Role::where('name', 'custom-role')->exists())->toBeFalse();
});

it('refuses to delete a system role', function () {
    $hod = Role::findByName('hod');

    $this->actingAs(roleManager(['role.delete']))
        ->delete('/settings/roles/'.$hod->id)
        ->assertSessionHasErrors('role');

    expect(Role::where('name', 'hod')->exists())->toBeTrue();
});

it('refuses to delete a role that still has users', function () {
    $role = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);
    createUser()->assignRole($role);

    $this->actingAs(roleManager(['role.delete']))
        ->delete('/settings/roles/'.$role->id)
        ->assertSessionHasErrors('role');

    expect(Role::where('name', 'custom-role')->exists())->toBeTrue();
});

it('syncs permissions through the dedicated endpoint', function () {
    $role = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

    $this->actingAs(roleManager(['role.update']))
        ->put('/settings/roles/'.$role->id.'/permissions', [
            'permissions' => ['work-order.read', 'dashboard.read'],
        ])
        ->assertRedirect();

    expect($role->fresh()->permissions->pluck('name')->all())
        ->toBe(['work-order.read', 'dashboard.read']);
});

it('refuses to sync permissions on the super_admin role', function () {
    $superAdmin = Role::findByName('super_admin');

    $this->actingAs(roleManager(['role.update']))
        ->put('/settings/roles/'.$superAdmin->id.'/permissions', [
            'permissions' => ['dashboard.read'],
        ])
        ->assertSessionHasErrors('permissions');

    expect($superAdmin->fresh()->permissions)->toHaveCount(0);
});

it('rejects unknown permission names on the sync endpoint', function () {
    $role = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

    $this->actingAs(roleManager(['role.update']))
        ->put('/settings/roles/'.$role->id.'/permissions', [
            'permissions' => ['nope.missing'],
        ])
        ->assertSessionHasErrors('permissions.0');
});
