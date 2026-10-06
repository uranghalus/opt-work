<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function makeRbacManager(): User
{
    $user = createUser();

    givePermission($user, 'rbac.manage');

    return $user;
}

test('guests are redirected to the SSO portal', function () {
    $this->get('/settings/roles')->assertRedirect(route('saml.redirect'));
});

test('users without rbac.manage cannot access role management', function () {
    $user = createUser();

    $this->actingAs($user)->get('/settings/roles')->assertForbidden();

    $this->actingAs($user)
        ->post('/settings/roles', ['name' => 'x', 'permissions' => []])
        ->assertForbidden();
});

test('users with rbac.manage can view the role management page', function () {
    $manager = makeRbacManager();

    Role::firstOrCreate(['name' => 'hod', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rbac.manage', 'guard_name' => 'web']);

    $this->actingAs($manager)
        ->get('/settings/roles')
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('settings/roles')
                ->has('roles')
                ->has('permissions.rbac');
        });
});

test('the super admin role bypasses the rbac.manage permission check', function () {
    // super_admin carries no explicit permissions here — only the
    // Gate::before bypass in AppServiceProvider grants access.
    $admin = makeSuperAdmin(createUser());

    $this->actingAs($admin)->get('/settings/roles')->assertOk();
});

test('creating a role syncs its permissions', function () {
    Permission::firstOrCreate(['name' => 'work-order.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'work-order.read', 'guard_name' => 'web']);

    $manager = makeRbacManager();

    $response = $this
        ->actingAs($manager)
        ->post('/settings/roles', [
            'name' => 'supervisor',
            'permissions' => ['work-order.create', 'work-order.read'],
        ]);

    $response
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('success');

    $role = Role::where('name', 'supervisor')->first();

    expect($role)->not->toBeNull();
    expect($role->guard_name)->toBe('web');
    expect($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['work-order.create', 'work-order.read']);
});

test('creating a role with a duplicate name fails validation', function () {
    Role::firstOrCreate(['name' => 'hod', 'guard_name' => 'web']);

    $manager = makeRbacManager();

    $this->actingAs($manager)
        ->post('/settings/roles', ['name' => 'hod', 'permissions' => []])
        ->assertSessionHasErrors('name');
});

test('assigning an unknown permission fails validation', function () {
    $manager = makeRbacManager();

    $this->actingAs($manager)
        ->post('/settings/roles', ['name' => 'ghost', 'permissions' => ['not.a.permission']])
        ->assertSessionHasErrors('permissions.*');
});

test('updating a role renames it and re-syncs its permissions', function () {
    $role = Role::firstOrCreate(['name' => 'draft_role', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'division.read', 'guard_name' => 'web']);
    $role->givePermissionTo('division.read');

    $manager = makeRbacManager();

    $response = $this
        ->actingAs($manager)
        ->put('/settings/roles/'.$role->getKey(), [
            'name' => 'renamed_role',
            'permissions' => [],
        ]);

    $response
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('success');

    $role->refresh();

    expect($role->name)->toBe('renamed_role');
    expect($role->permissions)->toBeEmpty();
});

test('a role can be deleted', function () {
    $role = Role::firstOrCreate(['name' => 'temp_role', 'guard_name' => 'web']);

    $manager = makeRbacManager();

    $this->actingAs($manager)
        ->delete('/settings/roles/'.$role->getKey())
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('roles', ['id' => $role->getKey()]);
});

test('the super admin role cannot be deleted', function () {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $manager = makeRbacManager();

    $this->actingAs($manager)
        ->delete('/settings/roles/'.$role->getKey())
        ->assertRedirect(route('roles.index'))
        ->assertSessionHasErrors('name');

    $this->assertDatabaseHas('roles', ['id' => $role->getKey()]);
});
