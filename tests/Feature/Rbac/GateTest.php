<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\Request;

it('treats the is_super_admin flag as super admin', function () {
    expect(makeSuperAdmin(createUser())->isSuperAdmin())->toBeTrue();
});

it('treats the super_admin role as super admin', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = createUser();
    $user->assignRole('super_admin');

    expect($user->isSuperAdmin())->toBeTrue();
});

it('does not treat a regular user as super admin', function () {
    expect(createUser()->isSuperAdmin())->toBeFalse();
});

it('bypasses every gate for a super admin even with zero permissions', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $admin = createUser();
    $admin->assignRole('super_admin');

    expect($admin->can('anything.at.all'))->toBeTrue();
});

it('denies a regular user an unseeded ability without throwing', function () {
    expect(createUser()->can('anything.at.all'))->toBeFalse();
});

it('gives a super admin the shared role.read flag despite an empty permission list', function () {
    (new RoleAndPermissionSeeder)->run();
    $admin = makeSuperAdmin(createUser());

    $props = (new HandleInertiaRequests)->share(requestForUser($admin));

    expect($props['auth']['isSuperAdmin'])->toBeTrue();
    expect($props['auth']['can']['role.read'])->toBeTrue();
});

it('gives a user with role.read the shared flag and denies users without it', function () {
    (new RoleAndPermissionSeeder)->run();

    $permitted = createUser();
    $permitted->givePermissionTo('role.read');

    $denied = createUser();

    expect((new HandleInertiaRequests)->share(requestForUser($permitted))['auth']['can']['role.read'])->toBeTrue();
    expect((new HandleInertiaRequests)->share(requestForUser($denied))['auth']['can']['role.read'])->toBeFalse();
});

it('shares the full hak-akses flag set with a user holding them', function () {
    (new RoleAndPermissionSeeder)->run();

    $manager = createUser();
    $manager->givePermissionTo([
        'role.read',
        'role.create',
        'role.update',
        'role.delete',
        'permission.read',
        'permission.create',
        'permission.update',
        'permission.delete',
    ]);

    $can = (new HandleInertiaRequests)->share(requestForUser($manager))['auth']['can'];

    expect($can)->toBe([
        'role.read' => true,
        'role.create' => true,
        'role.update' => true,
        'role.delete' => true,
        'permission.read' => true,
        'permission.create' => true,
        'permission.update' => true,
        'permission.delete' => true,
        'akun.read' => false,
        'akun.update' => false,
        'whatsapp.read' => false,
        'whatsapp.update' => false,
        'work-order.review' => false,
        'work-order.assign' => false,
        'work-order.submit' => false,
        'work-order.verify' => false,
        'work-data.read' => false,
        'work-data.update' => false,
        'daily-work.read' => false,
        'daily-work.update' => false,
        'daily-work.create' => false,
        'daily-work.delete' => false,
        'work-order.extend' => false,
        'work-order.extend-approve' => false,
        'work-order.approve-request' => false,
    ]);
});

it('shares only role.read with a read-only viewer', function () {
    (new RoleAndPermissionSeeder)->run();

    $viewer = createUser();
    $viewer->givePermissionTo('role.read');

    $can = (new HandleInertiaRequests)->share(requestForUser($viewer))['auth']['can'];

    expect($can)->toBe([
        'role.read' => true,
        'role.create' => false,
        'role.update' => false,
        'role.delete' => false,
        'permission.read' => false,
        'permission.create' => false,
        'permission.update' => false,
        'permission.delete' => false,
        'akun.read' => false,
        'akun.update' => false,
        'whatsapp.read' => false,
        'whatsapp.update' => false,
        'work-order.review' => false,
        'work-order.assign' => false,
        'work-order.submit' => false,
        'work-order.verify' => false,
        'work-data.read' => false,
        'work-data.update' => false,
        'daily-work.read' => false,
        'daily-work.update' => false,
        'daily-work.create' => false,
        'daily-work.delete' => false,
        'work-order.extend' => false,
        'work-order.extend-approve' => false,
        'work-order.approve-request' => false,
    ]);
});

it('shares the full hak-akses flag set with a super admin holding zero permissions', function () {
    (new RoleAndPermissionSeeder)->run();
    $admin = makeSuperAdmin(createUser());

    $can = (new HandleInertiaRequests)->share(requestForUser($admin))['auth']['can'];

    expect($can)->toBe([
        'role.read' => true,
        'role.create' => true,
        'role.update' => true,
        'role.delete' => true,
        'permission.read' => true,
        'permission.create' => true,
        'permission.update' => true,
        'permission.delete' => true,
        'akun.read' => true,
        'akun.update' => true,
        'whatsapp.read' => true,
        'whatsapp.update' => true,
        'work-order.review' => true,
        'work-order.assign' => true,
        'work-order.submit' => true,
        'work-order.verify' => true,
        'work-data.read' => true,
        'work-data.update' => true,
        'daily-work.read' => true,
        'daily-work.update' => true,
        'daily-work.create' => true,
        'daily-work.delete' => true,
        'work-order.extend' => true,
        'work-order.extend-approve' => true,
        'work-order.approve-request' => true,
    ]);
});

it('does not expose the raw permission list in shared props', function () {
    (new RoleAndPermissionSeeder)->run();

    $user = createUser();
    $user->givePermissionTo('role.read');

    $sharedUser = (new HandleInertiaRequests)->share(requestForUser($user))['auth']['user'];

    expect($sharedUser)->not->toHaveKey('permissions');
});

/**
 * Build a request whose resolved user is the given model, so the Inertia
 * share() method can be exercised without a full HTTP round trip.
 */
function requestForUser(User $user): Request
{
    $request = Request::create('/dashboard', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $user);

    return $request;
}
