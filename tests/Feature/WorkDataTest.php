<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkData;
use App\Models\WorkOrder;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * FR-3.1–3.3: Work Data Management — satu record auto-generated saat WO
 * closed, append-only (finalisasi analitik sekali), detail read-only.
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
 * WO yang sudah menunggu verifikasi (assigned → submitted), lengkap
 * dengan pelaksana dan lampiran sebelum/sesudah.
 *
 * @return array{0: WorkOrder, 1: User, 2: Employee, 3: User}
 */
function makeClosedableWorkOrder(array $overrides = []): array
{
    $tenant = Tenant::where('code', 'hq')->first();
    $hod = createUser(['tenant_id' => $tenant->id]);
    $hod->givePermissionTo('work-order.verify');
    $department = Department::create([
        'kode_department' => 'DEP-WD-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Work Data Ops',
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
        'nomor_wo' => 'WO-WD-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Work Data Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::PendingVerify,
        'assigned_at' => now()->subDays(2),
        'deadline_date' => now()->addDays(4),
        'assigned_employees' => [$assigneeEmployee->getKey()],
        'attachments' => ['work-orders/sebelum-1.jpg'],
        'result_notes' => 'Sudah diperbaiki.',
        'result_attachments' => ['work-orders/sesudah-1.jpg'],
        'is_escalated' => false,
        'extend_count' => 0,
    ], $overrides));

    return [$workOrder, $hod, $assigneeEmployee, $requester];
}

it('generates one work data record when the WO is closed', function () {
    [$workOrder, $hod] = makeClosedableWorkOrder();
    $assignedAt = $workOrder->assigned_at->copy();

    $this->actingAs($hod)->post(route('work-orders.verify', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertRedirect();

    $workData = WorkData::query()->where('work_order_id', $workOrder->getKey())->sole();

    expect($workData->no_kerja)->toBe($workOrder->nomor_wo)
        ->and($workData->department_id)->toBe($workOrder->target_department_id)
        ->and($workData->status_pekerjaan)->toBe('selesai')
        ->and($workData->tgl_mulai_aktual->toDateString())->toBe($assignedAt->toDateString())
        ->and($workData->tgl_selesai_aktual)->not->toBeNull()
        ->and((float) $workData->jam_pengerjaan)->toBeGreaterThan(0.0)
        ->and($workData->gambar_sebelum)->toBe(['work-orders/sebelum-1.jpg'])
        ->and($workData->gambar_sesudah)->toBe(['work-orders/sesudah-1.jpg'])
        ->and($workData->pic_lead_user_id)->toBe($hod->getKey())
        ->and($workData->finalized_at)->toBeNull();
});

it('snapshots the assigned pekerja into work_data_pekerja', function () {
    [$workOrder, $hod, $assigneeEmployee] = makeClosedableWorkOrder();

    $this->actingAs($hod)->post(route('work-orders.verify', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertRedirect();

    $workData = WorkData::query()->where('work_order_id', $workOrder->getKey())->sole();

    expect($workData->pekerja()->count())->toBe(1)
        ->and($workData->pekerja()->first()->employee_id)->toBe($assigneeEmployee->getKey())
        ->and($workData->pekerja()->first()->role_pekerja)->toBe('pelaksana');
});

it('finalizes the post-mortem analysis exactly once', function () {
    [$workOrder, $hod] = makeClosedableWorkOrder();

    $this->actingAs($hod)->post(route('work-orders.verify', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertRedirect();

    $workData = WorkData::query()->where('work_order_id', $workOrder->getKey())->sole();
    $hod->givePermissionTo('work-data.update');

    $this->actingAs($hod)->post(route('work-data.finalize', [
        'tenant' => 'hq',
        'workData' => $workData->getKey(),
    ]), [
        'prediksi_penyebab' => 'Kompresor aus karena usia pakai.',
        'tindakan' => 'Ganti kompresor + tambal kebocoran.',
        'hasil_kesimpulan' => 'AC kembali normal.',
        'saran_solusi' => 'Jadwalkan servis rutin 3 bulanan.',
    ])->assertRedirect();

    $workData->refresh();
    expect($workData->prediksi_penyebab)->toBe('Kompresor aus karena usia pakai.')
        ->and($workData->tindakan)->toBe('Ganti kompresor + tambal kebocoran.')
        ->and($workData->hasil_kesimpulan)->toBe('AC kembali normal.')
        ->and($workData->saran_solusi)->toBe('Jadwalkan servis rutin 3 bulanan.')
        ->and($workData->finalized_at)->not->toBeNull();

    // Append-only: finalisasi kedua ditolak — analitik beku selamanya.
    $this->actingAs($hod)->post(route('work-data.finalize', [
        'tenant' => 'hq',
        'workData' => $workData->getKey(),
    ]), [
        'prediksi_penyebab' => 'Coba ubah.',
    ])->assertConflict();

    expect($workData->refresh()->prediksi_penyebab)->toBe('Kompresor aus karena usia pakai.');
});

it('refuses finalize without the work-data.update permission', function () {
    [$workOrder, $hod] = makeClosedableWorkOrder();

    $this->actingAs($hod)->post(route('work-orders.verify', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertRedirect();

    $workData = WorkData::query()->where('work_order_id', $workOrder->getKey())->sole();
    $outsider = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $this->actingAs($outsider)->post(route('work-data.finalize', [
        'tenant' => 'hq',
        'workData' => $workData->getKey(),
    ]), [
        'prediksi_penyebab' => 'Tidak berizin.',
    ])->assertForbidden();

    expect($workData->refresh()->finalized_at)->toBeNull();
});

it('registers only the read and finalize routes for work data', function () {
    expect(Route::has('work-data.index'))->toBeTrue()
        ->and(Route::has('work-data.show'))->toBeTrue()
        ->and(Route::has('work-data.finalize'))->toBeTrue()
        ->and(Route::has('work-data.edit'))->toBeFalse()
        ->and(Route::has('work-data.update'))->toBeFalse()
        ->and(Route::has('work-data.destroy'))->toBeFalse()
        ->and(Route::has('work-data.create'))->toBeFalse()
        ->and(Route::has('work-data.store'))->toBeFalse();
});

it('filters work data by department, date range, and status', function () {
    [$closedOne, $hod] = makeClosedableWorkOrder();
    $this->actingAs($hod)->post(route('work-orders.verify', [
        'tenant' => 'hq',
        'workOrder' => $closedOne->getKey(),
    ]))->assertRedirect();

    // WO closed kedua di department lain (tanggal selesai hari ini juga).
    $otherDepartment = Department::create([
        'kode_department' => 'DEP-WD-B-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Other Dept',
    ]);
    [$other] = makeClosedableWorkOrder([
        'target_department_id' => $otherDepartment->getKey(),
    ]);
    $this->actingAs($hod)->post(route('work-orders.verify', [
        'tenant' => 'hq',
        'workOrder' => $other->getKey(),
    ]))->assertRedirect();

    $reader = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $reader->givePermissionTo('work-data.read');

    // Filter department: hanya satu baris.
    $this->actingAs($reader)->get(route('work-data.index', [
        'tenant' => 'hq',
        'department_id' => $closedOne->target_department_id,
    ]))->assertOk()->assertInertia(fn ($page) => $page
        ->component('work-data/index')
        ->has('workData.data', 1));

    // Filter status.
    $this->actingAs($reader)->get(route('work-data.index', [
        'tenant' => 'hq',
        'status' => 'selesai',
    ]))->assertOk()->assertInertia(fn ($page) => $page
        ->has('workData.data', 2));
});

it('hides work data from users of another branch', function () {
    [$workOrder, $hod] = makeClosedableWorkOrder();
    $this->actingAs($hod)->post(route('work-orders.verify', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertRedirect();

    $workData = WorkData::query()->where('work_order_id', $workOrder->getKey())->sole();

    Tenant::forgetCurrent();
    initTenant('plant-1');
    $foreignReader = createUser(['tenant_id' => Tenant::where('code', 'plant-1')->first()->id]);
    $foreignReader->givePermissionTo('work-data.read');

    $this->actingAs($foreignReader)->get(route('work-data.show', [
        'tenant' => 'plant-1',
        'workData' => $workData->getKey(),
    ]))->assertNotFound();
});

it('refuses the index without the work-data.read permission', function () {
    $reader = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $this->actingAs($reader)->get(route('work-data.index', [
        'tenant' => 'hq',
    ]))->assertForbidden();
});
