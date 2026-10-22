<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Tests\Support\FakeIdentityProvider;

/**
 * Q14 drift guard (ADR-0010): the home tenant follows the SAML assertion
 * every login while the employee link is global. When the login write
 * leaves the two in different cabang, a warning is recorded (diagnosable,
 * not silent); the link is never broken or re-pointed automatically.
 */
beforeEach(function () {
    config()->set('services.saml2', [
        'metadata' => FakeIdentityProvider::metadataXml(),
        'sp_entityid' => FakeIdentityProvider::spEntityId(),
        'sp_acs' => 'saml/acs',
        'sp_sls' => 'saml/sls',
    ]);

    config()->set('auth.super_admin_email', 'platform-superadmin@optigate.test');

    (new RoleAndPermissionSeeder)->run();

    Socialite::forgetDrivers();

    Log::spy();
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/** The SSO fixture account, linked to a karyawan in the given cabang. */
function driftLinkedAccount(Tenant $home): User
{
    initTenant($home->code);

    $employee = Employee::create(['nama_employee' => 'Karyawan Dua Cabang']);

    $user = createUser([
        'email' => FakeIdentityProvider::EMAIL,
        'tenant_id' => $home->id,
        'employee_id' => $employee->getKey(),
    ]);
    $user->assignRole('karyawan');

    return $user;
}

/** The IdP-initiated assertion lands the fixture account. */
function driftLogin()
{
    return test()
        ->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl());
}

it('records a warning when the asserted company flips the home tenant away from the linked karyawan', function () {
    $hq = createTestTenant(['code' => 'hq', 'name' => 'Head Office']);
    $account = driftLinkedAccount($hq);

    // The portal now asserts a company registered as a different cabang.
    Tenant::factory()->create([
        'name' => FakeIdentityProvider::COMPANY,
        'code' => 'plant-1',
    ]);
    Tenant::forgetCurrent();

    driftLogin()->assertRedirect();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Home tenant drift'))
        ->once();

    // The flip lands, but the link survives (ADR-0010: never auto-repair).
    expect($account->refresh())
        ->employee_id->not->toBeNull()
        ->and($account->tenant_id)->toBe(Tenant::where('code', 'plant-1')->sole()->id);
});

it('stays quiet when the asserted company matches the linked karyawan cabang', function () {
    $hq = createTestTenant(['code' => 'hq', 'name' => FakeIdentityProvider::COMPANY]);
    driftLinkedAccount($hq);

    driftLogin()->assertRedirect();

    Log::shouldNotHaveReceived('warning');
});

it('ignores accounts without a linked karyawan', function () {
    createTestTenant(['code' => 'hq', 'name' => FakeIdentityProvider::COMPANY]);

    // First-time SSO account (guest role provisioned, no link): the login
    // may write any home tenant — there is nothing to drift against.
    driftLogin()->assertRedirect();

    Log::shouldNotHaveReceived('warning');
});
