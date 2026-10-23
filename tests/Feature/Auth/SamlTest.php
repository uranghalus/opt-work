<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Laravel\Socialite\Facades\Socialite;
use LightSaml\SamlConstants;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Saml2\Provider;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeIdentityProvider;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config()->set('services.saml2', [
        'metadata' => FakeIdentityProvider::metadataXml(),
        'sp_entityid' => FakeIdentityProvider::spEntityId(),
        'sp_acs' => 'saml/acs',
        'sp_sls' => 'saml/sls',
    ]);

    // Each request must build its Saml2 driver from scratch — the Socialite
    // manager memoizes driver instances (including their resolved users)
    // across the multiple requests some tests make.
    Socialite::forgetDrivers();
});

test('the saml2 driver is registered when SocialiteWasCalled is dispatched', function () {
    event(app(SocialiteWasCalled::class));

    expect(Socialite::driver('saml2'))->toBeInstanceOf(Provider::class);
});

test('the redirect route issues a signed authn request to the identity provider', function () {
    $response = $this->get(route('saml.redirect'));

    $response->assertRedirect();

    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->toContain(FakeIdentityProvider::SSO_URL);

    $authnRequest = FakeIdentityProvider::decodeAuthnRequest($redirectUrl);

    expect($authnRequest->getID())->toStartWith('_')
        ->and($authnRequest->getIssuer()->getValue())->toBe(FakeIdentityProvider::spEntityId())
        ->and($authnRequest->getAssertionConsumerServiceURL())->toBe(FakeIdentityProvider::acsUrl());
});

test('the redirect route remembers the relay state in the session', function () {
    $response = $this->get(route('saml.redirect'));

    $state = session('state');

    expect($state)->toBeString()->not->toBeEmpty()
        ->and($response->headers->get('Location'))->toContain(
            'RelayState='.urlencode($state)
        );
});

test('a signed response completes login and provisions the account', function () {
    // Seeded RBAC catalogue: the role-aware login gate + the `guest`
    // provisioning (`.scratch/guest-role`) both assume a seeded catalogue.
    (new RoleAndPermissionSeeder)->run();

    Tenant::factory()->create([
        'name' => FakeIdentityProvider::COMPANY,
        'code' => 'test-co',
    ]);

    $response = $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl());

    $response->assertRedirect(route('dashboard', ['tenant' => 'test-co'], absolute: false));

    $user = User::query()->where('email', FakeIdentityProvider::EMAIL)->sole();

    expect($user->name)->toBe(FakeIdentityProvider::NAME)
        // A first-time unlinked account gets the zero-permission guest role,
        // so the session continues into the (gated) tenant shell.
        ->and($user->getRoleNames()->all())->toBe(['guest'])
        ->and($user->tenant_id)->not->toBeNull()
        ->and($this->app['auth']->guard()->check())->toBeTrue();
});

test('a signed response without the guest role in the catalogue denies a first-time user', function () {
    // No RoleAndPermissionSeeder here: the catalogue has no `guest` role,
    // so provisioning cannot assign it and the role-aware gate denies the
    // session (Task 2) — the account itself is still created.
    Tenant::factory()->create([
        'name' => FakeIdentityProvider::COMPANY,
        'code' => 'test-co',
    ]);

    $response = $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl());

    $response->assertRedirect(route('saml.denied', absolute: false))
        ->assertSessionHas('saml.denial_reason', 'no_role');

    $user = User::query()->where('email', FakeIdentityProvider::EMAIL)->sole();

    expect($user->getRoleNames())->toBeEmpty()
        ->and($user->fresh()->tenant_id)->toBeNull()
        ->and($this->app['auth']->guard()->check())->toBeFalse();
});

test('a signed response logs an existing user back in without duplicating the account', function () {
    Tenant::factory()->create([
        'name' => FakeIdentityProvider::COMPANY,
        'code' => 'test-co',
    ]);

    $user = User::factory()->create(['email' => FakeIdentityProvider::EMAIL]);
    $user->assignRole(Role::firstOrCreate(['name' => 'karyawan', 'guard_name' => 'web']));

    $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl());

    expect(User::query()->count())->toBe(1)
        ->and($this->app['auth']->guard()->user()?->is($user))->toBeTrue();
});

test('an idp initiated response without sp initiated state is accepted as stateless', function () {
    // No "state" in the session: the identity provider started the flow
    // (IdP-initiated SSO), which never carries our relay state.
    Tenant::factory()->create([
        'name' => FakeIdentityProvider::COMPANY,
        'code' => 'test-co',
    ]);

    $user = User::factory()->create(['email' => FakeIdentityProvider::EMAIL]);
    $user->assignRole(Role::firstOrCreate(['name' => 'karyawan', 'guard_name' => 'web']));

    $this->get(FakeIdentityProvider::assertionResponseUrl());

    expect($this->app['auth']->guard()->check())->toBeTrue();
});

test('a forged state value is rejected', function () {
    $this->withSession(['state' => 'attacker-crafted-state'])
        ->get(FakeIdentityProvider::assertionResponseUrl());

    expect($this->app['auth']->guard()->check())->toBeFalse();
});

test('an assertion signed by an unknown identity provider is rejected', function () {
    $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl(['signed_by' => 'sp']));

    expect($this->app['auth']->guard()->check())->toBeFalse();
});

test('an assertion from an unexpected issuer is rejected', function () {
    $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl([
            'issuer' => 'https://evil.example/metadata',
        ]));

    expect($this->app['auth']->guard()->check())->toBeFalse();
});

test('an unsuccessful status is rejected', function () {
    $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl(['success' => false]));

    expect($this->app['auth']->guard()->check())->toBeFalse();
});

test('a replayed assertion is rejected', function () {
    $responseUrl = FakeIdentityProvider::assertionResponseUrl();

    $this->withSession(['state' => FakeIdentityProvider::STATE])->get($responseUrl);

    expect(User::query()->count())->toBe(1);

    // The same assertion a second time must not be accepted again, even
    // with a matching relay state. Forget the memoized Socialite driver
    // instances so the second request is validated from scratch, like a
    // real second HTTP request would be.
    Socialite::forgetDrivers();

    $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get($responseUrl)
        ->assertRedirect(url('/'));
});

test('the email attribute identifies the local account', function () {
    $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl([
            'email' => 'other.person@optigate.test',
        ]));

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->sole()->email)->toBe('other.person@optigate.test');
});

test('an invalid state exception surfaces as a login error', function () {
    $this->withSession(['state' => 'attacker-crafted-state'])
        ->get(FakeIdentityProvider::assertionResponseUrl())
        ->assertRedirect(url('/'));
});

test('the service provider metadata route renders valid xml', function () {
    $response = $this->get(route('saml.metadata'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/samlmetadata+xml');

    $metadata = $response->getContent();
    expect($metadata)->toContain(FakeIdentityProvider::spEntityId())
        ->toContain(FakeIdentityProvider::acsUrl())
        ->toContain('SingleLogoutService');
});

test('the service provider metadata advertises both logout bindings', function () {
    // The SAML provider derives the advertised bindings from the HTTP verbs
    // the single logout route accepts, so a GET-only route would tell the
    // identity provider it may never POST a logout message here.
    $metadata = $this->get(route('saml.metadata'))->getContent();

    foreach ([
        SamlConstants::BINDING_SAML2_HTTP_POST,
        SamlConstants::BINDING_SAML2_HTTP_REDIRECT,
    ] as $binding) {
        expect($metadata)->toContain(
            'SingleLogoutService Binding="'.$binding.'" Location="'.FakeIdentityProvider::slsUrl().'"'
        );
    }
});

test('an idp logout request terminates the local session', function () {
    $user = User::factory()->create();

    actingAs($user)->get(FakeIdentityProvider::logoutRequestUrl());

    expect($this->app['auth']->guard()->check())->toBeFalse();
});

test('a completed login remembers the name id for single logout', function () {
    $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl());

    expect(session('saml.name_id'))->toBe(FakeIdentityProvider::EMAIL);
});

test('logging out sends a logout request to the identity provider', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->withSession(['saml.name_id' => FakeIdentityProvider::EMAIL])
        ->post(route('saml.slo'));

    $response->assertRedirect();

    $logoutUrl = (string) $response->headers->get('Location');

    expect($logoutUrl)->toContain(FakeIdentityProvider::SLO_URL);

    $logoutRequest = FakeIdentityProvider::decodeLogoutRequest($logoutUrl);

    expect($logoutRequest->getNameID()->getValue())->toBe(FakeIdentityProvider::EMAIL)
        ->and($logoutRequest->getIssuer()->getValue())->toBe(FakeIdentityProvider::spEntityId())
        ->and($logoutRequest->getDestination())->toBe(FakeIdentityProvider::SLO_URL)
        ->and($this->app['auth']->guard()->check())->toBeFalse();
});

test('logging out without a remembered name id lands on the root', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('saml.slo'))
        ->assertRedirect(url('/'));

    expect($this->app['auth']->guard()->check())->toBeFalse();
});

test('the identity provider logout response completes the logout', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(FakeIdentityProvider::logoutResponseUrl())
        ->assertRedirect(url('/'));

    expect($this->app['auth']->guard()->check())->toBeFalse();
});

test('the logout response posted over the post binding completes the logout', function () {
    $user = User::factory()->create();

    // OptiGate answers an SP-initiated logout with an auto-POST form carrying
    // the LogoutResponse, so the single logout service must accept POST as well
    // as the HTTP-Redirect binding.
    actingAs($user)
        ->post(route('saml.logout'), [
            'SAMLResponse' => FakeIdentityProvider::logoutResponsePayload(),
        ])
        ->assertRedirect(url('/'));

    expect($this->app['auth']->guard()->check())->toBeFalse();
});

test('a posted logout response with an unexpected signature is rejected', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('saml.logout'), [
            'SAMLResponse' => FakeIdentityProvider::logoutResponsePayload(['signed_by' => 'sp']),
        ])
        ->assertRedirect(url('/'));

    expect($this->app['auth']->guard()->check())->toBeTrue();
});

test('an idp logout request posted over the post binding terminates the local session', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('saml.logout'), [
        'SAMLRequest' => FakeIdentityProvider::logoutRequestPayload(),
    ]);

    expect($this->app['auth']->guard()->check())->toBeFalse();
});

test('a logout response with an unexpected signature is rejected', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(FakeIdentityProvider::logoutResponseUrl(['signed_by' => 'sp']))
        ->assertRedirect(url('/'));

    expect($this->app['auth']->guard()->check())->toBeTrue();
});

test('a logout response from an unexpected issuer is rejected', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(FakeIdentityProvider::logoutResponseUrl(['issuer' => 'https://evil.example/metadata']))
        ->assertRedirect(url('/'));

    expect($this->app['auth']->guard()->check())->toBeTrue();
});

test('an unsuccessful logout response is rejected', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(FakeIdentityProvider::logoutResponseUrl(['success' => false]))
        ->assertRedirect(url('/'));

    expect($this->app['auth']->guard()->check())->toBeTrue();
});

test('a login error lands back on the login screen with a message', function () {
    $response = $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl(['success' => false]))
        ->assertRedirect(url('/'));

    $response->assertSessionHas('errors');
});
