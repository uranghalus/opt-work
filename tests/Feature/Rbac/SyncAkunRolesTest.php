<?php

use App\Models\Department;
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
function akunPair(): array
{
    $employee = Employee::factory()->create();
    $akun = User::factory()->create(['employee_id' => $employee->id]);

    return [$employee, $akun];
}

/** An editor holding exactly the given akun permissions, inside the current cabang. */
function akunEditor(array $permissions): User
{
    $user = createUser(['tenant_id' => Tenant::current()?->id]);
    $user->givePermissionTo($permissions);

    return $user;
}

function syncAkunRolesRoute(Employee $employee): string
{
    return route('employees.akun.roles.sync', [
        'tenant' => 'hq',
        'employee' => $employee->id,
    ]);
}

it('rejects the role sync without akun.update', function () {
    [$employee] = akunPair();

    $this->actingAs(createUser(['tenant_id' => $this->hq->id]))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['hod']])
        ->assertForbidden();

    expect($employee->user()->first()->hasRole('hod'))->toBeFalse();
});

it('syncs roles for a super admin holding zero permissions', function () {
    [$employee, $akun] = akunPair();

    $this->actingAs(makeSuperAdmin(createUser(['tenant_id' => $this->hq->id])))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['hod']])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($akun->fresh()->getRoleNames()->toArray())->toBe(['hod']);
});

it('replaces the current role set with exactly the submitted set', function () {
    [$employee, $akun] = akunPair();
    $akun->assignRole('viewer');

    $this->actingAs(akunEditor(['akun.update']))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['hod', 'viewer']])
        ->assertRedirect();

    expect($akun->fresh()->getRoleNames()->sort()->values()->toArray())
        ->toBe(['hod', 'viewer']);
});

it('auto-attaches karyawan when field_staff is granted', function () {
    [$employee, $akun] = akunPair();

    $this->actingAs(akunEditor(['akun.update']))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['field_staff']])
        ->assertRedirect();

    expect($akun->fresh()->getRoleNames()->sort()->values()->toArray())
        ->toBe(['field_staff', 'karyawan']);
});

it('refuses to drop karyawan from a field_staff holder', function () {
    [$employee, $akun] = akunPair();
    $akun->assignRole(['field_staff', 'karyawan']);

    // The submission keeps field_staff but silently drops its pair.
    $this->actingAs(akunEditor(['akun.update']))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['field_staff']])
        ->assertRedirect()
        ->assertSessionHasErrors('roles');

    // Nothing written: the old pair must survive untouched.
    expect($akun->fresh()->getRoleNames()->sort()->values()->toArray())
        ->toBe(['field_staff', 'karyawan']);
});

it('keeps karyawan in place when field_staff is revoked', function () {
    [$employee, $akun] = akunPair();
    $akun->assignRole('field_staff');

    $this->actingAs(akunEditor(['akun.update']))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['karyawan']])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($akun->fresh()->getRoleNames()->toArray())->toBe(['karyawan']);
});

it('rejects super_admin in the submission regardless of who asks', function () {
    [$employee, $akun] = akunPair();

    $this->actingAs(makeSuperAdmin(createUser(['tenant_id' => $this->hq->id])))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['hod', 'super_admin']])
        ->assertSessionHasErrors('roles');

    expect($akun->fresh()->getRoleNames()->toArray())->toBe([]);
});

it('refuses to edit an akun that already holds super_admin', function () {
    [$employee, $akun] = akunPair();
    $akun->assignRole('super_admin');

    $this->actingAs(makeSuperAdmin(createUser(['tenant_id' => $this->hq->id])))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['hod']])
        ->assertRedirect()
        ->assertSessionHasErrors('roles');

    expect($akun->fresh()->getRoleNames()->toArray())->toBe(['super_admin']);
});

it('leaves identity flags and department links byte-identical after a sync', function () {
    [$employee, $akun] = akunPair();
    $akun->forceFill(['is_super_admin' => false, 'is_hod' => true])->save();

    $department = Department::factory()->create();
    $department->forceFill(['hod_user_id' => $akun->id])->save();
    $department->refresh();

    $this->actingAs(akunEditor(['akun.update']))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['viewer']])
        ->assertRedirect();

    $freshAkun = $akun->fresh();
    $freshDepartment = $department->fresh();

    expect($freshAkun->is_super_admin)->toBeFalse()
        ->and($freshAkun->is_hod)->toBeTrue()
        ->and($freshDepartment->hod_user_id)->toBe($akun->id)
        ->and($freshDepartment->manager_user_id)->toBeNull();
});

it('refuses the sync for a karyawan without a linked akun', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(akunEditor(['akun.update']))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['hod']])
        ->assertForbidden();
});

it('rejects unknown role names', function () {
    [$employee] = akunPair();

    $this->actingAs(akunEditor(['akun.update']))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['not-a-role']])
        ->assertSessionHasErrors('roles');

    $akun = $employee->user()->first();

    expect($akun->getRoleNames()->toArray())->toBe([]);
});

it('recognizes a freshly assigned role on the akun’s next request without re-login', function () {
    [$employee, $akun] = akunPair();
    $akun->forceFill(['tenant_id' => $this->hq->id])->save();
    $akun->assignRole('viewer');

    $seeAkunAs = fn (User $sessionUser) => $this->actingAs($sessionUser)
        ->get(route('employees.show', ['tenant' => 'hq', 'employee' => $employee]))
        ->assertOk();

    // Before the sync the akun cannot even read the roles surface.
    $seeAkunAs($akun)->assertInertia(fn ($page) => $page->where('akun', null));

    // A manager grants admin_tenant while the akun's session stays alive.
    $this->actingAs(akunEditor(['akun.update']))
        ->post(syncAkunRolesRoute($employee), ['roles' => ['admin_tenant']])
        ->assertRedirect()
        ->assertSessionHas('success');

    // The next request re-resolves the user by session ID like the guard does
    // in production (fresh model instance, fresh role pivot) — no re-login.
    // actingAs on the original instance would pin its already-loaded roles
    // relation, which no real request ever does.
    $seeAkunAs(User::find($akun->id))->assertInertia(fn ($page) => $page
        ->where('akun.exists', true)
        ->where('akun.email', $akun->email));
});
