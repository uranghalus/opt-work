<?php

use App\Models\Tenant;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => '9001',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('renders the local login screen for guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));
});

it('bounces authenticated users away from the login screen', function () {
    $user = createUser();

    $this->actingAs($user)->get('/login')->assertRedirect();
});

it('sends guests to the sso portal while sso is enabled', function () {
    $this->get(route('home'))->assertRedirect(route('saml.redirect'));
});

it('sends guests to the local login screen when sso is disabled', function () {
    config(['auth.sso.enabled' => false]);

    $this->get(route('home'))->assertRedirect(route('login'));

    // redirectGuestsTo (path middleware) mengikuti flag yang sama.
    $this->get('/tenants')->assertRedirect(route('login'));
});

it('points the denied screen back to the local login when sso is disabled', function () {
    config(['auth.sso.enabled' => false]);

    $this->withSession(['saml.denial_reason' => 'no_role'])
        ->get('/saml/denied')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('saml/denied')
            ->where('ssoEnabled', false));
});

it('keeps the denied screen pointing at the sso portal by default', function () {
    $this->withSession(['saml.denial_reason' => 'no_role'])
        ->get('/saml/denied')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('ssoEnabled', true));
});
