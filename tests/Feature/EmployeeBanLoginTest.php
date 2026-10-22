<?php

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
});

afterEach(function () {
    Tenant::forgetCurrent();
});

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
        ->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'banned');

    $this->assertGuest();
});

it('bounces a password login for an account linked to a banned karyawan', function () {
    [, $akun] = bannedPair();

    $this->post(route('login.store'), [
        'email' => $akun->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', ['tenant' => 'hq']));

    $this->get(route('dashboard', ['tenant' => 'hq']))
        ->assertRedirect(route('saml.denied'))
        ->assertSessionHas('saml.denial_reason', 'banned');

    $this->assertGuest();
});

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
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertOk();

    expect($employee->refresh()->banned_at)->toBeNull();
});

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
