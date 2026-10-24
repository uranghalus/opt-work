<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkPlanning;
use App\Notifications\WorkScheduleExtendDecided;
use App\Notifications\WorkScheduleExtendRequested;
use App\Services\WorkPlanningService;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * FR-2.4 + FR-2.5: Work Planning untuk WO Terjadwal — jadwal HANYA bisa
 * bergeser lewat Extend Work Schedule dengan approval DGM/GM (alur
 * terpisah dari extend WO telat FR-2.3).
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

/**
 * WO terjadwal (hasil review HOD 'schedule') + HOD + GM + DGM.
 *
 * @return array{0: WorkOrder, 1: User, 2: User, 3: User, 4: User}
 */
function makeScheduledWorkOrder(array $overrides = []): array
{
    $tenant = Tenant::where('code', 'hq')->first();
    $hod = createUser(['tenant_id' => $tenant->id]);
    $hod->givePermissionTo('work-planning.update');
    $department = Department::create([
        'kode_department' => 'DEP-WP-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Planning Ops',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => $tenant->id]);
    $generalManager = createUser(['tenant_id' => $tenant->id]);
    $generalManager->assignRole('general_manager');
    $generalManager->givePermissionTo('work-planning.update');
    $deputyManager = createUser(['tenant_id' => $tenant->id]);
    $deputyManager->assignRole('deputy_general_manager');
    $deputyManager->givePermissionTo('work-planning.update');

    $workOrder = WorkOrder::create(array_merge([
        'nomor_wo' => 'WO-WP-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Planning Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Scheduled,
        'hod_action' => 'schedule',
        'scheduled_date' => now()->addDays(7),
        'is_escalated' => false,
        'extend_count' => 0,
    ], $overrides));

    // Planning row yang biasanya lahir dari review 'schedule'.
    app(WorkPlanningService::class)->createForWorkOrder($workOrder);

    return [$workOrder, $hod, $generalManager, $deputyManager, $requester];
}

it('creates a planning row when the HOD schedules a WO at review', function () {
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $hod->givePermissionTo('work-order.review');
    $department = Department::create([
        'kode_department' => 'DEP-WP-R-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Review Dept',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-WPR-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Review Schedule WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::WaitingHod,
        'is_escalated' => false,
        'extend_count' => 0,
    ]);

    $this->actingAs($hod)->post(route('work-orders.review', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'action' => 'schedule',
        'scheduled_date' => now()->addDays(10)->toDateString(),
    ])->assertRedirect();

    $planning = WorkPlanning::query()->where('work_order_id', $workOrder->getKey())->sole();
    expect($planning->tgl_jadwal->toDateString())->toBe(now()->addDays(10)->toDateString())
        ->and($planning->status_jadwal)->toBe('planned')
        ->and($planning->extend_count)->toBe(0);
});

it('lets the HOD request a schedule extension that pends for DGM/GM', function () {
    Notification::fake();
    [$workOrder, $hod, $generalManager] = makeScheduledWorkOrder();

    $this->actingAs($hod)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $workOrder->planning->getKey(),
    ]), [
        'tgl_jadwal' => now()->addDays(12)->toDateString(),
        'extend_reason' => 'Material datang 5 hari terlambat.',
    ])->assertRedirect();

    $planning = $workOrder->planning()->sole();
    expect($planning->status_jadwal)->toBe('pending_extend_approval')
        ->and($planning->tgl_jadwal->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and($planning->proposed_tgl_jadwal->toDateString())->toBe(now()->addDays(12)->toDateString())
        ->and($planning->original_tgl_jadwal->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and($planning->extend_requested_by)->toBe($hod->getKey());

    Notification::assertSentTo($generalManager, WorkScheduleExtendRequested::class);

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'schedule_extend_requested',
    ]);
});

it('refuses a proposed date that is not after the current schedule', function () {
    [$workOrder, $hod] = makeScheduledWorkOrder();

    $this->actingAs($hod)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $workOrder->planning->getKey(),
    ]), [
        'tgl_jadwal' => now()->addDays(2)->toDateString(), // sebelum jadwal +7
        'extend_reason' => 'Mundur duluan.',
    ])->assertSessionHasErrors('tgl_jadwal');

    expect($workOrder->planning()->sole()->status_jadwal)->toBe('planned');
});

it('lets a general manager approve and moves the WO schedule', function () {
    Notification::fake();
    [$workOrder, $hod, $generalManager, , $requester] = makeScheduledWorkOrder();
    $planning = $workOrder->planning()->sole();
    $newDate = now()->addDays(12)->toDateString();

    $this->actingAs($hod)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [
        'tgl_jadwal' => $newDate,
        'extend_reason' => 'Material terlambat.',
    ])->assertRedirect();

    $this->actingAs($generalManager)->post(route('work-planning.approve', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [
        'notes' => 'Disetujui dengan catatan durasi.',
    ])->assertRedirect();

    $planning->refresh();
    $workOrder->refresh();

    expect($planning->status_jadwal)->toBe('rescheduled')
        ->and($planning->tgl_jadwal->toDateString())->toBe($newDate)
        ->and($planning->extend_count)->toBe(1)
        ->and($planning->extend_approved_by)->toBe($generalManager->getKey())
        ->and($planning->extend_approval_notes)->toBe('Disetujui dengan catatan durasi.')
        ->and($workOrder->scheduled_date->toDateString())->toBe($newDate);

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'schedule_extended',
    ]);

    Notification::assertSentTo($hod, WorkScheduleExtendDecided::class);
});

it('lets a deputy general manager approve too', function () {
    Notification::fake();
    [$workOrder, $hod, , $deputyManager] = makeScheduledWorkOrder();
    $planning = $workOrder->planning()->sole();

    $this->actingAs($hod)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [
        'tgl_jadwal' => now()->addDays(14)->toDateString(),
        'extend_reason' => 'Coordination dengan vendor.',
    ])->assertRedirect();

    $this->actingAs($deputyManager)->post(route('work-planning.approve', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [])->assertRedirect();

    expect($planning->refresh()->status_jadwal)->toBe('rescheduled')
        ->and($planning->extend_approved_by)->toBe($deputyManager->getKey());
});

it('refuses an HOD to approve a schedule extension', function () {
    [$workOrder, $hod] = makeScheduledWorkOrder();
    $planning = $workOrder->planning()->sole();

    $this->actingAs($hod)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [
        'tgl_jadwal' => now()->addDays(12)->toDateString(),
        'extend_reason' => 'Butuh lebih lama.',
    ])->assertRedirect();

    $this->actingAs($hod)->post(route('work-planning.approve', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [])->assertForbidden();

    expect($planning->refresh()->status_jadwal)->toBe('pending_extend_approval')
        ->and($workOrder->refresh()->scheduled_date->toDateString())->toBe(now()->addDays(7)->toDateString());
});

it('reverts the proposal and keeps the WO schedule when rejected', function () {
    Notification::fake();
    [$workOrder, $hod] = makeScheduledWorkOrder();
    $planning = $workOrder->planning()->sole();

    $this->actingAs($hod)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [
        'tgl_jadwal' => now()->addDays(20)->toDateString(),
        'extend_reason' => 'Terlalu longgar.',
    ])->assertRedirect();

    $generalManager = User::role('general_manager')->firstOrFail();
    $this->actingAs($generalManager)->post(route('work-planning.reject', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [
        'notes' => 'Jadwal sudah ketat, kerjakan sesuai rencana.',
    ])->assertRedirect();

    $planning->refresh();
    $workOrder->refresh();

    expect($planning->status_jadwal)->toBe('planned')
        ->and($planning->tgl_jadwal->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and($planning->proposed_tgl_jadwal)->toBeNull()
        ->and($planning->extend_approval_notes)->toBe('Jadwal sudah ketat, kerjakan sesuai rencana.')
        ->and($workOrder->scheduled_date->toDateString())->toBe(now()->addDays(7)->toDateString());

    Notification::assertSentTo($hod, WorkScheduleExtendDecided::class);

    $this->assertDatabaseHas('work_order_events', [
        'work_order_id' => $workOrder->getKey(),
        'action' => 'schedule_extend_rejected',
    ]);
});

it('blocks a second request while one is pending and outside scheduled WOs', function () {
    [$workOrder, $hod] = makeScheduledWorkOrder();
    $planning = $workOrder->planning()->sole();

    $payload = [
        'tgl_jadwal' => now()->addDays(12)->toDateString(),
        'extend_reason' => 'Pengajuan.',
    ];

    $this->actingAs($hod)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), $payload)->assertRedirect();

    $this->actingAs($hod)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), $payload)->assertConflict();

    // WO non-terjadwal: planning tidak ada → tidak bisa mengaju.
    [$assignedWo] = makeScheduledWorkOrder(['status' => WorkOrderStatus::Assigned]);
    $this->actingAs($hod)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $assignedWo->getKey(),
    ]))->assertNotFound();
});

it('refuses the requester to decide their own schedule extension', function () {
    [$workOrder, , $generalManager] = makeScheduledWorkOrder();
    $planning = $workOrder->planning()->sole();

    // GM sendiri yang mengaju.
    $this->actingAs($generalManager)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [
        'tgl_jadwal' => now()->addDays(12)->toDateString(),
        'extend_reason' => 'Diaju sendiri oleh GM.',
    ])->assertRedirect();

    // Lalu mencoba memutuskan pengajuannya sendiri.
    $this->actingAs($generalManager)->post(route('work-planning.approve', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [])->assertForbidden();

    expect($planning->refresh()->status_jadwal)->toBe('pending_extend_approval');
});

it('updates planning details without ever touching tgl_jadwal', function () {
    [$workOrder, $hod] = makeScheduledWorkOrder();
    $planning = $workOrder->planning()->sole();
    $scheduleBefore = $planning->tgl_jadwal->toDateString();

    $this->actingAs($hod)->patch(route('work-planning.update', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [
        'jam_mulai' => '08:00',
        'jam_selesai' => '12:00',
        'jenis_pekerjaan' => 'Perawatan preventif',
        'lama_pekerjaan_hari' => 2,
        'budget' => 1500000,
        'catatan' => 'Koordinasi dengan tim listrik.',
        'tgl_jadwal' => now()->addDays(1)->toDateString(), // harus diabaikan
    ])->assertRedirect();

    $planning->refresh();
    expect($planning->jam_mulai)->toBe('08:00')
        ->and($planning->jam_selesai)->toBe('12:00')
        ->and($planning->jenis_pekerjaan)->toBe('Perawatan preventif')
        ->and((int) $planning->lama_pekerjaan_hari)->toBe(2)
        ->and((float) $planning->budget)->toBe(1500000.0)
        ->and($planning->catatan)->toBe('Koordinasi dengan tim listrik.')
        ->and($planning->tgl_jadwal->toDateString())->toBe($scheduleBefore);
});

it('refuses the extend flow without the work-planning.update permission', function () {
    [$workOrder] = makeScheduledWorkOrder();
    $outsider = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $planning = $workOrder->planning()->sole();

    $this->actingAs($outsider)->post(route('work-planning.extend', [
        'tenant' => 'hq',
        'workPlanning' => $planning->getKey(),
    ]), [
        'tgl_jadwal' => now()->addDays(12)->toDateString(),
        'extend_reason' => 'Tanpa izin.',
    ])->assertForbidden();

    expect($planning->refresh()->status_jadwal)->toBe('planned');
});

/**
 * Regresi (2026-10-24): halaman detail WO terjadwal meledak 500
 * "Class App\Http\Controllers\WorkOrderStatus not found" karena import
 * enum hilang — hanya terpicu saat $planning !== null (short-circuit),
 * jadi lolos dari suite yang tidak me-render show() dengan planning.
 * Gejala user: simpan jadwal sukses → redirect ke detail → 500.
 */
it('renders the detail page of a scheduled WO with its planning and flags', function () {
    [$workOrder, $hod] = makeScheduledWorkOrder();

    $this->actingAs($hod)
        ->get(route('work-orders.show', [
            'tenant' => 'hq',
            'workOrder' => $workOrder->getKey(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/show')
            ->where('planning.status_jadwal', 'planned')
            ->where('planning.pending', false)
            ->where('can_update_planning', true)
            ->where('can_decide_schedule', false));
});
