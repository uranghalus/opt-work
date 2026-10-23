<?php

use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Task 3 — FR-5.1: HOD mengelola template tugas rutin per karyawan
 * (OQ-4 opsi A). Gate: daily-work.create/update/delete; scope: karyawan
 * pada department yang dipimpin HOD (atau deputy manager_user_id).
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    initTenant('hq');
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/**
 * HOD user + satu karyawan bawahan pada department yang dipimpinnya.
 *
 * @return array{0: User, 1: Employee, 2: Department}
 */
function makeHodWithSubordinate(): array
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $department = Department::factory()->create([
        'tenant_id' => $tenantId,
        'nama_department' => 'Teknik',
    ]);

    $hodEmployee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $hod = createUser(['tenant_id' => $tenantId, 'employee_id' => $hodEmployee->getKey()]);
    $hod->givePermissionTo(['daily-work.read', 'daily-work.create', 'daily-work.update', 'daily-work.delete']);

    $subordinate = Employee::factory()->create([
        'tenant_id' => $tenantId,
        'department_id' => $department->getKey(),
    ]);

    $department->update(['hod_user_id' => $hod->getKey()]);

    return [$hod, $subordinate, $department];
}

it('lets the HOD create a recurring template for a subordinate', function () {
    [$hod, $subordinate] = makeHodWithSubordinate();

    $this->actingAs($hod)
        ->post('/hq/daily-work/templates', [
            'employee_id' => $subordinate->getKey(),
            'title' => 'Cek chiller lantai 1',
            'weekdays' => [1, 3, 5],
        ])
        ->assertRedirect();

    $template = DailyWorkTemplate::query()->where('employee_id', $subordinate->getKey())->firstOrFail();
    expect($template->title)->toBe('Cek chiller lantai 1')
        ->and($template->weekdays)->toBe([1, 3, 5])
        ->and($template->is_active)->toBeTrue()
        ->and($template->created_by)->toBe($hod->getKey());
});

it('rejects a template for an employee outside the HOD department with 403', function () {
    [$hod] = makeHodWithSubordinate();
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $outsider = Employee::factory()->create(['tenant_id' => $tenantId]);

    $this->actingAs($hod)
        ->post('/hq/daily-work/templates', [
            'employee_id' => $outsider->getKey(),
            'title' => 'Tugas di luar scope',
            'weekdays' => [1],
        ])
        ->assertForbidden();

    expect(DailyWorkTemplate::count())->toBe(0);
});

it('rejects a HOD without the create permission with 403', function () {
    [$hod, $subordinate, $department] = makeHodWithSubordinate();
    $hod->revokePermissionTo('daily-work.create');

    $this->actingAs($hod)
        ->post('/hq/daily-work/templates', [
            'employee_id' => $subordinate->getKey(),
            'title' => 'Tanpa izin',
            'weekdays' => [1],
        ])
        ->assertForbidden();

    expect(DailyWorkTemplate::count())->toBe(0);
});

it('validates weekdays to integers 0..6 with at least one day', function () {
    [$hod, $subordinate] = makeHodWithSubordinate();

    $this->actingAs($hod)
        ->post('/hq/daily-work/templates', [
            'employee_id' => $subordinate->getKey(),
            'title' => 'Weekday invalid',
            'weekdays' => [7],
        ])
        ->assertSessionHasErrors('weekdays.0');

    expect(DailyWorkTemplate::count())->toBe(0);
});

it('lets the HOD update a template but keeps the owner employee', function () {
    [$hod, $subordinate] = makeHodWithSubordinate();

    $template = DailyWorkTemplate::factory()->create([
        'employee_id' => $subordinate->getKey(),
        'title' => 'Lama',
        'weekdays' => [1],
    ]);

    $this->actingAs($hod)
        ->patch("/hq/daily-work/templates/{$template->getKey()}", [
            'title' => 'Baru',
            'weekdays' => [2, 4],
            'is_active' => false,
        ])
        ->assertRedirect();

    $template->refresh();
    expect($template->title)->toBe('Baru')
        ->and($template->weekdays)->toBe([2, 4])
        ->and($template->is_active)->toBeFalse()
        ->and($template->employee_id)->toBe($subordinate->getKey());
});

it('lets the HOD delete a template but keeps the materialized history', function () {
    [$hod, $subordinate] = makeHodWithSubordinate();

    $template = DailyWorkTemplate::factory()->create([
        'employee_id' => $subordinate->getKey(),
    ]);

    // Instance harian yang terlanjur materialize (lineage ke template).
    $instance = DailyWork::factory()->forTemplate($template, '2026-10-26')->create();

    $this->actingAs($hod)
        ->delete("/hq/daily-work/templates/{$template->getKey()}")
        ->assertRedirect();

    expect(DailyWorkTemplate::count())->toBe(0);

    $instance->refresh();
    expect($instance->template_id)->toBeNull()
        ->and($instance->title)->not->toBeEmpty();
});

it('rejects deleting a template of an employee outside scope with 403', function () {
    [$hod] = makeHodWithSubordinate();
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $outsider = Employee::factory()->create(['tenant_id' => $tenantId]);
    $template = DailyWorkTemplate::factory()->create(['employee_id' => $outsider->getKey()]);

    $this->actingAs($hod)
        ->delete("/hq/daily-work/templates/{$template->getKey()}")
        ->assertForbidden();

    expect(DailyWorkTemplate::count())->toBe(1);
});

it('deputy manager can manage templates for the department', function () {
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $department = Department::factory()->create(['tenant_id' => $tenantId]);
    $subordinate = Employee::factory()->create([
        'tenant_id' => $tenantId,
        'department_id' => $department->getKey(),
    ]);

    $deputyEmployee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $deputy = createUser(['tenant_id' => $tenantId, 'employee_id' => $deputyEmployee->getKey()]);
    $deputy->givePermissionTo('daily-work.create');

    $department->update(['manager_user_id' => $deputy->getKey()]);

    $this->actingAs($deputy)
        ->post('/hq/daily-work/templates', [
            'employee_id' => $subordinate->getKey(),
            'title' => 'Dari deputy',
            'weekdays' => [1],
        ])
        ->assertRedirect();

    expect(DailyWorkTemplate::where('employee_id', $subordinate->getKey())->exists())->toBeTrue();
});
