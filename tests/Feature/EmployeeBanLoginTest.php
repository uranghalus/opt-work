<?php

use App\Models\Employee;
use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => '9101',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    (new RoleAndPermissionSeeder)->run();
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/** A karyawan with a linked, role-holding akun. */
function makeLinkedKaryawan(bool $banned = false): array
{
    $employee = Employee::create([
        'tenant_id' => test()->hq->id,
        'nik_employee' => 'NIK-'.str()->random(6),
        'nama_employee' => 'Linked Karyawan',
        'banned_at' => $banned ? now() : null,
    ]);

    $user = createUser(['tenant_id' => test()->hq->id, 'employee_id' => $employee->id]);
    $user->assignRole('karyawan');

    return [$employee, $user];
}

it('kills an active session on the next request once the karyawan is banned', function () {
    [, $user] = makeLinkedKaryawan();

    $this->actingAs($user)
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertOk();

    // Ban comes in from elsewhere (e.g. superadmin in another tab).
    Employee::query()->update(['banned_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'banned');

    $this->assertGuest();
});

it('denies a fresh password login for a banned karyawan', function () {
    [, $user] = makeLinkedKaryawan(banned: true);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // The login itself authenticates, but the very first request lands on the
    // choke point and the session is ended there.
    $this->get(route('dashboard', ['tenant' => 'hq']))
        ->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'banned');

    $this->assertGuest();
});

it('lets an unbanned karyawan keep their session', function () {
    [$employee, $user] = makeLinkedKaryawan();

    $this->actingAs($user)
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertOk();

    expect($employee->refresh()->banned_at)->toBeNull();
});

it('restores access after an unban', function () {
    [$employee, $user] = makeLinkedKaryawan(banned: true);

    $employee->forceFill(['banned_at' => null])->save();

    $this->actingAs($user)
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertOk();
});

it('does not block a user without a linked karyawan', function () {
    // The ban lives on the karyawan record; an akun without a link is
    // unaffected (there is nothing to check).
    $user = createUser(['tenant_id' => $this->hq->id]);
    $user->assignRole('karyawan');

    $this->actingAs($user)
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertOk();
});

it('exempts the platform super admin from the ban choke point', function () {
    // ADR-0004: the platform account bypasses every gate — a ban on the
    // linked karyawan record must never lock out platform administration.
    [, $user] = makeLinkedKaryawan(banned: true);
    makeSuperAdmin($user);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

it('blocks banned karyawan on non-tenant routes too', function () {
    [, $user] = makeLinkedKaryawan(banned: true);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'banned');
});
