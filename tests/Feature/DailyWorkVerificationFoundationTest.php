<?php

use App\DailyWorkStatus;
use App\Models\DailyWork;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Schema;

/**
 * Task 1 — fondasi verifikasi Daily Work: kolom bukti + index (migrasi),
 * enum DailyWorkStatus, relasi verifier/rejector, factory states
 * submitted/verified/returnedByHod. Plan .scratch/daily-work-verification/plan.md.
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

it('adds the verification columns and index to daily_works', function () {
    foreach ([
        'proof_notes', 'proof_attachments', 'submitted_at',
        'verified_by', 'verified_at', 'rejected_by', 'rejected_at', 'rejection_note',
    ] as $column) {
        expect(Schema::hasColumn('daily_works', $column))->toBeTrue("kolom {$column} hilang");
    }

    expect(Schema::hasIndex('daily_works', 'daily_works_tenant_id_status_work_date_index'))->toBeTrue();
});

it('labels the DailyWorkStatus enum like the approved design', function () {
    expect(DailyWorkStatus::Pending->value)->toBe('pending')
        ->and(DailyWorkStatus::InProgress->value)->toBe('in_progress')
        ->and(DailyWorkStatus::PendingVerify->value)->toBe('pending_verify')
        ->and(DailyWorkStatus::Done->value)->toBe('done')
        ->and(DailyWorkStatus::Pending->label())->toBe('Belum')
        ->and(DailyWorkStatus::InProgress->label())->toBe('Proses')
        ->and(DailyWorkStatus::PendingVerify->label())->toBe('Perlu verifikasi HOD')
        ->and(DailyWorkStatus::Done->label())->toBe('Telah selesai')
        ->and(DailyWorkStatus::options())->toHaveCount(4);
});

/**
 * Daily Work milik karyawan skenario dalam tenant hq yang aktif.
 */
function makeDailyWorkForFactoryStates(): DailyWork
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();
    $employee = Employee::factory()->create(['tenant_id' => $tenantId]);

    return DailyWork::factory()->create([
        'employee_id' => $employee->getKey(),
        'work_date' => '2100-01-01',
    ]);
}

it('creates a submitted factory state pending verification', function () {
    $dailyWork = makeDailyWorkForFactoryStates();
    $submitted = DailyWork::factory()->submitted()->create([
        'employee_id' => $dailyWork->employee_id,
        'work_date' => '2100-01-01',
        'template_id' => null,
    ]);

    $submitted->refresh();

    expect($submitted->status)->toBe('pending_verify')
        ->and($submitted->proof_notes)->not->toBeNull()
        ->and($submitted->proof_attachments)->toHaveCount(2)
        ->and($submitted->submitted_at)->not->toBeNull()
        ->and($submitted->done_at)->not->toBeNull()
        ->and($submitted->rejection_note)->toBeNull();
});

it('marks a verified state with the verifier relation', function () {
    $dailyWork = makeDailyWorkForFactoryStates();

    // verified() = submitted() + status done + verified_by/at.
    $verified = DailyWork::factory()->verified()->create([
        'employee_id' => $dailyWork->employee_id,
        'work_date' => '2100-01-01',
        'template_id' => null,
    ]);

    $verified->refresh();

    expect($verified->status)->toBe('done')
        ->and($verified->verified_at)->not->toBeNull()
        ->and($verified->verified_by)->not->toBeNull()
        ->and($verified->verifier)->toBeInstanceOf(User::class);
});

it('marks a returned state keeping rejection details', function () {
    $dailyWork = makeDailyWorkForFactoryStates();

    $returned = DailyWork::factory()->returnedByHod()->create([
        'employee_id' => $dailyWork->employee_id,
        'work_date' => '2100-01-01',
        'template_id' => null,
    ]);

    $returned->refresh();

    expect($returned->status)->toBe('in_progress')
        ->and($returned->rejection_note)->not->toBeNull()
        ->and($returned->rejected_at)->not->toBeNull()
        ->and($returned->rejected_by)->not->toBeNull()
        ->and($returned->rejector)->toBeInstanceOf(User::class);
});
