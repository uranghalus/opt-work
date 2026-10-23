<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;

/**
 * D1 — visibilitas pelaksana (design .scratch/wo-review-decisions/design.md):
 * daftar WO dan timeline harus bisa diakses Requester, HOD/deputy dept
 * tujuan, DAN pelaksana yang di-assign (assigned_employees) — pelaksana lintas
 * department sebelumnya hanya menjangkau WO miliknya lewat link notifikasi,
 * memblokir FR-1.7 submit.
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

function visibilityEmployee(Department $department): Employee
{
    return Employee::create([
        'nik_employee' => fake()->unique()->numerify('NIK-########'),
        'nama_employee' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'department_id' => $department->getKey(),
    ]);
}

it('lists assigned work orders for the cross-department assignee in the index', function () {
    $targetDepartment = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
    ]);
    $otherDepartment = Department::create([
        'kode_department' => 'DEP-FM',
        'nama_department' => 'Maintenance',
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $assigneeEmployee = visibilityEmployee($otherDepartment);
    $assigneeUser = createUser([
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
        'employee_id' => $assigneeEmployee->getKey(),
    ]);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-TEST-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $targetDepartment->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Perbaikan AC',
        'description' => 'AC ruang rapat mati',
        'status' => WorkOrderStatus::Assigned,
        'hod_action' => 'execute_immediately',
        'assigned_employees' => [$assigneeEmployee->getKey()],
        'deadline_date' => now()->addDay()->toDateString(),
    ]);

    $this
        ->actingAs($assigneeUser)
        ->get('/hq/work-orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/index')
            ->where('workOrders.data.0.id', $workOrder->getKey())
            ->where('workOrders.data.0.deadline_state', 'due_soon')
            ->where('workOrders.data.0.deadline_label', 'Deadline besok'));
});

it('keeps the timeline scoped the same way for the assignee', function () {
    $targetDepartment = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
    ]);
    $otherDepartment = Department::create([
        'kode_department' => 'DEP-FM',
        'nama_department' => 'Maintenance',
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $assigneeEmployee = visibilityEmployee($otherDepartment);
    $assigneeUser = createUser([
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
        'employee_id' => $assigneeEmployee->getKey(),
    ]);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-TEST-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $targetDepartment->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Ganti lampu gudang',
        'description' => 'Lampu gudang mati',
        'status' => WorkOrderStatus::Assigned,
        'hod_action' => 'schedule',
        'scheduled_date' => Carbon::today()->startOfWeek(Carbon::MONDAY)->toDateString(),
        'assigned_employees' => [$assigneeEmployee->getKey()],
    ]);

    $this
        ->actingAs($assigneeUser)
        ->get('/hq/work-orders/timeline')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/timeline')
            ->where('days.0.workOrders.0.id', $workOrder->getKey()));
});

it('keeps an unrelated karyawan out of the index', function () {
    $targetDepartment = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $bystander = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    WorkOrder::create([
        'nomor_wo' => 'WO-TEST-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $targetDepartment->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Perbaikan AC',
        'description' => 'AC ruang rapat mati',
        'status' => WorkOrderStatus::WaitingHod,
    ]);

    $this
        ->actingAs($bystander)
        ->get('/hq/work-orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/index')
            ->where('workOrders.data', []));
});

it('respects requester and department head visibility unchanged', function () {
    $targetDepartment = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
        'hod_user_id' => null,
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $targetDepartment->forceFill(['hod_user_id' => $hod->getKey()])->save();

    WorkOrder::create([
        'nomor_wo' => 'WO-TEST-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $targetDepartment->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Perbaikan AC',
        'description' => 'AC ruang rapat mati',
        'status' => WorkOrderStatus::WaitingHod,
    ]);

    foreach ([$requester, $hod] as $viewer) {
        $this
            ->actingAs($viewer)
            ->get('/hq/work-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('work-orders/index')
                ->where('workOrders.data', fn ($rows) => count($rows) === 1));
    }
});
