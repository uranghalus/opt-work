<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderCancelled;
use App\Notifications\WorkOrderCreated;
use App\Notifications\WorkOrderRequestApproved;
use App\Notifications\WorkOrderRequestRejected;
use App\Notifications\WorkOrderRequestSubmitted;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * R1–R14: gerbang approval lintas-department (ADR-0011). Skenario kunci:
 * dept tujuan buta terhadap WO belum-rilis, approver salah department
 * ditolak, self-approval terblokir walau requester HOD, duplikat
 * dibatalkan reviewer dept tujuan, resubmit/cancel.
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
 * Department pengaju (dengan HOD + staf karyawan) dan department tujuan
 * (dengan HOD sendiri).
 *
 * @return array{0: User, 1: User, 2: User, 3: Department, 4: Department}
 */
function makeApprovalScenario(): array
{
    $tenant = Tenant::where('code', 'hq')->first();

    $hodPengaju = createUser(['tenant_id' => $tenant->id]);
    $hodPengaju->assignRole('hod');
    $hodPengaju->givePermissionTo(['work-order.approve-request', 'work-order.read']);

    $deptPengaju = Department::create([
        'kode_department' => 'DEP-IT-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Teknologi Informasi',
        'hod_user_id' => $hodPengaju->getKey(),
    ]);

    $employeePengaju = Employee::factory()->create(['department_id' => $deptPengaju->getKey()]);
    $requester = createUser(['tenant_id' => $tenant->id, 'employee_id' => $employeePengaju->getKey()]);
    $requester->assignRole('karyawan');
    $requester->givePermissionTo(['work-order.create', 'work-order.read', 'work-order.update']);

    $hodTujuan = createUser(['tenant_id' => $tenant->id]);
    $hodTujuan->assignRole('hod');
    $hodTujuan->givePermissionTo(['work-order.review', 'work-order.read']);

    $deptTujuan = Department::create([
        'kode_department' => 'DEP-TEK-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Teknik',
        'hod_user_id' => $hodTujuan->getKey(),
    ]);

    return [$requester, $hodPengaju, $hodTujuan, $deptPengaju, $deptTujuan];
}

/**
 * @return array<string, mixed>
 */
function woStorePayload(Department $target, string $title = 'Instalasi listrik ruang server', string $category = 'normal'): array
{
    return [
        'title' => $title,
        'description' => 'Mohon pemasangan jalur listrik baru.',
        'category' => $category,
        'target_department_id' => $target->getKey(),
    ];
}

function storeWorkOrderAs(User $requester, array $payload): WorkOrder
{
    test()->actingAs($requester)->post(route('work-orders.store', [
        'tenant' => 'hq',
    ]), $payload);

    // TrimStrings memangkas input request — cocokkan versi ter-trim, dan
    // ambil yang termuda (ULID monotonik) saat judul identik sengaja
    // dibuat ulang untuk kasus duplikat.
    return WorkOrder::query()
        ->where('requester_user_id', $requester->getKey())
        ->where('title', trim($payload['title']))
        ->orderByDesc('id')
        ->firstOrFail();
}

it('routes a cross-department WO into the approval gate and notifies only the requester department', function () {
    Notification::fake();
    [$requester, $hodPengaju, $hodTujuan, , $deptTujuan] = makeApprovalScenario();

    $workOrder = storeWorkOrderAs($requester, woStorePayload($deptTujuan));

    expect($workOrder->status)->toBe(WorkOrderStatus::PendingDeptApproval)
        ->and($workOrder->released_at)->toBeNull();

    Notification::assertSentTo($hodPengaju, WorkOrderRequestSubmitted::class);
    Notification::assertNothingSentTo($hodTujuan);
});

it('lets a same-department WO skip the gate and reach the target HOD directly', function () {
    Notification::fake();
    [$requester, $hodPengaju, , $deptPengaju] = makeApprovalScenario();

    $workOrder = storeWorkOrderAs($requester, woStorePayload($deptPengaju, 'Perbaikan printer ruang IT'));

    expect($workOrder->status)->toBe(WorkOrderStatus::WaitingHod)
        ->and($workOrder->released_at)->not->toBeNull();

    Notification::assertSentTo($hodPengaju, WorkOrderCreated::class);
    Notification::assertNotSentTo($hodPengaju, WorkOrderRequestSubmitted::class);
});

it('lets an account without a department skip the gate', function () {
    Notification::fake();
    [, , $hodTujuan, , $deptTujuan] = makeApprovalScenario();

    $admin = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $admin->assignRole('admin_tenant');
    $admin->givePermissionTo('work-order.create');

    $workOrder = storeWorkOrderAs($admin, woStorePayload($deptTujuan, 'WO dari admin cabang'));

    expect($workOrder->status)->toBe(WorkOrderStatus::WaitingHod)
        ->and($workOrder->released_at)->not->toBeNull();

    Notification::assertSentTo($hodTujuan, WorkOrderCreated::class);
});

it('keeps an unapproved WO invisible to the target department in list and detail', function () {
    [$requester, $hodPengaju, $hodTujuan, , $deptTujuan] = makeApprovalScenario();

    $workOrder = storeWorkOrderAs($requester, woStorePayload($deptTujuan));

    // R8 — detail: dept tujuan (bahkan pemegang work-order.read) 403.
    $this->actingAs($hodTujuan)
        ->get(route('work-orders.show', ['tenant' => 'hq', 'workOrder' => $workOrder->getKey()]))
        ->assertForbidden();

    // R8 — daftar: WO tidak muncul untuk dept tujuan…
    $this->actingAs($hodTujuan)
        ->get(route('work-orders.index', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('workOrders.data', fn ($rows) => collect($rows)->pluck('id')->doesntContain($workOrder->getKey())));

    // …tapi tetap terlihat oleh requester dan approver dept pengaju.
    $this->actingAs($requester)
        ->get(route('work-orders.show', ['tenant' => 'hq', 'workOrder' => $workOrder->getKey()]))
        ->assertOk();

    $this->actingAs($hodPengaju)
        ->get(route('work-orders.show', ['tenant' => 'hq', 'workOrder' => $workOrder->getKey()]))
        ->assertOk();
});

it('releases the WO to the target department only after approval', function () {
    Notification::fake();
    [$requester, $hodPengaju, $hodTujuan, , $deptTujuan] = makeApprovalScenario();

    $workOrder = storeWorkOrderAs($requester, woStorePayload($deptTujuan));

    $this->actingAs($hodPengaju)->post(route('work-orders.request.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertRedirect();

    $workOrder->refresh();

    expect($workOrder->status)->toBe(WorkOrderStatus::WaitingHod)
        ->and($workOrder->released_at)->not->toBeNull();

    // R11: dept tujuan baru diberi tahu SETELAH approve; requester juga.
    Notification::assertSentTo($hodTujuan, WorkOrderCreated::class);
    Notification::assertSentTo($requester, WorkOrderRequestApproved::class);

    // Dept tujuan kini bisa membuka detailnya.
    $this->actingAs($hodTujuan)
        ->get(route('work-orders.show', ['tenant' => 'hq', 'workOrder' => $workOrder->getKey()]))
        ->assertOk();
});

it('denies an approver from another department', function () {
    [$requester, , , , $deptTujuan] = makeApprovalScenario();

    $outsiderHod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $outsiderHod->assignRole('hod');
    $outsiderHod->givePermissionTo('work-order.approve-request');
    $outsiderDept = Department::create([
        'kode_department' => 'DEP-HK-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Housekeeping',
    ]);
    $outsiderHod->forceFill(['employee_id' => Employee::factory()->create(['department_id' => $outsiderDept->getKey()])->getKey()])->save();

    $workOrder = storeWorkOrderAs($requester, woStorePayload($deptTujuan));

    // R9 — policy, bukan cuma UI: 403 approve maupun reject.
    $this->actingAs($outsiderHod)->post(route('work-orders.request.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertForbidden();

    $this->actingAs($outsiderHod)->post(route('work-orders.request.reject', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), ['reason' => 'Bukan wewenang saya.'])->assertForbidden();

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::PendingDeptApproval);
});

it('blocks self-approval even when the requester is the department HOD and falls back to the deputy', function () {
    Notification::fake();
    $tenant = Tenant::where('code', 'hq')->first();

    // Requester = HOD department-nya sendiri (OQ-3).
    $hodRequester = createUser(['tenant_id' => $tenant->id]);
    $hodRequester->assignRole('hod');
    $hodRequester->givePermissionTo(['work-order.create', 'work-order.approve-request', 'work-order.read']);

    $deputy = createUser(['tenant_id' => $tenant->id]);
    $deputy->givePermissionTo(['work-order.approve-request', 'work-order.read']);

    $deptPengaju = Department::create([
        'kode_department' => 'DEP-GA-'.fake()->unique()->lexify('???'),
        'nama_department' => 'General Affairs',
        'hod_user_id' => $hodRequester->getKey(),
        'manager_user_id' => $deputy->getKey(),
    ]);

    $employee = Employee::factory()->create(['department_id' => $deptPengaju->getKey()]);
    $hodRequester->forceFill(['employee_id' => $employee->getKey()])->save();

    $hodTujuan = createUser(['tenant_id' => $tenant->id]);
    $deptTujuan = Department::create([
        'kode_department' => 'DEP-TEK2-'.fake()->unique()->lexify('???'),
        'nama_department' => 'Teknik 2',
        'hod_user_id' => $hodTujuan->getKey(),
    ]);

    $workOrder = storeWorkOrderAs($hodRequester, woStorePayload($deptTujuan, 'Renovasi gudang teknik'));

    // R9: self-approval terblokir…
    $this->actingAs($hodRequester)->post(route('work-orders.request.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertForbidden();

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::PendingDeptApproval);

    // …dan rantai fallback (deputy) yang memutuskan.
    $this->actingAs($deputy)->post(route('work-orders.request.approve', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertRedirect();

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::WaitingHod);
});

it('rejects with a mandatory reason and keeps the WO away from the target department', function () {
    Notification::fake();
    [$requester, $hodPengaju, $hodTujuan, , $deptTujuan] = makeApprovalScenario();

    $workOrder = storeWorkOrderAs($requester, woStorePayload($deptTujuan));

    // R4: alasan wajib.
    $this->actingAs($hodPengaju)->post(route('work-orders.request.reject', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [])->assertSessionHasErrors('reason');

    $this->actingAs($hodPengaju)->post(route('work-orders.request.reject', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), ['reason' => 'Anggaran department belum tersedia tahun ini.'])->assertRedirect();

    $workOrder->refresh();

    expect($workOrder->status)->toBe(WorkOrderStatus::RejectedByDept)
        ->and($workOrder->request_rejection_reason)->toBe('Anggaran department belum tersedia tahun ini.')
        ->and($workOrder->released_at)->toBeNull();

    // R4: tak pernah sampai ke dept tujuan — detail tetap 403.
    $this->actingAs($hodTujuan)
        ->get(route('work-orders.show', ['tenant' => 'hq', 'workOrder' => $workOrder->getKey()]))
        ->assertForbidden();

    Notification::assertSentTo($requester, WorkOrderRequestRejected::class);
    Notification::assertNothingSentTo($hodTujuan);
});

it('lets the requester revise and resubmit a rejected WO', function () {
    Notification::fake();
    [$requester, $hodPengaju, , , $deptTujuan] = makeApprovalScenario();

    $workOrder = storeWorkOrderAs($requester, woStorePayload($deptTujuan));

    $this->actingAs($hodPengaju)->post(route('work-orders.request.reject', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), ['reason' => 'Spesifikasi belum lengkap.'])->assertRedirect();

    // Resubmit tanpa revisi juga boleh; revisi dulu di sini (R12).
    $this->actingAs($requester)->patch(route('work-orders.request.update', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'title' => 'Instalasi listrik ruang server (revisi)',
        'description' => 'Ditambah titik stop-kontak sesuai masukan HOD.',
        'category' => 'normal',
        'target_department_id' => $deptTujuan->getKey(),
    ])->assertRedirect();

    // Hanya requester yang boleh merevisi.
    $this->actingAs($hodPengaju)->patch(route('work-orders.request.update', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), [
        'title' => 'X',
        'description' => 'Y',
        'category' => 'normal',
        'target_department_id' => $deptTujuan->getKey(),
    ])->assertForbidden();

    $this->actingAs($requester)->post(route('work-orders.request.resubmit', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertRedirect();

    $workOrder->refresh();

    expect($workOrder->status)->toBe(WorkOrderStatus::PendingDeptApproval)
        ->and($workOrder->title)->toBe('Instalasi listrik ruang server (revisi)')
        ->and($workOrder->request_rejection_reason)->toBeNull();

    Notification::assertSentTo($hodPengaju, WorkOrderRequestSubmitted::class, fn ($notification) => $notification->workOrder->getKey() === $workOrder->getKey());
});

it('lets the requester cancel a pending request and keeps it terminal', function () {
    Notification::fake();
    [$requester, $hodPengaju, , , $deptTujuan] = makeApprovalScenario();

    $workOrder = storeWorkOrderAs($requester, woStorePayload($deptTujuan));

    $this->actingAs($requester)->post(route('work-orders.request.cancel', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]), ['reason' => 'Ternyata bisa diselesaikan internal.'])->assertRedirect();

    $workOrder->refresh();

    expect($workOrder->status)->toBe(WorkOrderStatus::Cancelled);

    // Terminal: resubmit setelah cancel ditolak (409 — status salah tahap).
    $this->actingAs($requester)->post(route('work-orders.request.resubmit', [
        'tenant' => 'hq',
        'workOrder' => $workOrder->getKey(),
    ]))->assertStatus(409);

    Notification::assertSentTo($requester, WorkOrderCancelled::class);
    Notification::assertNotSentTo($hodPengaju, WorkOrderCancelled::class);
});

it('lets the target department cancel a duplicate at review while refusing an ordinary cancel', function () {
    Notification::fake();
    [$requester, $hodPengaju, $hodTujuan, , $deptTujuan] = makeApprovalScenario();

    $original = storeWorkOrderAs($requester, woStorePayload($deptTujuan, 'Pengecatan area parkir basement'));
    $this->actingAs($hodPengaju)->post(route('work-orders.request.approve', [
        'tenant' => 'hq',
        'workOrder' => $original->getKey(),
    ]))->assertRedirect();

    // WO kedua: isi sama (judul sama), sudah rilis ke dept tujuan.
    $duplicate = storeWorkOrderAs($requester, woStorePayload($deptTujuan, 'Pengecatan area parkir basement'));
    $this->actingAs($hodPengaju)->post(route('work-orders.request.approve', [
        'tenant' => 'hq',
        'workOrder' => $duplicate->getKey(),
    ]))->assertRedirect();

    expect($duplicate->refresh()->status)->toBe(WorkOrderStatus::WaitingHod);

    // OQ-4b: cancel biasa di tahap review ditolak…
    $this->actingAs($hodTujuan)->post(route('work-orders.request.cancel', [
        'tenant' => 'hq',
        'workOrder' => $duplicate->getKey(),
    ]), ['reason' => 'Sekadar batal.'])->assertForbidden();

    // …cancel di tahap review tanpa menunjuk duplikat ditolak policy (403).
    $this->actingAs($hodTujuan)->post(route('work-orders.request.cancel', [
        'tenant' => 'hq',
        'workOrder' => $duplicate->getKey(),
    ]), ['is_review_duplicate' => true])->assertForbidden();

    // …cancel sebagai duplikat (menunjuk WO asal) diizinkan.
    $this->actingAs($hodTujuan)->post(route('work-orders.request.cancel', [
        'tenant' => 'hq',
        'workOrder' => $duplicate->getKey(),
    ]), [
        'is_review_duplicate' => true,
        'duplicate_of' => $original->getKey(),
        'reason' => 'Duplikat dari WO yang sama.',
    ])->assertRedirect();

    $duplicate->refresh();

    expect($duplicate->status)->toBe(WorkOrderStatus::Cancelled);

    Notification::assertSentTo($requester, WorkOrderCancelled::class, fn ($notification) => $notification->duplicateOfNomor === $original->nomor_wo);
});

it('flags duplicate candidates on the approval notification', function () {
    Notification::fake();
    [$requester, $hodPengaju, , , $deptTujuan] = makeApprovalScenario();

    $first = storeWorkOrderAs($requester, woStorePayload($deptTujuan, 'Perbaikan chiller lantai 3'));
    $second = storeWorkOrderAs($requester, woStorePayload($deptTujuan, '  perbaikan chiller lantai 3 '));

    // Judul sama (case/spasi diabaikan) → WO kedua membawa penanda duplikat.
    Notification::assertSentTo($hodPengaju, WorkOrderRequestSubmitted::class,
        fn ($notification, $channels) => $notification->workOrder->getKey() === $second->getKey()
            && in_array($first->nomor_wo, $notification->duplicateNumbers, true));

    // WO dengan judul berbeda tidak ditandai duplikat.
    Notification::assertSentTo($hodPengaju, WorkOrderRequestSubmitted::class,
        fn ($notification, $channels) => $notification->workOrder->getKey() === $first->getKey()
            && $notification->duplicateNumbers === []);
});

it('gates urgent categories too and keeps the accident scheduling rule after release', function () {
    [$requester, $hodPengaju, $hodTujuan, , $deptTujuan] = makeApprovalScenario();

    // R6 (keputusan user): gerbang tetap berlaku untuk urgent.
    $accident = storeWorkOrderAs($requester, woStorePayload($deptTujuan, 'Kebocoran pipa utama', 'accident'));

    expect($accident->status)->toBe(WorkOrderStatus::PendingDeptApproval)
        ->and($accident->released_at)->toBeNull();

    $this->actingAs($hodPengaju)->post(route('work-orders.request.approve', [
        'tenant' => 'hq',
        'workOrder' => $accident->getKey(),
    ]))->assertRedirect();

    expect($accident->refresh()->status)->toBe(WorkOrderStatus::WaitingHod)
        ->and($accident->released_at)->not->toBeNull();

    // Aturan kategori setelah rilis tidak berubah: Accident tak boleh
    // dijadwalkan (ditangkap validasi review yang sudah teruji).
    $this->actingAs($hodTujuan)->post(route('work-orders.review', [
        'tenant' => 'hq',
        'workOrder' => $accident->getKey(),
    ]), [
        'action' => 'schedule',
        'scheduled_date' => now()->addDays(2)->toDateString(),
    ])->assertSessionHasErrors('hod_action');

    expect($accident->refresh()->status)->toBe(WorkOrderStatus::WaitingHod);
});
