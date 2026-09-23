<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Saml2\Provider;
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

    URL::forceRootUrl(config('app.url'));
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

test('a signed response completes login and creates the user', function () {
    $response = $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl());

    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->sole();

    expect($user->email)->toBe(FakeIdentityProvider::EMAIL)
        ->and($user->name)->toBe(FakeIdentityProvider::NAME)
        ->and($this->app['auth']->guard()->user()?->is($user))->toBeTrue();
});

test('a signed response logs an existing user back in without duplicating the account', function () {
    $user = User::factory()->create(['email' => FakeIdentityProvider::EMAIL]);

    $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl());

    expect(User::query()->count())->toBe(1)
        ->and($this->app['auth']->guard()->user()?->is($user))->toBeTrue();
});

test('an idp initiated response without sp initiated state is accepted as stateless', function () {
    // No "state" in the session: the identity provider started the flow
    // (IdP-initiated SSO), which never carries our relay state.
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
        ->assertRedirect(route('login'));
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
        ->assertRedirect(route('login'));
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

test('an idp logout request terminates the local session', function () {
    $user = User::factory()->create();

    actingAs($user)->get(FakeIdentityProvider::logoutRequestUrl());

    expect($this->app['auth']->guard()->check())->toBeFalse();
});

test('a login error lands back on the login screen with a message', function () {
    $response = $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl(['success' => false]))
        ->assertRedirect(route('login'));

    $response->assertSessionHas('errors');
});
