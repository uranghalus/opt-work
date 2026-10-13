<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
});

/** A user holding exactly the given hak-akses permissions. */
function permissionManager(array $permissions): User
{
    $user = createUser();
    $user->givePermissionTo($permissions);

    return $user;
}

it('redirects guests away from the permissions index', function () {
    $this->get('/settings/permissions')->assertRedirect(route('saml.redirect'));
});

it('denies the permissions index without permission.read', function () {
    $this->actingAs(createUser())
        ->get('/settings/permissions')
        ->assertForbidden();
});

it('renders the permissions index with groups for a user with permission.read', function () {
    $this->actingAs(permissionManager(['permission.read']))
        ->get('/settings/permissions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/permissions/index')
            ->has('permissions.data')
            ->has('groups')
            ->where('filters.search', ''));
});

it('renders the permissions index for a super admin holding zero permissions', function () {
    $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/settings/permissions')
        ->assertOk();
});

it('searches permissions by name', function () {
    $this->actingAs(permissionManager(['permission.read']))
        ->get('/settings/permissions?search=division')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.search', 'division'));
});

it('filters permissions by group', function () {
    $this->actingAs(permissionManager(['permission.read']))
        ->get('/settings/permissions?group=division')
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->where('filters.group', 'division');
        });

    expect(Permission::where('name', 'like', 'division.%')->count())->toBe(4);
});

it('batch creates permissions from resource and actions', function () {
    $this->actingAs(permissionManager(['permission.create']))
        ->post('/settings/permissions', [
            'resource' => 'custom.resource',
            'actions' => ['read', 'create'],
        ])
        ->assertRedirect();

    expect(Permission::where('name', 'custom.resource.read')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Permission::where('name', 'custom.resource.create')->where('guard_name', 'web')->exists())->toBeTrue();
});

it('batch creates idempotently', function () {
    $manager = permissionManager(['permission.create']);

    $this->actingAs($manager)
        ->post('/settings/permissions', ['resource' => 'custom.resource', 'actions' => ['read']])
        ->assertRedirect();

    $this->actingAs($manager)
        ->post('/settings/permissions', ['resource' => 'custom.resource', 'actions' => ['read']])
        ->assertRedirect();

    expect(Permission::where('name', 'custom.resource.read')->count())->toBe(1);
});

it('rejects an invalid resource format', function () {
    $this->actingAs(permissionManager(['permission.create']))
        ->post('/settings/permissions', ['resource' => 'Invalid-Resource', 'actions' => ['read']])
        ->assertSessionHasErrors('resource');
});

it('rejects an empty actions array', function () {
    $this->actingAs(permissionManager(['permission.create']))
        ->post('/settings/permissions', ['resource' => 'custom.resource', 'actions' => []])
        ->assertSessionHasErrors('actions');
});

it('rejects actions outside the closed verb set', function () {
    $this->actingAs(permissionManager(['permission.create']))
        ->post('/settings/permissions', ['resource' => 'custom.resource', 'actions' => ['view']])
        ->assertSessionHasErrors('actions.0');
});

it('renames a permission', function () {
    $permission = Permission::firstOrCreate(['name' => 'custom.rename-me', 'guard_name' => 'web']);

    $this->actingAs(permissionManager(['permission.update']))
        ->patch('/settings/permissions/'.$permission->id, ['name' => 'custom.read'])
        ->assertRedirect();

    expect($permission->fresh()->name)->toBe('custom.read');
});

it('rejects an invalid name format on update', function () {
    $permission = Permission::firstOrCreate(['name' => 'custom.rename-me', 'guard_name' => 'web']);

    $this->actingAs(permissionManager(['permission.update']))
        ->patch('/settings/permissions/'.$permission->id, ['name' => 'invalidname'])
        ->assertSessionHasErrors('name');
});

it('rejects a verb outside the closed set on update', function () {
    $permission = Permission::firstOrCreate(['name' => 'custom.rename-me', 'guard_name' => 'web']);

    $this->actingAs(permissionManager(['permission.update']))
        ->patch('/settings/permissions/'.$permission->id, ['name' => 'custom.rename.view'])
        ->assertSessionHasErrors('name');
});

it('rejects a duplicate name on update', function () {
    $permission = Permission::firstOrCreate(['name' => 'custom.rename-me', 'guard_name' => 'web']);

    $this->actingAs(permissionManager(['permission.update']))
        ->patch('/settings/permissions/'.$permission->id, ['name' => 'division.read'])
        ->assertSessionHasErrors('name');
});

it('deletes a permission not attached to any role', function () {
    $permission = Permission::firstOrCreate(['name' => 'custom.deletable', 'guard_name' => 'web']);

    $this->actingAs(permissionManager(['permission.delete']))
        ->delete('/settings/permissions/'.$permission->id)
        ->assertRedirect();

    expect(Permission::where('name', 'custom.deletable')->exists())->toBeFalse();
});

it('refuses to delete a permission attached to a role', function () {
    $permission = Permission::where('name', 'dashboard.read')->firstOrFail();
    Role::findByName('viewer')->syncPermissions([$permission->name]);

    $this->actingAs(permissionManager(['permission.delete']))
        ->delete('/settings/permissions/'.$permission->id)
        ->assertSessionHasErrors('permission');

    expect(Permission::where('name', 'dashboard.read')->exists())->toBeTrue();
});

it('denies store without permission.create', function () {
    $this->actingAs(createUser())
        ->post('/settings/permissions', ['resource' => 'custom.resource', 'actions' => ['read']])
        ->assertForbidden();
});
