<?php

use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => 9002,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    (new RoleAndPermissionSeeder)->run();
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('denies a guest-only user at login → provisioning-waiting page', function () {
    $user = createUser(['tenant_id' => $this->hq->id]);
    $user->assignRole('guest');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'guest_provisioning');

    $this->assertGuest();
});

it('blocks a guest-only user from a tenant dashboard route', function () {
    $user = createUser(['tenant_id' => $this->hq->id]);
    $user->assignRole('guest');

    $this->actingAs($user)
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertForbidden();
});

it('allows a karyawan user to reach the dashboard', function () {
    $user = createUser(['tenant_id' => $this->hq->id]);
    $user->assignRole('karyawan');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', ['tenant' => 'hq']));
});
