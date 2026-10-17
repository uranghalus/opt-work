<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Notifications\WorkOrderAssigned;
use App\Services\DeadlineCalculator;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Ticket 03 of wo-slice-1: assign endpoint — 1+ karyawan pelaksana,
 * assigned_at set once, deadline computed from the assign date, assignee
 * notifications through linked akuns (the gap opti-work2 never closed),
 * history event.
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    createTestTenant([
        'optigate_company_id' => 2,
        'code' => 'plant-1',
        'name' => 'Plant 1',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

function reviewedWorkOrder(WorkOrderCategory $category = WorkOrderCategory::Normal): WorkOrder
{
    initTenant('hq');
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-TEST-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => $category,
        'title' => 'Perbaikan AC',
        'description' => 'AC ruang rapat mati',
        'status' => WorkOrderStatus::HodApproved,
        'hod_action' => 'execute_immediately',
    ]);

    $workOrder->refresh();

    return $workOrder;
}

function departmentEmployee(Department $department): Employee
{
    return Employee::create([
        'nik_employee' => fake()->unique()->numerify('NIK-########'),
        'nama_employee' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'department_id' => $department->getKey(),
    ]);
}

function assignUser(): User
{
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('work-order.assign');

    return $user;
}

it('assigns employees, sets the assign date, and computes the deadline', function () {
    Notification::fake();
    $workOrder = reviewedWorkOrder();
    $department = $workOrder->targetDepartment;
    $linked = departmentEmployee($department);
    $unlinked = departmentEmployee($department);
    $linkedUser = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id, 'employee_id' => $linked->getKey()]);

    $response = $this
        ->actingAs(assignUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/assign", [
            'employees' => [$linked->getKey(), $unlinked->getKey()],
            'assignment_notes' => 'Dikerjakan hari ini juga',
        ]);

    $response->assertRedirect();

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::Assigned);
    expect($workOrder->assigned_employees)->toBe([$linked->getKey(), $unlinked->getKey()]);
    expect($workOrder->assigned_at)->not->toBeNull();
    expect($workOrder->assignment_notes)->toBe('Dikerjakan hari ini juga');
    expect($workOrder->deadline_date?->toDateString())
        ->toBe(app(DeadlineCalculator::class)->calculate(WorkOrderCategory::Normal, $workOrder->assigned_at)->toDateString());

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'assigned',
        'from_status' => 'hod_approved',
        'to_status' => 'assigned',
    ]);
});

it('gives an urgent work order the 3-business-day deadline', function () {
    Notification::fake();
    $workOrder = reviewedWorkOrder(WorkOrderCategory::Accident);
    $employee = departmentEmployee($workOrder->targetDepartment);

    $this
        ->actingAs(assignUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/assign", [
            'employees' => [$employee->getKey()],
        ])
        ->assertRedirect();

    $workOrder->refresh();
    expect($workOrder->deadline_date?->toDateString())
        ->toBe(app(DeadlineCalculator::class)->calculate(WorkOrderCategory::Accident, $workOrder->assigned_at)->toDateString());
});

it('notifies only assignees with a linked akun', function () {
    Notification::fake();
    $workOrder = reviewedWorkOrder();
    $linked = departmentEmployee($workOrder->targetDepartment);
    $unlinked = departmentEmployee($workOrder->targetDepartment);
    $linkedUser = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id, 'employee_id' => $linked->getKey()]);

    $this
        ->actingAs(assignUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/assign", [
            'employees' => [$linked->getKey(), $unlinked->getKey()],
        ])
        ->assertRedirect();

    Notification::assertSentTo($linkedUser, WorkOrderAssigned::class);
    Notification::assertNotSentTo($workOrder->requester, WorkOrderAssigned::class);

    // The unlinked assignee is still recorded in the history meta.
    $event = WorkOrderEvent::query()
        ->where('work_order_id', $workOrder->getKey())
        ->where('action', 'assigned')
        ->sole();
    expect($event->meta['unlinked_employee_ids'])->toBe([$unlinked->getKey()]);
});

it('keeps the first assign date and deadline when the assignment is revised', function () {
    Notification::fake();
    $workOrder = reviewedWorkOrder();
    $first = departmentEmployee($workOrder->targetDepartment);
    $second = departmentEmployee($workOrder->targetDepartment);
    $firstAssignDate = today()->subDays(2)->startOfDay();

    $workOrder->forceFill(['assigned_at' => $firstAssignDate])->save();
    $workOrder->refresh();
    $originalDeadline = $workOrder->deadline_date ?? app(DeadlineCalculator::class)->calculate(WorkOrderCategory::Normal, $firstAssignDate);
    $workOrder->forceFill(['deadline_date' => $originalDeadline])->save();

    $this
        ->actingAs(assignUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/assign", [
            'employees' => [$second->getKey()],
        ])
        ->assertRedirect();

    $workOrder->refresh();
    expect($workOrder->assigned_at?->toDateString())->toBe($firstAssignDate->toDateString());
    expect($workOrder->deadline_date?->toDateString())->toBe($originalDeadline->toDateString());
    expect($workOrder->assigned_employees)->toBe([$second->getKey()]);
});

it('rejects employees outside the target department', function () {
    Notification::fake();
    $workOrder = reviewedWorkOrder();
    $otherDepartment = Department::create([
        'kode_department' => 'DEP-GA',
        'nama_department' => 'General Affairs',
    ]);
    $outsider = departmentEmployee($otherDepartment);

    $this
        ->actingAs(assignUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/assign", [
            'employees' => [$outsider->getKey()],
        ])
        ->assertSessionHasErrors('employees');

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::HodApproved);
});

it('refuses to assign before the HOD review', function () {
    Notification::fake();
    $workOrder = reviewedWorkOrder();
    $workOrder->forceFill(['status' => WorkOrderStatus::WaitingHod, 'hod_action' => null])->save();
    $employee = departmentEmployee($workOrder->targetDepartment);

    $this
        ->actingAs(assignUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/assign", [
            'employees' => [$employee->getKey()],
        ])
        ->assertStatus(409);
});

it('forbids assignment without the work-order.assign permission', function () {
    Notification::fake();
    $workOrder = reviewedWorkOrder();
    $employee = departmentEmployee($workOrder->targetDepartment);

    $this
        ->actingAs(createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]))
        ->post("/hq/work-orders/{$workOrder->getKey()}/assign", [
            'employees' => [$employee->getKey()],
        ])
        ->assertForbidden();

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::HodApproved);
});
