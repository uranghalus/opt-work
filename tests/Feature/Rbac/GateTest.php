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
