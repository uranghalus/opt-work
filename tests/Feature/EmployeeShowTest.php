<?php

use App\Models\Employee;
use App\Models\Role;
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

/** The show route for an employee in the hq test tenant. */
function employeeShowRoute(Employee $employee): string
{
    return route('employees.show', ['tenant' => 'hq', 'employee' => $employee->id]);
}

it('serializes a minimal employee payload and reports the missing account', function () {
    $employee = Employee::factory()->create([
        'tenant_id' => $this->hq->id,
        'last_login_ip' => '10.0.0.1',
        'optigate_user_id' => 'portal-employee-1',
    ]);

    $this->actingAs(makeSuperAdmin(createUser()))
        ->get(employeeShowRoute($employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/show')
            ->where('employee.has_account', false)
            ->where('employee.user', null)
            ->where('employee.is_banned', false)
            ->where('akun.exists', false)
            ->missing('employee.last_login_ip')
            ->missing('employee.optigate_user_id')
            ->missing('employee.tenant_id'));
});

it('reports a linked account without exposing it to employee-only readers', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $this->hq->id,
        'email' => 'linked@example.com',
    ]);
    $akun->assignRole('hod');

    $viewer = createUser(['tenant_id' => $this->hq->id]);
    $viewer->givePermissionTo('employee.read');

    $this->actingAs($viewer)
        ->get(employeeShowRoute($employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employee.has_account', true)
            ->where('akun', null)
            ->missing('employee.user'));
});

it('shows the linked account and ban state to a viewer with akun.read', function () {
    $employee = Employee::factory()->create([
        'tenant_id' => $this->hq->id,
        'banned_at' => now(),
    ]);
    $akun = User::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $this->hq->id,
        'email' => 'linked@example.com',
    ]);
    $akun->assignRole('hod');

    $viewer = createUser(['tenant_id' => $this->hq->id]);
    $viewer->givePermissionTo(['employee.read', 'akun.read']);

    $this->actingAs($viewer)
        ->get(employeeShowRoute($employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employee.has_account', true)
            ->where('employee.is_banned', true)
            ->where('employee.banned_at', fn ($value) => $value !== null)
            ->where('employee.user.email', 'linked@example.com')
            ->where('akun.exists', true)
            ->where('akun.roles.0', 'hod'));
});

it('offers database roles except super_admin to a superadmin', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $this->hq->id,
    ]);
    Role::create(['name' => 'auditor_khusus', 'guard_name' => 'web']);

    $this->actingAs(makeSuperAdmin(createUser(['tenant_id' => $this->hq->id])))
        ->get(employeeShowRoute($employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'assignable_roles',
            fn ($roles) => collect($roles)->contains('auditor_khusus')
                && collect($roles)->contains('guest')
                && ! collect($roles)->contains('super_admin'),
        ));
});

it('does not expose role management to an admin_tenant with akun.update', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $this->hq->id,
    ]);

    $admin = createUser(['tenant_id' => $this->hq->id]);
    $admin->assignRole('admin_tenant');

    $this->actingAs($admin)
        ->get(employeeShowRoute($employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('assignable_roles', []));
});

it('forbids an employee page without employee.read', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(createUser(['tenant_id' => $this->hq->id]))
        ->get(employeeShowRoute($employee))
        ->assertForbidden();
});
