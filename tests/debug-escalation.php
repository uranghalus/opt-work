<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderDeadlineEscalated;
use App\Services\DeadlineCalculator;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

echo "Testing escalation command...\n";

// Create tenant
$tenant = Tenant::create([
    'optigate_company_id' => 999,
    'code' => 'test-esc',
    'name' => 'Test Escalation',
    'is_active' => true,
]);
$tenant->makeCurrent();

// Create users
$hod = User::create([
    'name' => 'HOD User',
    'email' => 'hod@test.com',
    'password' => bcrypt('password'),
    'tenant_id' => $tenant->id,
    'is_hod' => true,
]);

// Create department
$department = Department::create([
    'kode_department' => 'DEP-TEST',
    'nama_department' => 'Test Dept',
    'hod_user_id' => $hod->id,
]);

// Create WorkOrder
$assignedAt = Carbon::parse('2026-10-01 09:00:00');
$calculator = app(DeadlineCalculator::class);
$deadline = $calculator->calculate(WorkOrderCategory::Normal, $assignedAt);

$workOrder = WorkOrder::create([
    'nomor_wo' => 'WO-DEBUG-001',
    'requester_user_id' => $hod->id,
    'target_department_id' => $department->id,
    'category' => WorkOrderCategory::Normal,
    'title' => 'Debug WO',
    'description' => 'Debug test',
    'status' => WorkOrderStatus::Assigned,
    'assigned_at' => $assignedAt,
    'deadline_date' => $deadline,
    'escalation_h3_sent_at' => null,
    'escalation_h5_sent_at' => null,
    'escalation_h6_sent_at' => null,
    'is_escalated' => false,
    'extend_count' => 0,
]);

echo "Created WO: {$workOrder->id}\n";
echo "Assigned at: {$workOrder->assigned_at}\n";
echo "Deadline: {$workOrder->deadline_date}\n";
echo 'Now: '.now()->toDateTimeString()."\n";

// Run the command
Notification::fake();
$exitCode = Artisan::call('app:check-deadline-escalation');
echo "Command exit code: $exitCode\n";

// Check results
$workOrder->refresh();
echo 'H3 sent at: '.($workOrder->escalation_h3_sent_at ?? 'NULL')."\n";
echo 'H5 sent at: '.($workOrder->escalation_h5_sent_at ?? 'NULL')."\n";
echo 'H6 sent at: '.($workOrder->escalation_h6_sent_at ?? 'NULL')."\n";

// Check notifications
Notification::assertSentTo($hod, WorkOrderDeadlineEscalated::class);
echo "Notification sent to HOD: YES\n";

echo "Test completed!\n";
