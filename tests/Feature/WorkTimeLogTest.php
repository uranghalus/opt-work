<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\ExtendRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkTimeLog;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Testing\TestResponse;

/**
 * R15–R18: timer durasi kerja pelaksana — accept → start → pause/resume →
 * finish (otomatis via submit). Semua timestamp disimpan server; elapsed
 * dihitung dari data tersimpan (R17). Hanya pelaksana tercantum yang boleh
 * beraksi (R15, server-side).
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
 * WO Assigned dengan dua pelaksana ber-akun di department tujuan.
 *
 * @return array{0: WorkOrder, 1: User, 2: User, 3: User}
 */
function makeAssignedWithTwoAssignees(): array
{
    $tenant = Tenant::where('code', 'hq')->first();

    $requester = createUser(['tenant_id' => $tenant->id]);

    $assigneeA = createUser(['tenant_id' => $tenant->id]);
    $assigneeB = createUser(['tenant_id' => $tenant->id]);
    $employeeA = Employee::factory()->create();
    $employeeB = Employee::factory()->create();
    $assigneeA->forceFill(['employee_id' => $employeeA->getKey()])->save();
    $assigneeB->forceFill(['employee_id' => $employeeB->getKey()])->save();

    $assigneeA->givePermissionTo(['work-order.read', 'work-order.submit']);
    $assigneeB->givePermissionTo(['work-order.read', 'work-order.submit']);

    $department = Department::create([
        'kode_department' => 'DEP-WT-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Teknik Timer',
        'hod_user_id' => $requester->getKey(),
    ]);
    $employeeA->update(['department_id' => $department->getKey()]);
    $employeeB->update(['department_id' => $department->getKey()]);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-WT-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Perbaikan AC lantai 2',
        'description' => 'Test timer.',
        'status' => WorkOrderStatus::Assigned,
        'assigned_employees' => [$employeeA->getKey(), $employeeB->getKey()],
        'assigned_at' => now()->subDay(),
        'deadline_date' => now()->addDays(5),
    ]);

    return [$workOrder, $assigneeA, $assigneeB, $requester];
}

function actOnWorkTime(User $actor, WorkOrder $workOrder, string $action): TestResponse
{
    return test()->actingAs($actor)->post(route('work-orders.work-time.'.$action, [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]));
}

it('lets an assignee accept and start, moving the WO to in_progress', function () {
    [$workOrder, $assigneeA] = makeAssignedWithTwoAssignees();

    actOnWorkTime($assigneeA, $workOrder, 'accept')->assertRedirect();

    $log = WorkTimeLog::query()->where('work_order_id', $workOrder->getKey())->sole();

    expect($log->accepted_at)->not->toBeNull()
        ->and($log->started_at)->toBeNull()
        ->and($log->timerState())->toBe('accepted')
        ->and($log->elapsedSeconds())->toBe(0);

    actOnWorkTime($assigneeA, $workOrder, 'start')->assertRedirect();

    $log->refresh();
    $workOrder->refresh();

    expect($log->started_at)->not->toBeNull()
        ->and($log->timerState())->toBe('running')
        ->and($workOrder->status)->toBe(WorkOrderStatus::InProgress);
});

it('refuses timer actions for users outside the assignee list', function () {
    [$workOrder, , , $requester] = makeAssignedWithTwoAssignees();

    // Pemohon (bukan pelaksana) maupun akun tanpa karyawan ditolak (R15).
    actOnWorkTime($requester, $workOrder, 'accept')->assertForbidden();

    $outsider = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    actOnWorkTime($outsider, $workOrder, 'start')->assertForbidden();

    expect(WorkTimeLog::query()->where('work_order_id', $workOrder->getKey())->exists())->toBeFalse();
});

it('enforces the timer sequence guards', function () {
    [$workOrder, $assigneeA] = makeAssignedWithTwoAssignees();

    // Start sebelum terima → 409.
    actOnWorkTime($assigneeA, $workOrder, 'start')->assertStatus(409);

    actOnWorkTime($assigneeA, $workOrder, 'accept')->assertRedirect();

    // Re-accept → 409.
    actOnWorkTime($assigneeA, $workOrder, 'accept')->assertStatus(409);

    actOnWorkTime($assigneeA, $workOrder, 'start')->assertRedirect();

    $log = WorkTimeLog::query()->where('work_order_id', $workOrder->getKey())->sole();
    expect($log->accepted_at)->not->toBeNull()
        ->and($log->started_at)->not->toBeNull();

    // Start ulang → 409.
    actOnWorkTime($assigneeA, $workOrder, 'start')->assertStatus(409);

    // Resume tanpa pause → 409.
    actOnWorkTime($assigneeA, $workOrder, 'resume')->assertStatus(409);

    // Pause lalu resume berjalan.
    actOnWorkTime($assigneeA, $workOrder, 'pause')->assertRedirect();
    $log->refresh();
    expect($log->paused_at)->not->toBeNull()
        ->and($log->timerState())->toBe('paused');

    // Pause dua kali → 409.
    actOnWorkTime($assigneeA, $workOrder, 'pause')->assertStatus(409);

    actOnWorkTime($assigneeA, $workOrder, 'resume')->assertRedirect();
    $log->refresh();
    expect($log->paused_at)->toBeNull();
});

it('computes elapsed time from stored timestamps with pause excluded', function () {
    [$workOrder, $assigneeA] = makeAssignedWithTwoAssignees();

    actOnWorkTime($assigneeA, $workOrder, 'accept')->assertRedirect();
    actOnWorkTime($assigneeA, $workOrder, 'start')->assertRedirect();

    // Backdate server-side (simulasi sudah bekerja 10 menit).
    $log = WorkTimeLog::query()->where('work_order_id', $workOrder->getKey())->sole();
    $log->update(['started_at' => now()->subSeconds(600)]);

    // Jeda terbuka TIDAK dihitung: elapsed tetap ≈ 600 detik.
    actOnWorkTime($assigneeA, $workOrder, 'pause')->assertRedirect();
    $log->refresh();

    expect($log->timerState())->toBe('paused')
        ->and($log->elapsedSeconds())->toBeBetween(590, 610);

    // Resume: segmen jeda tertutup masuk paused_seconds…
    actOnWorkTime($assigneeA, $workOrder, 'resume')->assertRedirect();
    $log->refresh();

    expect($log->paused_seconds)->toBeLessThan(30)
        ->and($log->pause_log)->toHaveCount(1)
        ->and($log->pause_log[0])->toHaveKeys(['paused_at', 'resumed_at'])
        // …dan elapsed kembali mengalir (masih ≈ 600-an detik).
        ->and($log->elapsedSeconds())->toBeGreaterThanOrEqual(590);
});

it('finishes the timer automatically when the assignee submits the result', function () {
    [$workOrder, $assigneeA, $assigneeB] = makeAssignedWithTwoAssignees();

    actOnWorkTime($assigneeA, $workOrder, 'accept')->assertRedirect();
    actOnWorkTime($assigneeA, $workOrder, 'start')->assertRedirect();

    $logA = WorkTimeLog::query()->where('work_order_id', $workOrder->getKey())->sole();
    $logA->update(['started_at' => now()->subSeconds(600)]);

    $this->actingAs($assigneeA)->post(route('work-orders.submit', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), ['result_notes' => 'Pekerjaan selesai.'])->assertRedirect();

    $logA->refresh();
    $workOrder->refresh();

    expect($workOrder->status)->toBe(WorkOrderStatus::PendingVerify)
        ->and($logA->finished_at)->not->toBeNull()
        ->and($logA->timerState())->toBe('finished')
        ->and($logA->elapsedSeconds())->toBeBetween(590, 615);

    // Pelaksana kedua: accept pada tahap non-eksekusi ditolak (409) —
    // timer miliknya tidak pernah terbentuk.
    actOnWorkTime($assigneeB, $workOrder, 'accept')->assertStatus(409);

    expect(WorkTimeLog::query()->where('work_order_id', $workOrder->getKey())->count())->toBe(1);
});

it('refuses timer actions outside the execution stage', function () {
    [$workOrder, $assigneeA] = makeAssignedWithTwoAssignees();

    $workOrder->update(['status' => WorkOrderStatus::PendingVerify->value]);

    actOnWorkTime($assigneeA, $workOrder, 'accept')->assertStatus(409);

    // Buat baris log langsung lalu coba pause pada tahap non-eksekusi.
    WorkTimeLog::create([
        'work_order_id' => $workOrder->getKey(),
        'employee_id' => $assigneeA->employee_id,
        'accepted_at' => now()->subHour(),
        'started_at' => now()->subMinutes(30),
    ]);

    actOnWorkTime($assigneeA, $workOrder, 'pause')->assertStatus(409);
});

it('lists active assignments with the server-computed timer for the assignee', function () {
    [$workOrder, $assigneeA] = makeAssignedWithTwoAssignees();

    // Penugasi direkam sebagai event `assigned` (R15: tampilkan penugasi).
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    WorkOrderEvent::record($workOrder, $hod, 'assigned', 'hod_approved', 'assigned');

    actOnWorkTime($assigneeA, $workOrder, 'accept')->assertRedirect();
    actOnWorkTime($assigneeA, $workOrder, 'start')->assertRedirect();

    $this->actingAs($assigneeA)
        ->get(route('work-orders.my', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('assignments.data.0.id', $workOrder->getKey())
            ->where('assignments.data.0.assigner_name', $hod->name)
            ->where('assignments.data.0.time_log.state', 'running')
            ->has('assignments.data.0.time_log.elapsed_seconds')
            ->where('assignments.data.0.deadline_state', 'on_track'));
});

it('keeps assignments invisible to users who are not assignees', function () {
    [$workOrder, , , $requester] = makeAssignedWithTwoAssignees();

    $this->actingAs($requester)
        ->get(route('work-orders.my', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('assignments.data', 0));

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Assigned);
});

it('surfaces pending extend requests only to the deciding approver (R21)', function () {
    Notification::fake();
    $tenant = Tenant::where('code', 'hq')->first();

    $hod = createUser(['tenant_id' => $tenant->id]);
    $department = Department::create([
        'kode_department' => 'DEP-EXA-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Extend Ops',
        'hod_user_id' => $hod->getKey(),
    ]);

    // TL role di department itu — tahap pertama rantai extend.
    $tlEmployee = Employee::factory()->create(['department_id' => $department->getKey()]);
    $teamLeader = createUser(['tenant_id' => $tenant->id, 'employee_id' => $tlEmployee->getKey()]);
    $teamLeader->assignRole('team_leader');
    $teamLeader->givePermissionTo('work-order.extend-approve');

    $requester = createUser([
        'tenant_id' => $tenant->id,
        'employee_id' => Employee::factory()->create(['department_id' => $department->getKey()])->getKey(),
    ]);
    $requester->givePermissionTo(['work-order.extend', 'work-order.read']);

    // WO telat (deadline 2 hari lalu) — dalam jendela extend.
    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-EXA-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Extend inbox WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Assigned,
        'assigned_at' => now()->subDays(8),
        'deadline_date' => now()->subDays(2),
    ]);

    $this->actingAs($requester)->post(route('work-orders.extend-requests.store', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'days' => 2,
        'reason' => 'Menunggu part vendor.',
    ])->assertRedirect();

    $extendRequest = ExtendRequest::query()
        ->where('work_order_id', $workOrder->getKey())
        ->sole();

    // TL (tahap berjalan) melihat pengajuan di inbox extend…
    $this->actingAs($teamLeader)
        ->get(route('work-orders.extend-approvals', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('deadline_extends.0.id', $extendRequest->getKey())
            ->where('deadline_extends.0.stage', 'pending_team_leader'));

    // …pengaju TIDAK (larangan memutuskan pengajuan sendiri)…
    $this->actingAs($requester)
        ->get(route('work-orders.extend-approvals', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('deadline_extends', 0));

    // …dan HOD (tahap belum tiba) juga belum melihatnya.
    $this->actingAs($hod)
        ->get(route('work-orders.extend-approvals', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('deadline_extends', 0));
});
