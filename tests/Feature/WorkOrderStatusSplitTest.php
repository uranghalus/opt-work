<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\WorkOrderJobStatus;
use App\WorkOrderStatus;
use App\WorkOrderTicketStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

function makeWorkOrder(array $overrides = []): WorkOrder
{
    $tenant = Tenant::where('code', 'hq')->first();
    $hod = User::factory()->create(['tenant_id' => $tenant->id]);
    $department = Department::create([
        'kode_department' => 'DEP-SPLIT-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Split Test Dept',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = User::factory()->create(['tenant_id' => $tenant->id]);

    return WorkOrder::create(array_merge([
        'nomor_wo' => 'WO-SPLIT-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => 'normal',
        'title' => 'Split Test WO',
        'description' => 'Test',
        'status' => 'waiting_hod',
    ], $overrides));
}

it('adds status_tiket and status_pekerjaan columns to work_orders', function () {
    expect(Schema::hasColumn('work_orders', 'status_tiket'))->toBeTrue();
    expect(Schema::hasColumn('work_orders', 'status_pekerjaan'))->toBeTrue();
});

it('migrates existing status values into status_tiket / status_pekerjaan', function () {
    $migrationPath = database_path('migrations/2026_10_28_000000_split_wo_status_fields.php');
    expect(file_exists($migrationPath))->toBeTrue('Migration file not yet created');
    if (! file_exists($migrationPath)) {
        return;
    }

    // Recreate the pre-migration schema (status column only) with legacy data.
    Schema::dropIfExists('work_orders');
    Schema::create('work_orders', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->string('nomor_wo', 50);
        $table->string('status', 50);
        $table->timestamps();
    });

    $rows = [
        'waiting_hod' => ['status_tiket' => 'waiting_hod', 'status_pekerjaan' => null],
        'hod_approved' => ['status_tiket' => 'hod_approved', 'status_pekerjaan' => null],
        'scheduled' => ['status_tiket' => 'scheduled', 'status_pekerjaan' => null],
        'assigned' => ['status_tiket' => 'hod_approved', 'status_pekerjaan' => 'assigned'],
        'in_progress' => ['status_tiket' => null, 'status_pekerjaan' => 'in_progress'],
        'pending_verify' => ['status_tiket' => null, 'status_pekerjaan' => 'pending_verify'],
        'revision' => ['status_tiket' => null, 'status_pekerjaan' => 'revision'],
        'closed' => ['status_tiket' => 'closed', 'status_pekerjaan' => null],
        'cancelled' => ['status_tiket' => 'cancelled', 'status_pekerjaan' => null],
    ];

    foreach ($rows as $oldStatus => $expected) {
        DB::table('work_orders')->insert([
            'id' => Str::uuid(),
            'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
            'nomor_wo' => 'WO-LEGACY-'.fake()->unique()->numerify('####'),
            'status' => $oldStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $migration = require $migrationPath;
    $migration->up();

    foreach ($rows as $oldStatus => $expected) {
        $row = DB::table('work_orders')
            ->where('status', $oldStatus)
            ->first();
        expect($row->status_tiket)->toBe($expected['status_tiket']);
        expect($row->status_pekerjaan)->toBe($expected['status_pekerjaan']);
    }
});

it('status_tiket casts as WorkOrderTicketStatus', function () {
    $workOrder = makeWorkOrder(['status_tiket' => 'hod_approved']);

    expect($workOrder->status_tiket)->toBeInstanceOf(WorkOrderTicketStatus::class);
    expect($workOrder->status_tiket->value)->toBe('hod_approved');
    expect($workOrder->status_tiket->label())->toBe('Disetujui HOD');
});

it('status_pekerjaan casts as WorkOrderJobStatus', function () {
    $workOrder = makeWorkOrder(['status_pekerjaan' => 'in_progress']);

    expect($workOrder->status_pekerjaan)->toBeInstanceOf(WorkOrderJobStatus::class);
    expect($workOrder->status_pekerjaan->value)->toBe('in_progress');
    expect($workOrder->status_pekerjaan->label())->toBe('Dikerjakan');
});

it('status cast remains for backward compatibility', function () {
    $workOrder = makeWorkOrder(['status' => 'waiting_hod']);

    expect($workOrder->status)->toBeInstanceOf(WorkOrderStatus::class);
    expect($workOrder->status->value)->toBe('waiting_hod');
});
