<?php

use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Task 3 — pratinjau HOD: daftar harian satu karyawan bawahan pada satu
 * tanggal (materialize-on-view juga dijalankan — pratinjau yang sama
 * yang dilihat karyawan, FR-5.3).
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
 * @return array{0: User, 1: Employee, 2: Department}
 */
function makeHodWithSubordinateForTeam(): array
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $department = Department::factory()->create(['tenant_id' => $tenantId]);

    $hodEmployee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $hod = createUser(['tenant_id' => $tenantId, 'employee_id' => $hodEmployee->getKey()]);
    $hod->givePermissionTo('daily-work.read');

    $subordinate = Employee::factory()->create([
        'tenant_id' => $tenantId,
        'department_id' => $department->getKey(),
    ]);

    $department->update(['hod_user_id' => $hod->getKey()]);

    return [$hod, $subordinate, $department];
}

it('previews a subordinate daily list and materializes templates for that date', function () {
    [$hod, $subordinate] = makeHodWithSubordinateForTeam();

    // Template aktif Senin (weekday 1) — 2026-10-26 adalah Senin.
    $template = DailyWorkTemplate::factory()->create([
        'employee_id' => $subordinate->getKey(),
        'title' => 'Patroli pagi',
        'weekdays' => [1],
    ]);

    $this->actingAs($hod)
        ->get('/hq/daily-work/team?employee='.$subordinate->getKey().'&date=2026-10-26')
        ->assertOk();

    // Materialize-on-view terjadi juga di pratinjau HOD.
    $materialized = DailyWork::query()
        ->where('employee_id', $subordinate->getKey())
        ->whereDate('work_date', '2026-10-26')
        ->firstOrFail();

    expect($materialized->title)->toBe('Patroli pagi')
        ->and($materialized->template_id)->toBe($template->getKey());
});

it('rejects the team preview for an employee outside scope with 403', function () {
    [$hod] = makeHodWithSubordinateForTeam();
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $outsider = Employee::factory()->create(['tenant_id' => $tenantId]);

    $this->actingAs($hod)
        ->get('/hq/daily-work/team?employee='.$outsider->getKey())
        ->assertForbidden();
});

it('rejects the team preview without the read permission with 403', function () {
    [$hod, $subordinate] = makeHodWithSubordinateForTeam();
    $hod->revokePermissionTo('daily-work.read');

    $this->actingAs($hod)
        ->get('/hq/daily-work/team?employee='.$subordinate->getKey())
        ->assertForbidden();
});
