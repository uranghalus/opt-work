<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderDeadlineEscalated;
use App\Services\DeadlineCalculator;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Multitenancy\Exceptions\CurrentTenantCouldNotBeDeterminedInTenantAwareJob;

/**
 * FR-2.x: Staged deadline escalation (H+3/5/6)
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    // Create hq tenant for testing
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/**
 * Create an overdue Work Order for escalation testing.
 * Normal category assigned Oct 1 = deadline Oct 8 (Thu)
 * Urgent category assigned Oct 1 = deadline Oct 6 (Tue)
 */
function createOverdueWorkOrder(
    WorkOrderCategory $category = WorkOrderCategory::Normal,
    array $escalationMarkers = [],
    ?int $hodUserId = null,
    ?int $managerUserId = null,
    string $status = WorkOrderStatus::Assigned->value,
): WorkOrder {
    initTenant('hq');
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
        'hod_user_id' => $hodUserId,
        'manager_user_id' => $managerUserId,
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $assignedAt = Carbon::parse('2026-10-01 09:00:00');
    $deadline = app(DeadlineCalculator::class)
        ->calculate($category, $assignedAt);

    return WorkOrder::create([
        'nomor_wo' => 'WO-ESC-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => $category,
        'title' => 'Escalation Test WO',
        'description' => 'Test',
        'status' => $status,
        'assigned_at' => $assignedAt,
        'deadline_date' => $deadline,
        'escalation_h3_sent_at' => $escalationMarkers['h3'] ?? null,
        'escalation_h5_sent_at' => $escalationMarkers['h5'] ?? null,
        'escalation_h6_sent_at' => $escalationMarkers['h6'] ?? null,
        'is_escalated' => false,
        'extend_count' => 0,
    ]);
}

it('escalates normal WO at H+3 to HOD (no TL)', function () {
    Notification::fake();
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    makeHod($hod);

    // Normal WO assigned Oct 1 (Thu), deadline Oct 8 (Thu)
    // H+3 business days from Oct 1 = Oct 6 (Tue) — already past
    $workOrder = createOverdueWorkOrder(
        WorkOrderCategory::Normal,
        hodUserId: $hod->getKey(),
    );

    Artisan::call('app:check-deadline-escalation');

    expect($workOrder->refresh()->escalation_h3_sent_at)->not->toBeNull();
    Notification::assertSentTo($hod, WorkOrderDeadlineEscalated::class);
});

it('escalates urgent WO at H+3 to HOD', function () {
    Notification::fake();
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    makeHod($hod);

    // Urgent WO assigned Oct 1 (Thu), deadline Oct 6 (Tue)
    // H+3 business days from Oct 1 = Oct 6 (Tue) — already past
    $workOrder = createOverdueWorkOrder(
        WorkOrderCategory::Accident,
        hodUserId: $hod->getKey(),
    );

    Artisan::call('app:check-deadline-escalation');

    expect($workOrder->refresh()->escalation_h3_sent_at)->not->toBeNull();
    Notification::assertSentTo($hod, WorkOrderDeadlineEscalated::class, function ($notification) use ($workOrder) {
        return $notification->workOrder->id === $workOrder->id
            && $notification->level === 'h3';
    });
});

it('does not resend escalation if already sent', function () {
    Notification::fake();
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    makeHod($hod);

    // Create WO with H+3 already sent
    $workOrder = createOverdueWorkOrder(
        WorkOrderCategory::Normal,
        escalationMarkers: ['h3' => Carbon::parse('2026-10-06 10:00:00')],
        hodUserId: $hod->getKey(),
    );

    Artisan::call('app:check-deadline-escalation');

    $workOrder->refresh();
    // H+3 should not be resent
    Notification::assertNotSentTo($hod, WorkOrderDeadlineEscalated::class, function ($notification) use ($workOrder) {
        return $notification->workOrder->id === $workOrder->id
            && $notification->level === 'h3';
    });
    // But H+5 should fire
    expect($workOrder->escalation_h5_sent_at)->not->toBeNull();
});

it('sends H+3 to Team Leader if department has one', function () {
    Notification::fake();
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    makeHod($hod);

    initTenant('hq');
    $department = Department::create([
        'kode_department' => 'DEP-TL',
        'nama_department' => 'Tech Lead',
        'hod_user_id' => $hod->getKey(),
    ]);

    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $assignedAt = Carbon::parse('2026-10-01 09:00:00');
    $deadline = app(DeadlineCalculator::class)
        ->calculate(WorkOrderCategory::Normal, $assignedAt);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-ESC-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Escalation Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Assigned,
        'assigned_at' => $assignedAt,
        'deadline_date' => $deadline,
        'escalation_h3_sent_at' => null,
        'escalation_h5_sent_at' => null,
        'escalation_h6_sent_at' => null,
        'is_escalated' => false,
        'extend_count' => 0,
    ]);

    Artisan::call('app:check-deadline-escalation');

    // Should notify HOD as fallback
    Notification::assertSentTo($hod, WorkOrderDeadlineEscalated::class);
});

it('falls back to manager when no hod_user_id', function () {
    Notification::fake();
    $manager = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    // Create department first, then get its key
    initTenant('hq');
    $department = Department::create([
        'kode_department' => 'DEP-FM',
        'nama_department' => 'Facility Mgmt',
        'manager_user_id' => $manager->getKey(),
    ]);

    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $assignedAt = Carbon::parse('2026-10-01 09:00:00');
    $deadline = app(DeadlineCalculator::class)
        ->calculate(WorkOrderCategory::Normal, $assignedAt);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-ESC-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Escalation Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Assigned,
        'assigned_at' => $assignedAt,
        'deadline_date' => $deadline,
        'escalation_h3_sent_at' => null,
        'escalation_h5_sent_at' => null,
        'escalation_h6_sent_at' => null,
        'is_escalated' => false,
        'extend_count' => 0,
    ]);

    Artisan::call('app:check-deadline-escalation');

    Notification::assertSentTo($manager, WorkOrderDeadlineEscalated::class, function ($notification) use ($workOrder) {
        return $notification->workOrder->id === $workOrder->id;
    });
});

it('falls back to hod-role users when no direct hod or manager', function () {
    Notification::fake();
    $hodRoleUser = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $hodRoleUser->assignRole('hod');

    initTenant('hq');
    $department = Department::create([
        'kode_department' => 'DEP-HR',
        'nama_department' => 'Human Resources',
        'hod_user_id' => $hodRoleUser->getKey(),
    ]);

    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $assignedAt = Carbon::parse('2026-10-01 09:00:00');
    $deadline = app(DeadlineCalculator::class)
        ->calculate(WorkOrderCategory::Normal, $assignedAt);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-ESC-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Escalation Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Assigned,
        'assigned_at' => $assignedAt,
        'deadline_date' => $deadline,
        'escalation_h3_sent_at' => null,
        'escalation_h5_sent_at' => null,
        'escalation_h6_sent_at' => null,
        'is_escalated' => false,
        'extend_count' => 0,
    ]);

    Artisan::call('app:check-deadline-escalation');

    Notification::assertSentTo($hodRoleUser, WorkOrderDeadlineEscalated::class);
});

it('falls back to super admin when no hod recipients at all', function () {
    Notification::fake();
    $superAdmin = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    makeSuperAdmin($superAdmin);
    Log::spy();

    initTenant('hq');
    $department = Department::create([
        'kode_department' => 'DEP-OPS',
        'nama_department' => 'Operations',
    ]);

    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $assignedAt = Carbon::parse('2026-10-01 09:00:00');
    $deadline = app(DeadlineCalculator::class)
        ->calculate(WorkOrderCategory::Normal, $assignedAt);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-ESC-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Escalation Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Assigned,
        'assigned_at' => $assignedAt,
        'deadline_date' => $deadline,
        'escalation_h3_sent_at' => null,
        'escalation_h5_sent_at' => null,
        'escalation_h6_sent_at' => null,
        'is_escalated' => false,
        'extend_count' => 0,
    ]);

    Artisan::call('app:check-deadline-escalation');

    Notification::assertSentTo($superAdmin, WorkOrderDeadlineEscalated::class, function ($notification) use ($workOrder) {
        return $notification->workOrder->id === $workOrder->id;
    });
});

it('sends H+5 to HOD after H+3 already sent', function () {
    Notification::fake();
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    makeHod($hod);

    // Create WO with H+3 already sent
    $workOrder = createOverdueWorkOrder(
        WorkOrderCategory::Normal,
        escalationMarkers: ['h3' => Carbon::parse('2026-10-06 10:00:00')],
        hodUserId: $hod->getKey(),
    );

    Artisan::call('app:check-deadline-escalation');

    $workOrder->refresh();
    expect($workOrder->escalation_h5_sent_at)->not->toBeNull();
    Notification::assertSentTo($hod, WorkOrderDeadlineEscalated::class, function ($notification) use ($workOrder) {
        return $notification->workOrder->id === $workOrder->id
            && $notification->level === 'h5';
    });
});

it('sends H+6 to DGM/GM after H+3 and H+5 already sent', function () {
    Notification::fake();
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    makeHod($hod);

    // Create WO with H+3 and H+5 already sent
    $workOrder = createOverdueWorkOrder(
        WorkOrderCategory::Normal,
        escalationMarkers: [
            'h3' => Carbon::parse('2026-10-06 10:00:00'),
            'h5' => Carbon::parse('2026-10-08 10:00:00'),
        ],
        hodUserId: $hod->getKey(),
    );

    Artisan::call('app:check-deadline-escalation');

    $workOrder->refresh();
    // H+6 should have been sent
    expect($workOrder->escalation_h6_sent_at)->not->toBeNull();
    // HOD should have received all three escalations
    Notification::assertSentTo($hod, WorkOrderDeadlineEscalated::class);
});

it('skips closed work orders', function () {
    Notification::fake();
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    makeHod($hod);

    initTenant('hq');
    $department = Department::create([
        'kode_department' => 'DEP-CS',
        'nama_department' => 'Customer Support',
        'hod_user_id' => $hod->getKey(),
    ]);

    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $assignedAt = Carbon::parse('2026-10-01 09:00:00');
    $deadline = app(DeadlineCalculator::class)
        ->calculate(WorkOrderCategory::Normal, $assignedAt);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-ESC-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Escalation Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Closed,
        'assigned_at' => $assignedAt,
        'deadline_date' => $deadline,
        'escalation_h3_sent_at' => null,
        'escalation_h5_sent_at' => null,
        'escalation_h6_sent_at' => null,
        'is_escalated' => false,
        'extend_count' => 0,
    ]);

    Artisan::call('app:check-deadline-escalation');

    Notification::assertNotSentTo($hod, WorkOrderDeadlineEscalated::class);
});

/**
 * Defect broadcast pre-existing (rencana 2026-10-22 langkah 5): notify()
 * tanpa tenant current (jalur command eskalasi) hanya mengantre saluran
 * broadcast — spatie unwraps `BroadcastEvent` ke `BroadcastNotificationCreated`
 * sebelum membaca `not_tenant_aware_jobs`, jadi tanpa entry kelas inner itu
 * job-nya di-delete dan saluran broadcast hilang diam-diam.
 */
it('would drop the broadcast channel if the unwrapped event were tenant-aware (red)', function () {
    config()->set('multitenancy.not_tenant_aware_jobs', []);

    $workOrder = createOverdueWorkOrder();
    $recipient = User::query()->findOrFail($workOrder->requester_user_id);

    Tenant::forgetCurrent(); // console context — nol tenant current

    expect(fn () => $recipient->notify(new WorkOrderDeadlineEscalated($workOrder, 'h3')))
        ->toThrow(CurrentTenantCouldNotBeDeterminedInTenantAwareJob::class);
});

it('keeps the broadcast channel for notifications dispatched without a current tenant (green)', function () {
    $workOrder = createOverdueWorkOrder();
    $recipient = User::query()->findOrFail($workOrder->requester_user_id);

    Tenant::forgetCurrent(); // console context — nol tenant current

    $recipient->notify(new WorkOrderDeadlineEscalated($workOrder, 'h3'));

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => $recipient->getMorphClass(),
        'notifiable_id' => $recipient->getKey(),
    ]);
});
