<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderSubmitted;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * FR-1.7: karyawan pelaksana menyerahkan hasil WO —
 * assigned/revision → pending_verify.
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
    initTenant('hq');
});

afterEach(function () {
    Tenant::forgetCurrent();
});

function makeSubmittableWorkOrder(array $overrides = []): WorkOrder
{
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $department = Department::create([
        'kode_department' => 'DEP-OPS',
        'nama_department' => 'Operations',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    return WorkOrder::create(array_merge([
        'nomor_wo' => 'WO-SUB-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Submit Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Assigned,
        'assigned_at' => now(),
        'deadline_date' => now()->addDays(6),
        'is_escalated' => false,
        'extend_count' => 0,
    ], $overrides));
}

function submitActor(): User
{
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('work-order.submit');

    return $user;
}

it('submits execution results and moves the WO to pending_verify', function () {
    Storage::fake('public');
    Notification::fake();
    $actor = submitActor();
    $workOrder = makeSubmittableWorkOrder();

    $response = $this->actingAs($actor)->post(route('work-orders.submit', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'result_notes' => 'Perbaikan selesai, AC kembali dingin.',
        'result_attachments' => [UploadedFile::fake()->image('hasil.jpg')],
    ]);

    $response->assertRedirect(route('work-orders.show', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]));

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::PendingVerify)
        ->and($workOrder->result_notes)->toBe('Perbaikan selesai, AC kembali dingin.')
        ->and($workOrder->result_attachments)->toHaveCount(1);

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'user_id' => $actor->getKey(),
        'action' => 'submitted',
        'from_status' => WorkOrderStatus::Assigned->value,
        'to_status' => WorkOrderStatus::PendingVerify->value,
    ]);

    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    Notification::assertSentTo($hod, WorkOrderSubmitted::class);
});

it('allows resubmission from revision and replaces the previous result', function () {
    Notification::fake();
    $actor = submitActor();
    $workOrder = makeSubmittableWorkOrder([
        'status' => WorkOrderStatus::Revision,
        'result_notes' => 'Hasil lama yang dikembalikan HOD.',
    ]);

    $this->actingAs($actor)->post(route('work-orders.submit', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'result_notes' => 'Hasil revisi dikerjakan ulang.',
    ])->assertRedirect();

    $workOrder->refresh();
    expect($workOrder->status)->toBe(WorkOrderStatus::PendingVerify)
        ->and($workOrder->result_notes)->toBe('Hasil revisi dikerjakan ulang.')
        ->and($workOrder->result_attachments)->toBeNull();

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'submitted',
        'from_status' => WorkOrderStatus::Revision->value,
        'to_status' => WorkOrderStatus::PendingVerify->value,
    ]);
});

it('rejects submit without the work-order.submit permission', function () {
    $actor = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $workOrder = makeSubmittableWorkOrder();

    $this->actingAs($actor)->post(route('work-orders.submit', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'result_notes' => 'Tidak berizin.',
    ])->assertForbidden();

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Assigned);
});

it('refuses submit before the WO reaches the execution stage', function () {
    $actor = submitActor();
    $workOrder = makeSubmittableWorkOrder(['status' => WorkOrderStatus::WaitingHod]);

    $this->actingAs($actor)->post(route('work-orders.submit', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'result_notes' => 'Terlalu dini.',
    ])->assertStatus(409);

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::WaitingHod);
});

it('refuses submit on a closed work order', function () {
    $actor = submitActor();
    $workOrder = makeSubmittableWorkOrder(['status' => WorkOrderStatus::Closed]);

    $this->actingAs($actor)->post(route('work-orders.submit', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'result_notes' => 'WO sudah closed.',
    ])->assertStatus(409);

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Closed);
});

it('validates the result notes and attachments', function () {
    $actor = submitActor();
    $workOrder = makeSubmittableWorkOrder();

    $this->actingAs($actor)->post(route('work-orders.submit', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'result_notes' => '',
        'result_attachments' => [
            UploadedFile::fake()->create('bukan-foto.txt', 10, 'text/plain'),
        ],
    ])->assertSessionHasErrors(['result_notes', 'result_attachments.0']);

    $this->actingAs($actor)->post(route('work-orders.submit', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'result_notes' => 'Catatan valid.',
        'result_attachments' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
            UploadedFile::fake()->image('d.jpg'),
        ],
    ])->assertSessionHasErrors('result_attachments');
});

it('cannot submit a work order from another tenant', function () {
    $actor = submitActor();
    $workOrder = makeSubmittableWorkOrder();

    $this->actingAs($actor)->post(route('work-orders.submit', [
        'tenant' => 'plant-1',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'result_notes' => 'Lintas cabang.',
    ])->assertForbidden(); // tenant.access blocks the cross-branch URL before the WO lookup.

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Assigned);
});
