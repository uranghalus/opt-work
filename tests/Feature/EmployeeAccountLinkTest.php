<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;

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

function accountLinkRoute(Employee $employee, string $name = 'employees.akun.link'): string
{
    return route($name, [
        'tenant' => 'hq',
        'employee' => $employee->id,
    ]);
}

function accountEditor(array $permissions = ['akun.update']): User
{
    $user = createUser(['tenant_id' => Tenant::current()?->id]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('links an unlinked account from the same cabang', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($akun->fresh()->employee_id)->toBe($employee->id);
});

it('rejects an account from another cabang', function () {
    $other = createTestTenant([
        'optigate_company_id' => '9002',
        'code' => 'plant-1',
        'name' => 'Plant 1',
    ]);
    initTenant('hq');

    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $other->id]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertSessionHasErrors('user_id');

    expect($akun->fresh()->employee_id)->toBeNull();
});

it('rejects an account already linked to another employee', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $otherEmployee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertSessionHasErrors('user_id');

    expect($akun->fresh()->employee_id)->toBe($otherEmployee->id);
});

it('rejects the platform super admin account', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $superadmin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $superadmin->id])
        ->assertSessionHasErrors('user_id');

    expect($superadmin->fresh()->employee_id)->toBeNull();
});

it('rejects an account carrying the super_admin role', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);
    $akun->assignRole('super_admin');

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertSessionHasErrors('user_id');

    expect($akun->fresh()->employee_id)->toBeNull();
});

it('rejects linking when the employee already has an account', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $employee->id,
    ]);
    $candidate = User::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $candidate->id])
        ->assertSessionHasErrors('user_id');

    expect($candidate->fresh()->employee_id)->toBeNull();
});

it('unlinks the account without deleting it or its roles', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $employee->id,
    ]);
    $akun->assignRole('hod');

    $this->actingAs(accountEditor())
        ->delete(accountLinkRoute($employee, 'employees.akun.unlink'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($akun->fresh())
        ->employee_id->toBeNull()
        ->and($akun->fresh()->hasRole('hod'))->toBeTrue();
});

it('refuses to unlink a super admin account', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $superadmin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));
    $superadmin->forceFill(['employee_id' => $employee->id])->save();

    $this->actingAs(accountEditor())
        ->delete(accountLinkRoute($employee, 'employees.akun.unlink'))
        ->assertSessionHasErrors('akun');

    expect($superadmin->fresh()->employee_id)->toBe($employee->id);
});

it('requires akun.update for link and unlink', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);
    $viewer = createUser(['tenant_id' => $this->hq->id]);

    $this->actingAs($viewer)
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(accountLinkRoute($employee, 'employees.akun.unlink'))
        ->assertForbidden();
});

it('prevents duplicate employee links at the database level', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $employee->id,
    ]);

    expect(fn () => User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $employee->id,
    ]))->toThrow(QueryException::class);
});

it('offers only unlinked same-cabang accounts on the show page', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $available = User::factory()->create([
        'tenant_id' => $this->hq->id,
        'name' => 'Akun Tersedia',
        'email' => 'available@example.com',
    ]);

    $linkedEmployee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $linkedEmployee->id,
        'email' => 'linked@example.com',
    ]);

    $other = createTestTenant([
        'optigate_company_id' => '9002',
        'code' => 'plant-1',
        'name' => 'Plant 1',
    ]);
    initTenant('hq');
    User::factory()->create([
        'tenant_id' => $other->id,
        'email' => 'other@example.com',
    ]);

    $superadmin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));
    $superadmin->update(['email' => 'superadmin@example.com']);

    $editor = accountEditor(['employee.read', 'akun.read', 'akun.update']);
    // The editor's own account is a valid tenant account, so link it to a
    // different karyawan to keep this fixture's candidate set deterministic.
    $editor->forceFill([
        'employee_id' => Employee::factory()->create(['tenant_id' => $this->hq->id])->id,
    ])->save();

    $this->actingAs($editor)
        ->get(route('employees.show', ['tenant' => 'hq', 'employee' => $employee->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/show')
            ->has('available_accounts', 1)
            ->where('available_accounts.0.email', $available->email)
            ->where('employee.has_account', false));
});
