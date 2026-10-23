<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkPlanning;
use App\Notifications\WorkOrderReviewed;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Ticket 02 of wo-slice-1: HOD review endpoint — the execution decision
 * (immediate vs scheduled) behind work-order.review, with the Accident
 * category forced to immediate execution and the decision recorded in
 * the work order history.
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

function createReviewTestWorkOrder(WorkOrderCategory $category = WorkOrderCategory::Normal): WorkOrder
{
    initTenant('hq');
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    return WorkOrder::create([
        'nomor_wo' => 'WO-TEST-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => $category,
        'title' => 'Perbaikan AC',
        'description' => 'AC ruang rapat mati',
        'status' => WorkOrderStatus::WaitingHod,
    ]);
}

function reviewUser(): User
{
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('work-order.review');

    return $user;
}

it('approves a work order for immediate execution', function () {
    $workOrder = createReviewTestWorkOrder();

    $response = $this
        ->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'execute_immediately',
            'hod_notes' => 'Kerusakan mengganggu operasional',
        ]);

    $response->assertRedirect();

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::HodApproved);
    expect($workOrder->hod_action)->toBe('execute_immediately');
    expect($workOrder->scheduled_date)->toBeNull();
    expect($workOrder->hod_notes)->toBe('Kerusakan mengganggu operasional');

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'reviewed',
        'from_status' => 'waiting_hod',
        'to_status' => 'hod_approved',
    ]);
});

it('schedules a work order with a date', function () {
    $workOrder = createReviewTestWorkOrder();

    $response = $this
        ->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'schedule',
            'scheduled_date' => today()->addDays(3)->toDateString(),
        ]);

    $response->assertRedirect();

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::Scheduled);
    expect($workOrder->hod_action)->toBe('schedule');
    expect($workOrder->scheduled_date?->toDateString())->toBe(today()->addDays(3)->toDateString());

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'reviewed',
        'to_status' => 'scheduled',
    ]);
});

it('rejects a schedule decision for urgent by accident', function () {
    $workOrder = createReviewTestWorkOrder(WorkOrderCategory::Accident);

    $response = $this
        ->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'schedule',
            'scheduled_date' => today()->addDays(3)->toDateString(),
        ]);

    $response->assertSessionHasErrors('hod_action');

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::WaitingHod);
});

it('requires a today-or-future date when scheduling', function () {
    $workOrder = createReviewTestWorkOrder();

    $this
        ->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'schedule',
        ])
        ->assertSessionHasErrors('scheduled_date');

    $this
        ->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'schedule',
            'scheduled_date' => today()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors('scheduled_date');
});

it('allows re-review until the work order is assigned', function () {
    $workOrder = createReviewTestWorkOrder();

    $this
        ->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'schedule',
            'scheduled_date' => today()->addDays(3)->toDateString(),
        ])
        ->assertRedirect();

    $this
        ->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'execute_immediately',
        ])
        ->assertRedirect();

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::HodApproved);
});

it('rejects a schedule date on an immediate-execution review', function () {
    // Fix 2026-10-24: mirror aturan store — tanggal pada eksekusi langsung
    // membuat WO HodApproved muncul di Timeline (whereNotNull scheduled_date).
    $workOrder = createReviewTestWorkOrder();

    $this
        ->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'execute_immediately',
            'scheduled_date' => today()->addDays(3)->toDateString(),
        ])
        ->assertSessionHasErrors('scheduled_date');
});

it('re-reviews back and forth without colliding on the unique planning row', function () {
    // Fix 2026-10-24: createForWorkOrder kini updateOrCreate — re-review
    // schedule → immediate → schedule tidak menabrak unique(work_order_id).
    $workOrder = createReviewTestWorkOrder();

    $this->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'schedule',
            'scheduled_date' => today()->addDays(3)->toDateString(),
        ])
        ->assertRedirect();

    $this->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'execute_immediately',
        ])
        ->assertRedirect();

    $this->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'schedule',
            'scheduled_date' => today()->addDays(5)->toDateString(),
        ])
        ->assertRedirect();

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::Scheduled)
        ->and($workOrder->scheduled_date?->toDateString())->toBe(today()->addDays(5)->toDateString())
        ->and(WorkPlanning::query()->where('work_order_id', $workOrder->getKey())->count())->toBe(1);
});

it('refuses to review a work order that already left the review stage', function () {
    $workOrder = createReviewTestWorkOrder();
    $workOrder->forceFill(['status' => WorkOrderStatus::Assigned])->save();

    $this
        ->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'execute_immediately',
        ])
        ->assertStatus(409);
});

it('forbids review without the work-order.review permission', function () {
    $workOrder = createReviewTestWorkOrder();

    $this
        ->actingAs(createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]))
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'execute_immediately',
        ])
        ->assertForbidden();

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::WaitingHod);
});

it('notifies the requester about the review decision', function () {
    Notification::fake();
    $workOrder = createReviewTestWorkOrder();

    $this
        ->actingAs(reviewUser())
        ->post("/hq/work-orders/{$workOrder->getKey()}/review", [
            'action' => 'schedule',
            'scheduled_date' => today()->addDays(3)->toDateString(),
        ]);

    Notification::assertSentTo(
        $workOrder->requester,
        WorkOrderReviewed::class,
    );
});
