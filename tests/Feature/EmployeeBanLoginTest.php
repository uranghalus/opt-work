<?php

<<<<<<< HEAD
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
=======
use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Tests\Support\FakeIdentityProvider;

/**
 * Q7 ban enforcement (grill 2026-10-22): a banned karyawan gets no usable
 * session — SAML login, password login, and any live session all bounce to
 * saml.denied('banned') one hop after authenticating; assignment refuses a
 * banned pelaksana; the ban revokes the linked akun's live sessions; unban
 * restores everything. The platform super admin is never caught by a ban.
 *
 * Seams under test: the SAML ACS HTTP flow, the password login flow, the
 * web-stack guard (EnsureEmployeeNotBanned) on the landing request, the
 * assign endpoint, and the ban endpoint's session revocation.
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

    // The tenant name IS the asserted company, so the ACS resolves the
    // assertion to this cabang (same recipe as GuestProvisioningTest).
    $this->hq = Tenant::factory()->create([
        'name' => FakeIdentityProvider::COMPANY,
        'code' => 'hq',
    ]);
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
});

afterEach(function () {
    Tenant::forgetCurrent();
});

<<<<<<< HEAD
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
=======
/** The fake IdP assertion lands the fixture account (janet.garcia@...). */
function banSamlLogin()
{
    return test()
        ->withSession(['state' => FakeIdentityProvider::STATE])
        ->get(FakeIdentityProvider::assertionResponseUrl());
}

/** A linked banned karyawan in hq with a role-holding akun; returns both. */
function bannedPair(): array
{
    initTenant('hq');

    $employee = Employee::create([
        'nama_employee' => 'Budi Diblokir',
        'banned_at' => now(),
    ]);

    $akun = createUser([
        'tenant_id' => test()->hq->id,
        'employee_id' => $employee->getKey(),
    ]);
    $akun->assignRole('karyawan');

    return [$employee, $akun];
}

it('bounces a SAML login for a karyawan banned in the asserted cabang', function () {
    initTenant('hq');
    Employee::create([
        'nama_employee' => FakeIdentityProvider::NAME,
        'email' => FakeIdentityProvider::EMAIL,
        'banned_at' => now(),
    ]);
    Tenant::forgetCurrent(); // production starts cold — ACS resolves the cabang itself

    $acs = banSamlLogin();
    $acs->assertRedirect();

    // The assertion itself completes (provisioning is ban-blind); the
    // web-stack guard bounces the first landing request one hop in.
    $this->get($acs->headers->get('Location'))
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
        ->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'banned');

    $this->assertGuest();
});

<<<<<<< HEAD
it('denies a fresh password login for a banned karyawan', function () {
    [, $user] = makeLinkedKaryawan(banned: true);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // The login itself authenticates, but the very first request lands on the
    // choke point and the session is ended there.
=======
it('bounces a password login for an account linked to a banned karyawan', function () {
    [, $akun] = bannedPair();

    $this->post(route('login.store'), [
        'email' => $akun->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', ['tenant' => 'hq']));

>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
    $this->get(route('dashboard', ['tenant' => 'hq']))
        ->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'banned');

    $this->assertGuest();
});

<<<<<<< HEAD
it('lets an unbanned karyawan keep their session', function () {
    [$employee, $user] = makeLinkedKaryawan();

    $this->actingAs($user)
=======
it('kills a live session on the very next request', function () {
    [, $akun] = bannedPair();

    $this->actingAs($akun)
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'banned');

    $this->assertGuest();
});

it('restores the whole path after an unban', function () {
    [$employee, $akun] = bannedPair();

    $this->actingAs($akun)
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertRedirect(route('saml.denied'));

    $admin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));

    $this->actingAs($admin)
        ->post(route('employees.unban', ['tenant' => 'hq', 'employee' => $employee->getKey()]))
        ->assertRedirect();

    $this->actingAs($akun)
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertOk();

    expect($employee->refresh()->banned_at)->toBeNull();
});

<<<<<<< HEAD
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
=======
it('revokes the linked akun live sessions and writes the audit row on ban', function () {
    initTenant('hq');
    $employee = Employee::create(['nama_employee' => 'Agus Operator']);
    $akun = createUser([
        'tenant_id' => $this->hq->id,
        'employee_id' => $employee->getKey(),
    ]);

    DB::table('sessions')->insert([
        'id' => 'live-session-1',
        'user_id' => $akun->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'phpunit',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    $admin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));

    $this->actingAs($admin)
        ->post(route('employees.ban', ['tenant' => 'hq', 'employee' => $employee->getKey()]))
        ->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $akun->getKey())->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'employee.ban')->count())->toBe(1);
});

it('refuses to assign a work order to a banned karyawan', function () {
    Notification::fake();

    initTenant('hq');
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
    ]);
    $requester = createUser(['tenant_id' => $this->hq->id]);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-BAN-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Perbaikan AC',
        'description' => 'AC ruang rapat mati',
        'status' => WorkOrderStatus::HodApproved,
        'hod_action' => 'execute_immediately',
    ]);
    $workOrder->refresh();

    $banned = Employee::create([
        'nik_employee' => fake()->unique()->numerify('NIK-########'),
        'nama_employee' => 'Karyawan Diblokir',
        'department_id' => $department->getKey(),
        'banned_at' => now(),
    ]);

    $assigner = createUser(['tenant_id' => $this->hq->id]);
    $assigner->givePermissionTo('work-order.assign');

    $this->actingAs($assigner)
        ->post("/hq/work-orders/{$workOrder->getKey()}/assign", [
            'employees' => [$banned->getKey()],
        ])
        ->assertSessionHasErrors('employees');

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::HodApproved)
        ->and($workOrder->assigned_at)->toBeNull();
});

it('never locks the platform super admin out, even behind a banned karyawan', function () {
    initTenant('hq');
    $employee = Employee::create([
        'nama_employee' => 'Platform Boss',
        'banned_at' => now(),
    ]);
    $admin = makeSuperAdmin(createUser([
        'tenant_id' => $this->hq->id,
        'employee_id' => $employee->getKey(),
    ]));

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
