<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderRejected;
use App\Notifications\WorkOrderVerified;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * FR-1.8: HOD memverifikasi hasil submit — approve → closed,
 * reject + catatan revisi → revision (deadline tidak direset).
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
 * @return array{0: WorkOrder, 1: User, 2: User, 3: User} [wo, hod, assigneeUser, requester]
 */
function makeVerifiableWorkOrder(array $overrides = []): array
{
    $tenant = Tenant::where('code', 'hq')->first();
    $hod = createUser(['tenant_id' => $tenant->id]);
    $department = Department::create([
        'kode_department' => 'DEP-OPS',
        'nama_department' => 'Operations',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => $tenant->id]);
    $assigneeUser = createUser(['tenant_id' => $tenant->id]);
    $assigneeEmployee = Employee::factory()->create([
        'department_id' => $department->getKey(),
        'tenant_id' => $tenant->id,
    ]);
    $assigneeUser->forceFill(['employee_id' => $assigneeEmployee->getKey()])->save();

    $workOrder = WorkOrder::create(array_merge([
        'nomor_wo' => 'WO-VER-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Verify Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::PendingVerify,
        'assigned_at' => now(),
        'deadline_date' => now()->addDays(6),
        'assigned_employees' => [$assigneeEmployee->getKey()],
        'result_notes' => 'Hasil pekerjaan.',
        'is_escalated' => false,
        'extend_count' => 0,
    ], $overrides));

    return [$workOrder, $hod, $assigneeUser, $requester];
}

function verifyActor(): User
{
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('work-order.verify');

    return $user;
}

it('approves the submitted result and closes the work order', function () {
    Notification::fake();
    [$workOrder, , $assigneeUser, $requester] = makeVerifiableWorkOrder();
    $hod = verifyActor();

    $this->actingAs($hod)->post(route('work-orders.verify', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertRedirect(route('work-orders.show', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]));

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Closed);

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'user_id' => $hod->getKey(),
        'action' => 'verified',
        'from_status' => WorkOrderStatus::PendingVerify->value,
        'to_status' => WorkOrderStatus::Closed->value,
    ]);

    Notification::assertSentTo($assigneeUser, WorkOrderVerified::class);
    Notification::assertSentTo($requester, WorkOrderVerified::class);
});

it('rejects the result with revision notes and keeps the deadline', function () {
    Notification::fake();
    [$workOrder, , $assigneeUser, $requester] = makeVerifiableWorkOrder();
    $hod = verifyActor();
    $deadline = $workOrder->deadline_date->toDateString();

    $this->actingAs($hod)->post(route('work-orders.reject', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'revision_notes' => 'Kabel belum dirapikan, kerjakan ulang bagian itu.',
    ])->assertRedirect();

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::Revision)
        ->and($workOrder->hod_notes)->toBe('Kabel belum dirapikan, kerjakan ulang bagian itu.')
        ->and($workOrder->deadline_date->toDateString())->toBe($deadline);

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'user_id' => $hod->getKey(),
        'action' => 'rejected',
        'from_status' => WorkOrderStatus::PendingVerify->value,
        'to_status' => WorkOrderStatus::Revision->value,
    ]);

    Notification::assertSentTo($assigneeUser, WorkOrderRejected::class);
    Notification::assertSentTo($requester, WorkOrderRejected::class);
});

it('requires revision notes when rejecting', function () {
    [$workOrder] = makeVerifiableWorkOrder();
    $hod = verifyActor();

    $this->actingAs($hod)->post(route('work-orders.reject', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [])->assertSessionHasErrors('revision_notes');

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::PendingVerify);
});

it('rejects verify and reject without the work-order.verify permission', function () {
    [$workOrder] = makeVerifiableWorkOrder();
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $this->actingAs($user)->post(route('work-orders.verify', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertForbidden();

    $this->actingAs($user)->post(route('work-orders.reject', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'revision_notes' => 'Tidak berizin.',
    ])->assertForbidden();

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::PendingVerify);
});

it('refuses verify before the result is submitted', function () {
    [$workOrder] = makeVerifiableWorkOrder(['status' => WorkOrderStatus::Assigned]);
    $hod = verifyActor();

    $this->actingAs($hod)->post(route('work-orders.verify', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertStatus(409);

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Assigned);
});

it('cannot assign a closed work order', function () {
    [$workOrder] = makeVerifiableWorkOrder(['status' => WorkOrderStatus::Closed]);
    $assigner = verifyActor();
    $assigner->givePermissionTo('work-order.assign');
    $employee = Employee::query()
        ->where('department_id', $workOrder->target_department_id)
        ->firstOrFail();

    $this->actingAs($assigner)->post(route('work-orders.assign', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'employees' => [$employee->getKey()],
    ])->assertStatus(409);

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Closed);
});

it('allows re-assign while the WO is assigned and keeps the SLA clock', function () {
    Notification::fake();
    [$workOrder] = makeVerifiableWorkOrder(['status' => WorkOrderStatus::Assigned]);
    $assigner = verifyActor();
    $assigner->givePermissionTo('work-order.assign');

    $secondEmployee = Employee::factory()->create([
        'department_id' => $workOrder->target_department_id,
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
    ]);

    $assignedAtBefore = $workOrder->assigned_at?->toDateTimeString();
    $deadlineBefore = $workOrder->deadline_date?->toDateString();

    $this->actingAs($assigner)->post(route('work-orders.assign', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'employees' => [$secondEmployee->getKey()],
        'assignment_notes' => 'Ganti pelaksana: karyawan awal cuti.',
    ])->assertRedirect();

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::Assigned)
        ->and($workOrder->assigned_employees)->toBe([$secondEmployee->getKey()])
        ->and($workOrder->assigned_at?->toDateTimeString())->toBe($assignedAtBefore)
        ->and($workOrder->deadline_date?->toDateString())->toBe($deadlineBefore);

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'assigned',
    ]);
});

it('still refuses assign from pending_verify', function () {
    [$workOrder] = makeVerifiableWorkOrder(['status' => WorkOrderStatus::PendingVerify]);
    $assigner = verifyActor();
    $assigner->givePermissionTo('work-order.assign');
    $employee = Employee::query()
        ->where('department_id', $workOrder->target_department_id)
        ->firstOrFail();

    $this->actingAs($assigner)->post(route('work-orders.assign', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'employees' => [$employee->getKey()],
    ])->assertStatus(409);
});
