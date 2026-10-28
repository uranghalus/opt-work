<?php

use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\ExtendRequest;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkData;
use App\Models\WorkDataPekerja;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkPlanning;
use App\WorkOrderStatus;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\TenantSeeder;

/**
 * Smoke test DemoSeeder: dataset demo lengkap + idempoten ringan
 * (re-run tidak menduplikasi apa pun).
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    (new TenantSeeder)->run();
    (new DemoSeeder)->run();
});

/**
 * Seeder mengakhiri run() dengan Tenant::forgetCurrent(), sehingga query
 * model ber-scope tenant harus memakai withoutGlobalScope di sini.
 */
function demoCount(string $model): int
{
    return $model::withoutGlobalScope('tenant')->count();
}

function demoUser(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

it('seeds the demo organization with linked accounts and roles', function () {
    $budi = demoUser('budi.hod@appdutamall.com');

    expect($budi->hasRole('hod'))->toBeTrue()
        ->and($budi->is_hod)->toBeTrue()
        ->and($budi->employee_id)->not->toBeNull()
        ->and($budi->phone)->toBe('081347233907')
        ->and(demoUser('sari.wulandari@appdutamall.com')->hasRole('karyawan'))->toBeTrue()
        ->and(demoUser('wawan.tl@appdutamall.com')->hasRole('team_leader'))->toBeTrue()
        ->and(demoUser('admin.tenant@appdutamall.com')->hasRole('admin_tenant'))->toBeTrue()
        ->and(demoUser('gm@appdutamall.com')->hasRole('general_manager'))->toBeTrue()
        ->and(demoUser('dgm@appdutamall.com')->hasRole('deputy_general_manager'))->toBeTrue()
        ->and(demoUser('viewer@appdutamall.com')->hasRole('viewer'))->toBeTrue()
        ->and(demoUser('fajar.staf@appdutamall.com')->hasRole('staf_admin_dept'))->toBeTrue();

    $budiEmployee = Employee::withoutGlobalScope('tenant')->findOrFail($budi->employee_id);
    expect($budiEmployee->number)->toBe('081347233907');

    $mtc = Department::withoutGlobalScope('tenant')->where('kode_department', 'DEP-MTC')->firstOrFail();
    expect($mtc->hod_user_id)->toBe($budi->getKey())
        ->and($mtc->hod_employee_id)->toBe($budiEmployee->getKey())
        ->and($mtc->manager_user_id)->toBe(demoUser('wawan.tl@appdutamall.com')->getKey());

    expect(demoCount(Division::class))->toBe(3)
        ->and(demoCount(Department::class))->toBe(4)
        ->and(demoCount(Position::class))->toBe(11)
        ->and(demoCount(Employee::class))->toBe(16);
});

it('seeds work orders across every status with dependents', function () {
    expect(demoCount(WorkOrder::class))->toBe(12)
        ->and(WorkOrder::withoutGlobalScope('tenant')->where('status', WorkOrderStatus::WaitingHod->value)->count())->toBe(2)
        ->and(WorkOrder::withoutGlobalScope('tenant')->where('status', WorkOrderStatus::HodApproved->value)->count())->toBe(1)
        ->and(WorkOrder::withoutGlobalScope('tenant')->where('status', WorkOrderStatus::Scheduled->value)->count())->toBe(2)
        ->and(WorkOrder::withoutGlobalScope('tenant')->where('status', WorkOrderStatus::Assigned->value)->count())->toBe(2)
        ->and(WorkOrder::withoutGlobalScope('tenant')->where('status', WorkOrderStatus::InProgress->value)->count())->toBe(1)
        ->and(WorkOrder::withoutGlobalScope('tenant')->where('status', WorkOrderStatus::PendingVerify->value)->count())->toBe(1)
        ->and(WorkOrder::withoutGlobalScope('tenant')->where('status', WorkOrderStatus::Revision->value)->count())->toBe(1)
        ->and(WorkOrder::withoutGlobalScope('tenant')->where('status', WorkOrderStatus::Closed->value)->count())->toBe(2)
        ->and(demoCount(WorkOrderEvent::class))->toBe(31)
        ->and(demoCount(WorkPlanning::class))->toBe(2)
        ->and(WorkPlanning::withoutGlobalScope('tenant')->where('status_jadwal', 'planned')->count())->toBe(1)
        ->and(WorkPlanning::withoutGlobalScope('tenant')->where('status_jadwal', 'pending_extend_approval')->count())->toBe(1)
        ->and(demoCount(ExtendRequest::class))->toBe(3)
        ->and(ExtendRequest::withoutGlobalScope('tenant')->where('status', 'approved')->count())->toBe(1)
        ->and(ExtendRequest::withoutGlobalScope('tenant')->where('status', 'rejected')->count())->toBe(1)
        ->and(ExtendRequest::withoutGlobalScope('tenant')->where('status', 'pending_hod')->count())->toBe(1)
        ->and(demoCount(WorkData::class))->toBe(2)
        ->and(WorkData::withoutGlobalScope('tenant')->whereNotNull('finalized_at')->count())->toBe(1)
        ->and(demoCount(WorkDataPekerja::class))->toBe(4);
});

it('seeds the escalated work order with stamps and extend trail', function () {
    $overdue = WorkOrder::withoutGlobalScope('tenant')->where('nomor_wo', 'WO-HQ-20261011-0011')->firstOrFail();

    expect($overdue->is_escalated)->toBeTrue()
        ->and($overdue->escalation_h3_sent_at)->not->toBeNull()
        ->and($overdue->extend_count)->toBe(1)
        ->and($overdue->extended_at)->not->toBeNull()
        ->and($overdue->deadline_date->toDateString())->toBeGreaterThan($overdue->assigned_at->toDateString())
        ->and(WorkOrderEvent::withoutGlobalScope('tenant')->where('work_order_id', $overdue->getKey())->where('action', 'extend_approved')->count())->toBe(1)
        ->and(WorkOrderEvent::withoutGlobalScope('tenant')->where('work_order_id', $overdue->getKey())->where('action', 'extend_rejected')->count())->toBe(1);
});

it('seeds daily work templates, today instances, and history', function () {
    expect(demoCount(DailyWorkTemplate::class))->toBe(4)
        ->and(demoCount(DailyWork::class))->toBeGreaterThanOrEqual(4)
        ->and(DailyWork::withoutGlobalScope('tenant')->whereDate('work_date', now()->toDateString())->where('source', 'adhoc')->count())->toBe(2)
        // 2 instance verifikasi demo (Task 8): pending_verify Sari + done Rudi, sengaja template_id null.
        ->and(DailyWork::withoutGlobalScope('tenant')->where('source', 'template')->whereNull('template_id')->count())->toBe(2);
});

it('seeds in-app notification history for the demo bell', function () {
    $budi = demoUser('budi.hod@appdutamall.com');

    expect($budi->notifications()->count())->toBe(5)
        ->and($budi->unreadNotifications()->count())->toBe(4)
        ->and(demoUser('sari.wulandari@appdutamall.com')->notifications()->count())->toBe(3)
        ->and(demoUser('gm@appdutamall.com')->notifications()->count())->toBe(1)
        ->and(demoUser('dgm@appdutamall.com')->notifications()->count())->toBe(1);
});

it('is idempotent: re-running the seeder duplicates nothing', function () {
    $before = [
        'users' => User::count(),
        'employees' => demoCount(Employee::class),
        'work_orders' => demoCount(WorkOrder::class),
        'events' => demoCount(WorkOrderEvent::class),
        'plannings' => demoCount(WorkPlanning::class),
        'extends' => demoCount(ExtendRequest::class),
        'work_data' => demoCount(WorkData::class),
        'pekerja' => demoCount(WorkDataPekerja::class),
        'templates' => demoCount(DailyWorkTemplate::class),
        'daily_works' => demoCount(DailyWork::class),
    ];

    (new DemoSeeder)->run();

    expect(User::count())->toBe($before['users'])
        ->and(demoCount(Employee::class))->toBe($before['employees'])
        ->and(demoCount(WorkOrder::class))->toBe($before['work_orders'])
        ->and(demoCount(WorkOrderEvent::class))->toBe($before['events'])
        ->and(demoCount(WorkPlanning::class))->toBe($before['plannings'])
        ->and(demoCount(ExtendRequest::class))->toBe($before['extends'])
        ->and(demoCount(WorkData::class))->toBe($before['work_data'])
        ->and(demoCount(WorkDataPekerja::class))->toBe($before['pekerja'])
        ->and(demoCount(DailyWorkTemplate::class))->toBe($before['templates'])
        ->and(demoCount(DailyWork::class))->toBe($before['daily_works']);
});
