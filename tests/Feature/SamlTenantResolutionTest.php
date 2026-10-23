<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\SamlTenantResolver;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeIdentityProvider;

beforeEach(function () {
    config()->set('services.saml2', [
        'metadata' => FakeIdentityProvider::metadataXml(),
        'sp_entityid' => FakeIdentityProvider::spEntityId(),
        'sp_acs' => 'saml/acs',
        'sp_sls' => 'saml/sls',
    ]);

    config(['auth.super_admin_email' => 'superadmin@appdutamall.com']);

    Socialite::forgetDrivers();
});

/** Seed the unit bisnis the fake assertion's company attribute points at. */
function matchingTenant(array $attributes = []): Tenant
{
    return Tenant::factory()->create([
        'name' => FakeIdentityProvider::COMPANY,
        'code' => 'test-co',
        ...$attributes,
    ]);
}

function loginWithAssertion(array $options = [])
{
    return test()->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl($options));
}

/**
 * A role-holding akun for the assertion's email. Logins without any active
 * role are denied (Task 2), so every test exercising tenant-resolution
 * mechanics acts as a user the role-assignment surface has already provisioned.
 */
function assertionUserWithRole(): User
{
    $user = User::factory()->create(['email' => FakeIdentityProvider::EMAIL]);
    $user->assignRole(Role::firstOrCreate(['name' => 'karyawan', 'guard_name' => 'web']));

    return $user;
}

it('matches the assertion company by name and lands on the tenant dashboard', function () {
    $tenant = matchingTenant();
    assertionUserWithRole();

    loginWithAssertion()
        ->assertRedirect(route('dashboard', ['tenant' => 'test-co'], absolute: false));

    $user = User::query()->sole();

    // The matched tenant becomes the user's home tenant and is current.
    expect($user->fresh()->tenant_id)->toBe($tenant->id)
        ->and(Tenant::current()?->is($tenant))->toBeTrue();
});

it('matches case-insensitively and after trimming whitespace', function () {
    $tenant = matchingTenant(['name' => 'PT Optigate Test']);
    assertionUserWithRole();

    // A real assertion may carry stray surrounding whitespace or different
    // casing; matching stays exact after trim + case folding, nothing fuzzy.
    loginWithAssertion(['company' => '  pT OPTIGATE tEsT  '])
        ->assertRedirect(route('dashboard', ['tenant' => 'test-co'], absolute: false));

    expect(User::query()->sole()->fresh()->tenant_id)->toBe($tenant->id);
});

it('never matches partially or fuzzily', function () {
    matchingTenant(['name' => 'PT Optigate Test Banjarmasin']);
    assertionUserWithRole();

    loginWithAssertion(['company' => 'PT Optigate'])
        ->assertRedirect(route('saml.denied', absolute: false));

    expect(session('saml.denial_reason'))->toBe(SamlTenantResolver::REASON_NO_MATCH);
});

it('denies access when no tenant matches and never auto-creates one', function () {
    assertionUserWithRole();

    loginWithAssertion(['company' => 'Perusahaan Tak Terdaftar'])
        ->assertRedirect(route('saml.denied', absolute: false));

    expect(session('saml.denial_reason'))->toBe(SamlTenantResolver::REASON_NO_MATCH)
        ->and(Tenant::query()->count())->toBe(0);
});

it('logs a warning with the assertion identifiers when no tenant matches', function () {
    Log::spy();
    assertionUserWithRole();

    loginWithAssertion(['company' => 'Perusahaan Tak Terdaftar']);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => str_contains($message, 'no tenant matches')
            && $context['company'] === 'Perusahaan Tak Terdaftar'
            && $context['name_id'] === FakeIdentityProvider::EMAIL);
});

it('denies access when more than one tenant matches and never picks one', function () {
    // The schema keeps code and optigate_company_id unique, but names may
    // repeat — a duplicate name must deny, not guess.
    matchingTenant(['code' => 'first']);
    matchingTenant(['code' => 'second', 'optigate_company_id' => 99]);
    assertionUserWithRole();

    loginWithAssertion()
        ->assertRedirect(route('saml.denied', absolute: false));

    expect(session('saml.denial_reason'))->toBe(SamlTenantResolver::REASON_MULTIPLE_MATCHES);
});

it('logs an error when multiple tenants match', function () {
    Log::spy();

    matchingTenant(['code' => 'first']);
    matchingTenant(['code' => 'second', 'optigate_company_id' => 99]);
    assertionUserWithRole();

    loginWithAssertion();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => str_contains($message, 'multiple tenants match'));
});

it('denies access when the matching tenant is inactive', function () {
    matchingTenant(['is_active' => false]);
    assertionUserWithRole();

    loginWithAssertion()
        ->assertRedirect(route('saml.denied', absolute: false));

    expect(session('saml.denial_reason'))->toBe(SamlTenantResolver::REASON_INACTIVE);
});

it('denies access when the matching tenant was deactivated by the sync', function () {
    matchingTenant(['deactivated_at' => now()]);
    assertionUserWithRole();

    loginWithAssertion()
        ->assertRedirect(route('saml.denied', absolute: false));

    expect(session('saml.denial_reason'))->toBe(SamlTenantResolver::REASON_INACTIVE);
});

it('denies access when the assertion carries no company data', function () {
    assertionUserWithRole();

    loginWithAssertion(['company' => null])
        ->assertRedirect(route('saml.denied', absolute: false));

    expect(session('saml.denial_reason'))->toBe(SamlTenantResolver::REASON_NO_COMPANY_DATA);
});

it('logs a warning with the assertion identifiers when the assertion carries no company data', function () {
    Log::spy();
    assertionUserWithRole();

    loginWithAssertion(['company' => null]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => str_contains($message, 'no company data')
            && $context['name_id'] === FakeIdentityProvider::EMAIL);
});

it('sends the denied user to an error page that explains the reason', function () {
    assertionUserWithRole();

    loginWithAssertion(['company' => 'Perusahaan Tak Terdaftar']);

    $this->get(route('saml.denied'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('message', fn ($value) => str_contains((string) $value, 'belum terdaftar'))
                ->etc(),
        );
});

it('lands a super admin straight on the assertion company dashboard', function () {
    $tenant = matchingTenant();

    User::factory()->create([
        'email' => FakeIdentityProvider::EMAIL,
        'is_super_admin' => true,
    ]);

    loginWithAssertion()
        ->assertRedirect(route('dashboard', ['tenant' => 'test-co'], absolute: false));

    // Platform accounts stay untied to a home tenant: they may switch to any
    // unit bisnis later without the assertion constraining them.
    expect(User::query()->sole()->fresh()->tenant_id)->toBeNull()
        ->and(Tenant::current()?->is($tenant))->toBeTrue();
});

it('lets the seeded super admin account bypass a non-matching company', function () {
    $user = User::factory()->create([
        'email' => 'superadmin@appdutamall.com',
        'is_super_admin' => false,
    ]);

    // The seeder provisions this account with the super_admin role (Task 3);
    // the bypass runs through the role + Gate::before, never through the
    // configured email.
    $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    loginWithAssertion([
        'company' => 'Perusahaan Tak Terdaftar',
        'email' => 'superadmin@appdutamall.com',
    ])->assertRedirect(route('admin.dashboard', absolute: false));

    expect($user->fresh()->tenant_id)->toBeNull()
        ->and(Tenant::query()->count())->toBe(0);
});

it('sends a super admin to the admin panel when the assertion company is inactive', function () {
    matchingTenant(['is_active' => false]);

    User::factory()->create([
        'email' => FakeIdentityProvider::EMAIL,
        'is_super_admin' => true,
    ]);

    loginWithAssertion()
        ->assertRedirect(route('admin.dashboard', absolute: false));

    expect(session('saml.denial_reason'))->toBeNull();
});
