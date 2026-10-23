<?php

use App\ExtendRequestStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExtendRequest;
use App\Models\Tenant;
use App\Models\TenantWaSession;
use App\Models\User;
use App\Models\WaMessage;
use App\Models\WorkOrder;
use App\Notifications\ExtendRequestApproved;
use App\Notifications\ExtendRequestRejected;
use App\Notifications\ExtendRequestSubmitted;
use App\Services\DeadlineCalculator;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * FR-2.3–2.5: pengajuan extend deadline WO telat — maks 3 hari/request,
 * maks 3 approved/WO, blokir pending duplikat, approval berjenjang
 * Team Leader (jika ada) → HOD.
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

function makeOverdueWorkOrder(array $overrides = []): WorkOrder
{
    $tenant = Tenant::where('code', 'hq')->first();
    $hod = createUser(['tenant_id' => $tenant->id]);
    $department = Department::create([
        'kode_department' => 'DEP-EXT-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Extend Ops',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => $tenant->id]);

    return WorkOrder::create(array_merge([
        'nomor_wo' => 'WO-EXT-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Extend Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Assigned,
        'assigned_at' => now()->subDays(8),
        'deadline_date' => now()->subDays(2),
        'is_escalated' => false,
        'extend_count' => 0,
    ], $overrides));
}

function makeDepartmentTeamLeader(Department $department): User
{
    $tenant = Tenant::where('code', 'hq')->first();
    $employee = Employee::factory()->create(['department_id' => $department->getKey()]);
    $teamLeader = createUser(['tenant_id' => $tenant->id, 'employee_id' => $employee->getKey()]);
    $teamLeader->assignRole('team_leader');

    return $teamLeader;
}

function extendRequester(): User
{
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('work-order.extend');

    return $user;
}

function extendApprover(): User
{
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('work-order.extend-approve');

    return $user;
}

function makePendingExtendRequest(
    WorkOrder $workOrder,
    string $status = 'pending_hod',
    int $days = 2,
    ?int $requestedBy = null,
): ExtendRequest {
    return ExtendRequest::create([
        'work_order_id' => $workOrder->getKey(),
        'requested_by_user_id' => $requestedBy ?? extendRequester()->getKey(),
        'days' => $days,
        'reason' => 'Spare part vendor terlambat.',
        'status' => $status,
    ]);
}

it('creates a pending_team_leader request when the department has a team leader', function () {
    Notification::fake();
    $workOrder = makeOverdueWorkOrder();
    $teamLeader = makeDepartmentTeamLeader($workOrder->targetDepartment);
    $actor = extendRequester();

    $response = $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 2,
        'reason' => 'Spare part datang terlambat dari vendor.',
    ]);

    $response->assertRedirect(route('work-orders.show', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]));

    $this->assertDatabaseHas('extend_requests', [
        'work_order_id' => $workOrder->getKey(),
        'requested_by_user_id' => $actor->getKey(),
        'days' => 2,
        'reason' => 'Spare part datang terlambat dari vendor.',
        'status' => 'pending_team_leader',
    ]);

    Notification::assertSentTo($teamLeader, ExtendRequestSubmitted::class);

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'user_id' => $actor->getKey(),
        'action' => 'extend_requested',
    ]);
});

it('lands directly at the HOD stage when the department has no team leader', function () {
    Notification::fake();
    $workOrder = makeOverdueWorkOrder();
    $actor = extendRequester();

    $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 3,
        'reason' => 'Pekerjaan terhenti karena cuaca.',
    ])->assertRedirect();

    $this->assertDatabaseHas('extend_requests', [
        'work_order_id' => $workOrder->getKey(),
        'status' => 'pending_hod',
    ]);

    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    Notification::assertSentTo($hod, ExtendRequestSubmitted::class);
});

it('rejects the request without the work-order.extend permission', function () {
    $actor = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $workOrder = makeOverdueWorkOrder();

    $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 2,
        'reason' => 'Tidak berizin.',
    ])->assertForbidden();

    $this->assertDatabaseCount('extend_requests', 0);
});

it('blocks a second request while one is still pending', function () {
    $workOrder = makeOverdueWorkOrder();
    $actor = extendRequester();

    $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 1,
        'reason' => 'Pengajuan pertama.',
    ])->assertRedirect();

    $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 2,
        'reason' => 'Pengajuan duplikat.',
    ])->assertConflict();

    $this->assertDatabaseCount('extend_requests', 1);
});

it('advances a team-leader-stage request to the HOD stage when the team leader approves', function () {
    Notification::fake();
    $workOrder = makeOverdueWorkOrder();
    $teamLeader = makeDepartmentTeamLeader($workOrder->targetDepartment);
    $teamLeader->givePermissionTo('work-order.extend-approve');
    $extendRequest = makePendingExtendRequest($workOrder, status: 'pending_team_leader', days: 2);

    $this->actingAs($teamLeader)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $extendRequest->getKey(),
    ]))->assertRedirect();

    $extendRequest->refresh();
    expect($extendRequest->status)->toBe(ExtendRequestStatus::PendingHod)
        ->and($extendRequest->team_leader_user_id)->toBe($teamLeader->getKey())
        ->and($extendRequest->team_leader_approved_at)->not->toBeNull()
        ->and($extendRequest->approved_at)->toBeNull();

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'extend_tl_approved',
    ]);

    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    Notification::assertSentTo($hod, ExtendRequestSubmitted::class);
});

it('finalizes approval at the HOD stage and moves the deadline by the requested business days', function () {
    Notification::fake();
    $workOrder = makeOverdueWorkOrder([
        'deadline_date' => '2026-10-20', // Selasa
    ]);
    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend-approve');

    $extendRequest = makePendingExtendRequest($workOrder, status: 'pending_hod', days: 2);

    $this->actingAs($hod)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $extendRequest->getKey(),
    ]))->assertRedirect();

    $extendRequest->refresh();
    $workOrder->refresh();

    expect($extendRequest->status)->toBe(ExtendRequestStatus::Approved)
        ->and($extendRequest->approved_by_user_id)->toBe($hod->getKey())
        ->and($extendRequest->approved_at)->not->toBeNull()
        // Dari Selasa 20: +1 = Rabu 21, +2 = Kamis 22.
        ->and($workOrder->deadline_date->toDateString())->toBe('2026-10-22')
        ->and($workOrder->extend_count)->toBe(1)
        ->and($workOrder->extend_reason)->toBe('Spare part vendor terlambat.')
        ->and($workOrder->extended_at)->not->toBeNull();

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'extend_approved',
    ]);

    Notification::assertSentTo($extendRequest->requester, ExtendRequestApproved::class);
});

it('blocks the requester from approving their own request', function () {
    $workOrder = makeOverdueWorkOrder();
    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend-approve');

    $extendRequest = makePendingExtendRequest($workOrder, status: 'pending_hod', requestedBy: $hod->getKey());

    $this->actingAs($hod)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $extendRequest->getKey(),
    ]))->assertForbidden();

    expect($extendRequest->refresh()->status)->toBe(ExtendRequestStatus::PendingHod)
        ->and($workOrder->refresh()->extend_count)->toBe(0);
});

it('blocks approvers outside the current chain stage', function () {
    $workOrder = makeOverdueWorkOrder();
    $teamLeader = makeDepartmentTeamLeader($workOrder->targetDepartment);
    $teamLeader->givePermissionTo('work-order.extend-approve');
    $outsider = extendApprover();

    $tlStage = makePendingExtendRequest($workOrder, status: 'pending_team_leader');

    // Bukan TL — permission saja tidak cukup di tahap TL.
    $this->actingAs($outsider)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $tlStage->getKey(),
    ]))->assertForbidden();

    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend-approve');

    $hodStage = makePendingExtendRequest($workOrder, status: 'pending_hod');

    // TL bukan rantai HOD — tidak bisa memutuskan tahap HOD.
    $this->actingAs($teamLeader)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $hodStage->getKey(),
    ]))->assertForbidden();

    expect($tlStage->refresh()->status)->toBe(ExtendRequestStatus::PendingTeamLeader)
        ->and($hodStage->refresh()->status)->toBe(ExtendRequestStatus::PendingHod);
});

it('records a rejection with the reason and leaves the WO untouched', function () {
    Notification::fake();
    $workOrder = makeOverdueWorkOrder();
    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend-approve');
    $deadlineBefore = $workOrder->deadline_date?->toDateString();

    $extendRequest = makePendingExtendRequest($workOrder, status: 'pending_hod', days: 3);

    $this->actingAs($hod)->post(route('work-orders.extend-requests.reject', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $extendRequest->getKey(),
    ]), [
        'reason' => 'Pekerjaan harus tetap berjalan.',
    ])->assertRedirect();

    $extendRequest->refresh();
    $workOrder->refresh();

    expect($extendRequest->status)->toBe(ExtendRequestStatus::Rejected)
        ->and($extendRequest->rejected_by_user_id)->toBe($hod->getKey())
        ->and($extendRequest->rejected_reason)->toBe('Pekerjaan harus tetap berjalan.')
        ->and($workOrder->deadline_date->toDateString())->toBe($deadlineBefore)
        ->and($workOrder->extend_count)->toBe(0);

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'extend_rejected',
    ]);

    Notification::assertSentTo($extendRequest->requester, ExtendRequestRejected::class);
});

it('refuses to decide an already-decided request', function () {
    $workOrder = makeOverdueWorkOrder();
    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend-approve');

    $extendRequest = makePendingExtendRequest($workOrder, status: 'approved');

    $this->actingAs($hod)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $extendRequest->getKey(),
    ]))->assertConflict();
});

it('skips weekends and holidays when extending the deadline', function () {
    config(['holidays' => ['2026-10-26']]);
    $workOrder = makeOverdueWorkOrder([
        'deadline_date' => '2026-10-23',
    ]);
    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend-approve');

    $extendRequest = makePendingExtendRequest($workOrder, status: 'pending_hod', days: 2);

    $this->actingAs($hod)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $extendRequest->getKey(),
    ]))->assertRedirect();

    $workOrder->refresh();
    // Dari Jumat 23: Sabtu+Minggu dilompati, Senin 26 libur → +1 = Selasa 27, +2 = Rabu 28.
    expect($workOrder->deadline_date->toDateString())->toBe('2026-10-28');
});

it('refuses a request when the 3-approved ceiling is reached', function () {
    $workOrder = makeOverdueWorkOrder(['extend_count' => 3]);
    $actor = extendRequester();

    $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 1,
        'reason' => 'Batas tercapai.',
    ])->assertConflict();

    $this->assertDatabaseCount('extend_requests', 0);
});

it('refuses a request while the deadline is still far', function () {
    $workOrder = makeOverdueWorkOrder([
        // 4 hari kerja di depan — di luar jendela mendekati telat (≤2).
        'deadline_date' => app(DeadlineCalculator::class)->addBusinessDays(now(), 4),
    ]);
    $actor = extendRequester();

    $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 1,
        'reason' => 'Belum telat.',
    ])->assertConflict();

    $this->assertDatabaseCount('extend_requests', 0);
});

it('allows a request while the WO is near its deadline', function () {
    Notification::fake();
    $workOrder = makeOverdueWorkOrder([
        // 1 hari kerja di depan → masuk jendela mendekati telat (keputusan
        // user 2026-10-22 di branch feature/extend-deadline).
        'deadline_date' => app(DeadlineCalculator::class)->addBusinessDays(now(), 1),
    ]);
    $actor = extendRequester();

    $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 2,
        'reason' => 'Estimasi mepet, antisipasi keterlambatan.',
    ])->assertRedirect();

    $this->assertDatabaseHas('extend_requests', [
        'work_order_id' => $workOrder->getKey(),
        'status' => 'pending_hod',
    ]);
});

it('queues WhatsApp outbox rows for extend events when the gateway is active', function () {
    config([
        'waha.url' => 'http://waha.test',
        'waha.api_key' => 'test-key',
        'waha.rate_per_minute' => 1000,
        'waha.min_interval_seconds' => 0,
    ]);
    Http::fake(['http://waha.test/api/sendText' => fn () => ['status' => 'sent']]);

    $tenant = Tenant::where('code', 'hq')->first();
    TenantWaSession::withoutGlobalScope('tenant')->updateOrCreate(
        ['tenant_id' => $tenant->getKey()],
        [
            'session_name' => 'tw-hq',
            'phone_number' => '6281234567890',
            'status' => 'working',
        ],
    );

    $workOrder = makeOverdueWorkOrder();
    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->forceFill(['phone' => '628111111111'])->save();
    $hod->givePermissionTo('work-order.extend-approve');
    $actor = extendRequester();
    $actor->forceFill(['phone' => '628222222222'])->save();

    $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 2,
        'reason' => 'Spare part terlambat.',
    ])->assertRedirect();

    $this->actingAs($hod)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => ExtendRequest::query()
            ->where('work_order_id', $workOrder->getKey())
            ->sole()
            ->getKey(),
    ]))->assertRedirect();

    expect(
        WaMessage::query()
            ->where('event', 'work_order.extend_requested')
            ->where('status', 'sent')
            ->count()
    )->toBe(1)
        ->and(
            WaMessage::query()
                ->where('event', 'work_order.extend_approved')
                ->where('status', 'sent')
                ->count()
        )->toBe(1)
        ->and(
            WaMessage::query()
                ->where('event', 'work_order.extend_approved')
                ->where('status', 'skipped')
                ->count()
        )->toBe(1);
});

it('exposes extend visibility flags on the show payload', function () {
    $workOrder = makeOverdueWorkOrder();
    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend-approve');
    $actor = extendRequester();
    $showRoute = ['tenant' => 'hq', 'workOrder' => $workOrder->getKey()];

    // Sebelum ada pengajuan: pemegang izin extend boleh mengaju (WO telat),
    // approver tidak berada pada tahap mana pun.
    $this->actingAs($actor)->get(route('work-orders.show', $showRoute))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/show')
            ->where('can_request_extend', true)
            ->where('can_decide_extend', false));

    $extendRequest = makePendingExtendRequest($workOrder, status: 'pending_hod', requestedBy: $actor->getKey());

    // Saat pending: form mengaju hilang, HOD di tahap HOD bisa memutuskan.
    $this->actingAs($hod)->get(route('work-orders.show', $showRoute))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/show')
            ->where('can_request_extend', false)
            ->where('can_decide_extend', true));

    // Pengaju tidak bisa memutuskan pengajuannya sendiri.
    $this->actingAs($actor)->get(route('work-orders.show', $showRoute))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_decide_extend', false));
});

it('refuses a request outside the execution stage', function () {
    $workOrder = makeOverdueWorkOrder(['status' => WorkOrderStatus::WaitingHod]);
    $actor = extendRequester();

    $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 1,
        'reason' => 'Status salah.',
    ])->assertConflict();
});

it('hides the extend routes from another cabang', function () {
    $workOrder = makeOverdueWorkOrder();
    Tenant::forgetCurrent();
    initTenant('plant-1');
    $foreign = makeOverdueWorkOrder();
    Tenant::forgetCurrent();
    initTenant('hq');
    $actor = extendRequester();

    $this->actingAs($actor)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $foreign->getKey(),
    ]), ['days' => 1, 'reason' => 'Lintas cabang.'])->assertNotFound();

    // Approver berizin lintas cabang tetap 404 — scope tenant mengunci WO.
    $approver = extendApprover();
    $this->actingAs($approver)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $foreign->getKey(),
    ]))->assertNotFound();

    $this->assertDatabaseCount('extend_requests', 0);
});

it('lets the HOD chain decide when the team leader left after filing', function () {
    Notification::fake();
    $workOrder = makeOverdueWorkOrder();
    $teamLeader = makeDepartmentTeamLeader($workOrder->targetDepartment);
    $extendRequest = makePendingExtendRequest($workOrder, status: 'pending_team_leader', days: 2);

    // TL keluar dari department setelah pengajuan dibuat.
    $teamLeader->removeRole('team_leader');

    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend-approve');

    $this->actingAs($hod)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $extendRequest->getKey(),
    ]))->assertRedirect();

    expect($extendRequest->refresh()->status)->toBe(ExtendRequestStatus::Approved)
        ->and($workOrder->refresh()->extend_count)->toBe(1);
});

it('refuses a request when no eligible approver remains', function () {
    // Pengaju = satu-satunya user di rantai HOD (hod_user_id department),
    // tanpa TL — tidak ada yang bisa memutuskan.
    $workOrder = makeOverdueWorkOrder();
    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend');

    $this->actingAs($hod)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 2,
        'reason' => 'Tidak ada approver lain.',
    ])->assertConflict();

    $this->assertDatabaseCount('extend_requests', 0);
});

it('refuses to approve after the WO left the execution stage', function () {
    $workOrder = makeOverdueWorkOrder();
    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend-approve');
    $extendRequest = makePendingExtendRequest($workOrder, status: 'pending_hod', days: 2);

    $workOrder->update(['status' => WorkOrderStatus::Closed]);

    $this->actingAs($hod)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $extendRequest->getKey(),
    ]))->assertConflict();

    expect($workOrder->refresh()->extend_count)->toBe(0)
        ->and($extendRequest->refresh()->status)->toBe(ExtendRequestStatus::PendingHod);
});

it('resets the escalation markers when the extend is approved', function () {
    $workOrder = makeOverdueWorkOrder([
        'escalation_h3_sent_at' => now()->subDays(4),
        'escalation_h5_sent_at' => now()->subDays(2),
        'is_escalated' => true,
    ]);
    $hod = User::find($workOrder->targetDepartment->hod_user_id);
    $hod->givePermissionTo('work-order.extend-approve');
    $extendRequest = makePendingExtendRequest($workOrder, status: 'pending_hod', days: 2);

    $this->actingAs($hod)->post(route('work-orders.extend-requests.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
        'extendRequest' => $extendRequest->getKey(),
    ]))->assertRedirect();

    $workOrder->refresh();
    // Deadline baru = siklus eskalasi baru (ruling: siklus dihitung dari jadwal baru).
    expect($workOrder->escalation_h3_sent_at)->toBeNull()
        ->and($workOrder->escalation_h5_sent_at)->toBeNull()
        ->and($workOrder->is_escalated)->toBeFalse();
});
