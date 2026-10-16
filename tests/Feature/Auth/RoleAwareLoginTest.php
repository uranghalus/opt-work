<?php

use App\Models\Employee;
use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => '9001',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    (new RoleAndPermissionSeeder)->run();
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('rejects password login for a user holding no role', function () {
    $user = createUser(['tenant_id' => $this->hq->id]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'no_role');

    $this->assertGuest();
});

it('lands a role-holding user with a home tenant on their dashboard', function () {
    $user = createUser(['tenant_id' => $this->hq->id]);
    $user->assignRole('karyawan');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', ['tenant' => 'hq']));

    $this->assertAuthenticatedAs($user);
});

it('lands a platform super admin on the central admin panel', function () {
    $user = makeSuperAdmin(createUser());
    $user->assignRole('super_admin');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('lands a flag-only platform super admin without any Spatie role', function () {
    // The is_super_admin flag and the super_admin role are independent
    // mechanisms (Task 3): the flag alone must still reach the admin panel.
    $user = makeSuperAdmin(createUser());

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('denies a role-holding user whose account has no tenant membership', function () {
    // Role but no home tenant and no platform flag: nowhere to land.
    $user = createUser();
    $user->assignRole('karyawan');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'no_tenant');

    $this->assertGuest();
});

it('sees roles granted after login on the next request', function () {
    $user = createUser(['tenant_id' => $this->hq->id]);
    $user->assignRole('karyawan');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // Roles assigned mid-session are picked up per request — the session
    // never snapshots the permission set at login time.
    $user->givePermissionTo('employee.read');

    $this->actingAs($user)
        ->get(route('employees.show', ['tenant' => 'hq', 'employee' => Employee::factory()->create()->id]))
        ->assertOk();
});
