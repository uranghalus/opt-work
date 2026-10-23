<?php

use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Employee;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Task 1 — Foundation Daily Work FR-5.x: dua tabel baru (template rutin
 * per karyawan + instance harian) dengan konvensi repo (HasUuids,
 * BelongsToTenant) dan factory.
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

function makeDailyWorkEmployee(): Employee
{
    return Employee::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
    ]);
}

test('factory membuat template dan daily work dengan tenant dan relasi terisi', function () {
    $employee = makeDailyWorkEmployee();

    $template = DailyWorkTemplate::factory()->create([
        'employee_id' => $employee->getKey(),
    ]);

    $dailyWork = DailyWork::factory()->create([
        'employee_id' => $employee->getKey(),
    ]);

    expect($template->tenant_id)->toBe($employee->tenant_id)
        ->and($template->employee->is($employee))->toBeTrue()
        ->and($template->is_active)->toBeTrue()
        ->and($dailyWork->tenant_id)->toBe($employee->tenant_id)
        ->and($dailyWork->employee->is($employee))->toBeTrue()
        ->and($dailyWork->status)->toBe('pending')
        ->and($dailyWork->source)->toBe('adhoc');
});

test('cast weekdays array dan work_date Carbon', function () {
    $employee = makeDailyWorkEmployee();

    $template = DailyWorkTemplate::factory()->create([
        'employee_id' => $employee->getKey(),
        'weekdays' => [1, 3, 5],
    ]);

    $dailyWork = DailyWork::factory()->create([
        'employee_id' => $employee->getKey(),
        'work_date' => '2026-10-26',
        'done_at' => '2026-10-26 10:00:00',
    ]);

    expect($template->weekdays)->toBe([1, 3, 5])
        ->and($dailyWork->work_date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($dailyWork->work_date->toDateString())->toBe('2026-10-26')
        ->and($dailyWork->done_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('state forTemplate mewarisi title description dan source template', function () {
    $employee = makeDailyWorkEmployee();

    $template = DailyWorkTemplate::factory()->create([
        'employee_id' => $employee->getKey(),
        'title' => 'Cek AC Ruang Server',
        'description' => 'Periksa suhu dan filter.',
    ]);

    $dailyWork = DailyWork::factory()->forTemplate($template, '2026-10-26')->create();

    expect($dailyWork->title)->toBe('Cek AC Ruang Server')
        ->and($dailyWork->description)->toBe('Periksa suhu dan filter.')
        ->and($dailyWork->source)->toBe('template')
        ->and($dailyWork->template_id)->toBe($template->getKey())
        ->and($dailyWork->work_date->toDateString())->toBe('2026-10-26');
});
