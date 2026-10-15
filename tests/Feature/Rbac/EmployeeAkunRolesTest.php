<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
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

/** A karyawan in the test tenant with a linked akun; returns [employee, akun]. */
function karyawanWithAkun(): array
{
    $employee = Employee::factory()->create();
    $akun = User::factory()->create(['employee_id' => $employee->id]);

    return [$employee, $akun];
}

/** A viewer holding exactly the given akun permissions, inside the current cabang. */
function akunViewer(array $permissions): User
{
    $user = createUser(['tenant_id' => Tenant::current()?->id]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('omits the akun payload for a viewer without akun.read', function () {
    [$employee] = karyawanWithAkun();

    $this->actingAs(createUser(['tenant_id' => $this->hq->id]))
        ->get(route('employees.show', ['tenant' => 'hq', 'employee' => $employee]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/show')
            ->where('akun', null));
});

it('exposes the linked akun and its roles to a viewer with akun.read', function () {
    [$employee, $akun] = karyawanWithAkun();
    $akun->assignRole('hod');

    $this->actingAs(akunViewer(['akun.read']))
        ->get(route('employees.show', ['tenant' => 'hq', 'employee' => $employee]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('akun.exists', true)
            ->where('akun.email', $akun->email)
            ->has('akun.roles', 1)
            ->where('akun.roles.0', 'hod'));
});

it('exposes the akun payload to a super admin holding zero permissions', function () {
    [$employee, $akun] = karyawanWithAkun();
    $akun->assignRole('karyawan');

    $this->actingAs(makeSuperAdmin(createUser()))
        ->get(route('employees.show', ['tenant' => 'hq', 'employee' => $employee]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('akun.exists', true)
            ->where('akun.roles.0', 'karyawan'));
});

it('marks the belum-ada-akun state for a permitted viewer', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(akunViewer(['akun.read']))
        ->get(route('employees.show', ['tenant' => 'hq', 'employee' => $employee]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('akun.exists', false));
});
