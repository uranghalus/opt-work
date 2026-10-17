<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeIdentityProvider;

/**
 * Login provisioning (wo: `.scratch/guest-role`):
 *
 * - First-time SSO users with no role and no linked karyawan (Akun) get the
 *   zero-permission `guest` role, once, inside a transaction — so nothing
 *   leaks before a human (the super admin) grants real roles.
 * - The platform account (config `auth.super_admin_email`, env-fed) is
 *   self-healed at login: flag + `super_admin` role + linked employee by
 *   email — and is never given `guest`.
 *
 * Seams under test: the SAML ACS HTTP flow and the resulting database
 * state (roles / flags / links) — the same boundaries an operator sees.
 */
beforeEach(function () {
    config()->set('services.saml2', [
        'metadata' => FakeIdentityProvider::metadataXml(),
        'sp_entityid' => FakeIdentityProvider::spEntityId(),
        'sp_acs' => 'saml/acs',
        'sp_sls' => 'saml/sls',
    ]);

    config()->set('auth.super_admin_email', 'platform-superadmin@optigate.test');

    // The seeded catalogue (incl. the `guest` system role) must exist — the
    // app assumes a seeded RBAC catalogue, login never creates roles.
    (new RoleAndPermissionSeeder)->run();

    Socialite::forgetDrivers();

    URL::forceRootUrl(config('app.url'));

    Tenant::factory()->create([
        'name' => FakeIdentityProvider::COMPANY,
        'code' => 'test-co',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

function samlLogin(array $attributes = []): void
{
    test()
        ->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl($attributes));
}

/** The account the assertion logs in as (the platform account is a fixture too). */
function samlUser(): User
{
    return User::query()->where('email', FakeIdentityProvider::EMAIL)->sole();
}

it('gives a first-time unlinked user the guest role', function () {
    samlLogin();

    $user = samlUser();

    expect($user->getRoleNames()->all())->toBe(['guest'])
        ->and(Role::findByName('guest')->permissions)->toHaveCount(0)
        ->and($this->app['auth']->guard()->check())->toBeTrue();
});

it('keeps the guest assignment idempotent on the second login', function () {
    samlLogin();

    // A fresh assertion for a second HTTP request (a replayed assertion is
    // rejected by the provider, not by this feature).
    Socialite::forgetDrivers();
    samlLogin();

    $user = samlUser();

    expect($user->getRoleNames()->all())->toBe(['guest'])
        ->and(Role::findByName('guest')->users()->count())->toBe(1);
});

it('leaves an existing user with roles untouched', function () {
    $user = User::factory()->create(['email' => FakeIdentityProvider::EMAIL]);
    $user->assignRole('karyawan');

    samlLogin();

    expect($user->fresh()->getRoleNames()->all())->toBe(['karyawan']);
});

it('gives no guest role to an account already linked to a karyawan', function () {
    initTenant('test-co');
    $employee = Employee::create([
        'nik_employee' => 'NIK-0001',
        'nama_employee' => 'Rudi Teknisi',
        'email' => FakeIdentityProvider::EMAIL,
    ]);

    $user = User::factory()->create([
        'email' => FakeIdentityProvider::EMAIL,
        'employee_id' => $employee->getKey(),
    ]);

    samlLogin();

    expect($user->fresh()->getRoleNames())->toBeEmpty()
        ->and($user->fresh()->employee_id)->toBe($employee->getKey());
});

it('self-heals the platform account and never gives it guest', function () {
    initTenant('test-co');
    $employee = Employee::create([
        'nik_employee' => 'NIK-0002',
        'nama_employee' => 'Super Admin',
        'email' => config('auth.super_admin_email'),
    ]);

    // Login provisioning runs before the tenant is identified (production
    // reality): the platform branch must find the karyawan anyway.
    Tenant::forgetCurrent();

    samlLogin(['email' => config('auth.super_admin_email')]);

    $user = User::query()->where('email', config('auth.super_admin_email'))->sole();

    expect($user->getRoleNames()->all())->toBe(['super_admin'])
        ->and($user->is_super_admin)->toBeTrue()
        ->and($user->fresh()->employee_id)->toBe($employee->getKey());
});

it('denies a guest on gated routes', function () {
    samlLogin();

    $this->actingAs(samlUser())
        ->get('/hak-akses')
        ->assertForbidden();
});

it('shares no can flags for a guest', function () {
    samlLogin();

    $request = Request::create('/test-co', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn (): User => samlUser());

    $props = (new HandleInertiaRequests)->share($request);

    foreach ($props['auth']['can'] as $ability => $value) {
        expect($value)->toBeFalse();
    }
});
