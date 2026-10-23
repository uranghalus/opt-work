<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Tests\Support\FakeIdentityProvider;

/**
 * The OptiGate portal registers these exact service provider values:
 *
 *   SP Entity ID : {APP_URL}/saml/metadata
 *   ACS URL      : {APP_URL}/saml/acs
 *   SLO URL      : {APP_URL}/saml/logout
 *
 * Every generated SAML message must advertise them, even when the browser
 * reaches this application through a different hostname (127.0.0.1 vs
 * localhost). Otherwise the portal rejects the login because the ACS in the
 * AuthnRequest no longer matches the registered client.
 */
beforeEach(function () {
    // Only the identity provider side is faked. The service provider side
    // comes from the real config so env-derived values (such as the SP entity
    // ID) are exercised exactly as the application uses them.
    config()->set('services.saml2.metadata', FakeIdentityProvider::metadataXml());

    Socialite::forgetDrivers();
});

test('the authn request anchors the acs and issuer to the app url even on another host', function () {
    $appUrl = rtrim((string) config('app.url'), '/');

    $response = $this->get('http://127.0.0.1:8000/saml/redirect');

    $response->assertRedirect();

    $authnRequest = FakeIdentityProvider::decodeAuthnRequest(
        (string) $response->headers->get('Location')
    );

    expect($authnRequest->getAssertionConsumerServiceURL())->toBe($appUrl.'/saml/acs')
        ->and($authnRequest->getIssuer()->getValue())->toBe($appUrl.'/saml/metadata');
});

test('the published metadata anchors every endpoint to the app url even on another host', function () {
    $appUrl = rtrim((string) config('app.url'), '/');

    $response = $this->get('http://127.0.0.1:8000/saml/metadata');

    $response->assertOk();

    $metadata = (string) $response->getContent();

    expect($metadata)
        ->toContain('entityID="'.$appUrl.'/saml/metadata"')
        ->toContain($appUrl.'/saml/acs')
        ->toContain($appUrl.'/saml/logout')
        ->not->toContain('127.0.0.1:8000');
});

test('the identity provider certificate from config validates assertions', function () {
    // Static IdP configuration (the production shape): no metadata URL, only
    // the entity ID, endpoints and the X509 certificate from config.
    config()->set('services.saml2.metadata', null);
    config()->set('services.saml2.entityid', FakeIdentityProvider::ENTITY_ID);
    config()->set('services.saml2.acs', FakeIdentityProvider::SSO_URL);
    config()->set('services.saml2.slo', FakeIdentityProvider::SLO_URL);
    config()->set('services.saml2.certificate', FakeIdentityProvider::fixture('idp_saml.crt'));
    config()->set('services.saml2.sp_entityid', FakeIdentityProvider::spEntityId());

    Socialite::forgetDrivers();

    $this->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl());

    expect(User::query()->where('email', FakeIdentityProvider::EMAIL)->exists())->toBeTrue();
});
