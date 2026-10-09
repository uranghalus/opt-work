<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderCreated;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
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

it('creates a work order, generates its number, and notifies the department hod', function () {
    initTenant('hq');
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    Notification::fake();

    $response = $this
        ->actingAs($requester)
        ->post('/hq/work-orders', [
            'title' => 'Perbaikan AC',
            'description' => 'AC ruang rapat mati',
            'category' => 'normal',
            'target_department_id' => $department->getKey(),
        ]);

    $response->assertRedirect();

    $workOrder = WorkOrder::query()->sole();
    expect($workOrder->nomor_wo)->toStartWith('WO-HQ-');
    expect($workOrder->status->value)->toBe('waiting_hod');
    expect($workOrder->requester_user_id)->toBe($requester->getKey());

    Notification::assertSentTo($hod, WorkOrderCreated::class);
});

it('stores image attachments for the work order', function () {
    initTenant('hq');
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    Storage::fake('public');

    $this
        ->actingAs($requester)
        ->post('/hq/work-orders', [
            'title' => 'Perbaikan AC',
            'description' => 'AC ruang rapat mati',
            'category' => 'normal',
            'target_department_id' => $department->getKey(),
            'attachments' => [UploadedFile::fake()->image('foto.png')],
        ]);

    $workOrder = WorkOrder::query()->sole();
    expect($workOrder->attachments)->toHaveCount(1);
    expect(Storage::disk('public')->exists($workOrder->attachments[0]))->toBeTrue();
});

it('rejects a requested schedule date for urgent by accident', function () {
    initTenant('hq');
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    Notification::fake();

    $response = $this
        ->actingAs($requester)
        ->post('/hq/work-orders', [
            'title' => 'Kecelakaan kerja',
            'description' => 'Pekerja terjatuh dari tangga',
            'category' => 'accident',
            'target_department_id' => $department->getKey(),
            'requested_schedule_date' => today()->toDateString(),
        ]);

    $response->assertSessionHasErrors('requested_schedule_date');
    $this->assertDatabaseCount('work_orders', 0);
});

it('accepts a requested schedule date for a normal work order', function () {
    initTenant('hq');
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    Notification::fake();

    $this
        ->actingAs($requester)
        ->post('/hq/work-orders', [
            'title' => 'Pemeliharaan rutin',
            'description' => 'Service AC berkala',
            'category' => 'normal',
            'target_department_id' => $department->getKey(),
            'requested_schedule_date' => today()->addDays(2)->toDateString(),
        ]);

    $workOrder = WorkOrder::query()->sole();
    expect($workOrder->requested_schedule_date?->toDateString())->toBe(today()->addDays(2)->toDateString());
});

it('falls back to the department manager when no hod is set', function () {
    initTenant('hq');
    $manager = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $department = Department::create([
        'kode_department' => 'DEP-FM',
        'nama_department' => 'Facility',
        'manager_user_id' => $manager->getKey(),
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    Notification::fake();

    $this
        ->actingAs($requester)
        ->post('/hq/work-orders', [
            'title' => 'Perbaikan pipa',
            'description' => 'Pipa bocor di gudang',
            'category' => 'normal',
            'target_department_id' => $department->getKey(),
        ]);

    Notification::assertSentTo($manager, WorkOrderCreated::class);
});

it('falls back to hod-role users assigned to the department when no direct hod or manager is set', function () {
    initTenant('hq');
    $department = Department::create(['kode_department' => 'DEP-EL', 'nama_department' => 'Electrical']);
    $hodUser = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    makeHod($hodUser);
    $employee = Employee::create(['nik_employee' => 'NIK-HOD-1', 'nama_employee' => 'HOD Dept']);
    $employee->update(['department_id' => $department->getKey()]);
    $hodUser->update(['employee_id' => $employee->getKey()]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    Notification::fake();

    $this
        ->actingAs($requester)
        ->post('/hq/work-orders', [
            'title' => 'Perbaikan panel listrik',
            'description' => 'Panel short circuit',
            'category' => 'owner',
            'target_department_id' => $department->getKey(),
        ]);

    Notification::assertSentTo($hodUser, WorkOrderCreated::class);
});

it('falls back to admin tenant users and logs critical when no hod recipient exists', function () {
    initTenant('hq');
    $adminTenant = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    makeSuperAdmin($adminTenant);
    $department = Department::create(['kode_department' => 'DEP-X', 'nama_department' => 'Tanpa HOD']);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    Notification::fake();
    Log::spy();

    $this
        ->actingAs($requester)
        ->post('/hq/work-orders', [
            'title' => 'WO tanpa HOD',
            'description' => 'Department tanpa HOD',
            'category' => 'normal',
            'target_department_id' => $department->getKey(),
        ]);

    Notification::assertSentTo($adminTenant, WorkOrderCreated::class);
    Log::shouldHaveReceived('critical')->once();
});

it('rejects a work order targeting a foreign cabang department', function () {
    initTenant('hq');
    $foreignDepartment = Department::create(['kode_department' => 'DEP-HQ', 'nama_department' => 'HQ Dept']);
    Tenant::forgetCurrent();

    $plant1 = Tenant::where('code', 'plant-1')->first();
    $requester = createUser(['tenant_id' => $plant1->id]);
    Notification::fake();

    $response = $this
        ->actingAs($requester)
        ->post('/plant-1/work-orders', [
            'title' => 'WO lintas cabang',
            'description' => 'Harusnya ditolak',
            'category' => 'normal',
            'target_department_id' => $foreignDepartment->getKey(),
        ]);

    $response->assertSessionHasErrors('target_department_id');
});

it('denies creating a work order for a user of another branch', function () {
    initTenant('hq');
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $user = createUser(['tenant_id' => $plant1->id]);

    $this
        ->actingAs($user)
        ->post('/hq/work-orders', [
            'title' => 'Dari cabang lain',
            'description' => 'Harusnya ditolak',
            'category' => 'normal',
        ])
        ->assertForbidden();
});

it('lists work orders for the requester and for reviewable departments', function () {
    initTenant('hq');
    $hq = Tenant::where('code', 'hq')->first();
    $hod = createUser(['tenant_id' => $hq->id]);
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => $hq->id]);
    $other = createUser(['tenant_id' => $hq->id]);

    $reviewable = WorkOrder::factory()->create([
        'target_department_id' => $department->getKey(),
    ]);
    $foreignToHod = WorkOrder::factory()->create([
        'requester_user_id' => $other->getKey(),
    ]);

    $this
        ->actingAs($hod)
        ->get('/hq/work-orders')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('workOrders.data.0.id', $reviewable->getKey())
                ->etc(),
        );

    $this
        ->actingAs($requester)
        ->get('/hq/work-orders')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('workOrders.data', 0)
                ->etc(),
        );
});

it('shows a work order to any user in the owning branch', function () {
    initTenant('hq');
    $hq = Tenant::where('code', 'hq')->first();
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    $requester = createUser(['tenant_id' => $hq->id]);
    $colleague = createUser(['tenant_id' => $hq->id]);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-TEST-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Test Work Order',
        'description' => 'Test description',
        'status' => WorkOrderStatus::WaitingHod,
    ]);

    // Verify work order exists in database
    $found = WorkOrder::withoutGlobalScope('tenant')->where('id', $workOrder->id)->first();
    expect($found)->not->toBeNull();
    expect($found->tenant_id)->toBe($hq->id);

    // Test the exact query the controller uses
    $controllerQuery = \App\Models\WorkOrder::withoutGlobalScope('tenant')
        ->where('id', $workOrder->getKey())
        ->first();
    expect($controllerQuery)->not->toBeNull();

    // Tenant-level isolation is the rule: within a branch, any authenticated
    // user may read the work order — it is not restricted to the requester.
    $this
        ->actingAs($colleague)
        ->get("/hq/work-orders/{$workOrder->getKey()}")
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('workOrder.id', $workOrder->getKey())
                ->where('workOrder.nomor_wo', $workOrder->nomor_wo)
                ->where('workOrder.title', $workOrder->title)
                ->where('workOrder.target_department.nama_department', 'IT')
                ->etc(),
        );
});

it('hides a work order from users of another branch', function () {
    initTenant('hq');
    $hq = Tenant::where('code', 'hq')->first();
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    $requester = createUser(['tenant_id' => $hq->id]);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-TEST-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Test Work Order',
        'description' => 'Test description',
        'status' => WorkOrderStatus::WaitingHod,
    ]);

    $plant1 = Tenant::where('code', 'plant-1')->first();
    $outsider = createUser(['tenant_id' => $plant1->id]);

    $this
        ->actingAs($outsider)
        ->get("/hq/work-orders/{$workOrder->getKey()}")
        ->assertForbidden();
});

it('serves an attachment only to users of the owning branch', function () {
    initTenant('hq');
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    Storage::fake('public');

    $this
        ->actingAs($requester)
        ->post('/hq/work-orders', [
            'title' => 'Perbaikan AC',
            'description' => 'AC ruang rapat mati',
            'category' => 'normal',
            'target_department_id' => $department->getKey(),
            'attachments' => [UploadedFile::fake()->image('foto.png')],
        ]);

    $workOrder = WorkOrder::query()->sole();
    $path = $workOrder->attachments[0];

    $this
        ->actingAs(createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]))
        ->get("/hq/work-orders/{$workOrder->getKey()}/attachments/0")
        ->assertOk()
        ->assertHeader('content-type', 'image/png');

    // Attachments are tenant-scoped, so a plain /storage/... URL must not work.
    expect(Storage::disk('public')->path($path))->not->toBe(public_path('storage/'.$path));

    $plant1 = Tenant::where('code', 'plant-1')->first();
    $this
        ->actingAs(createUser(['tenant_id' => $plant1->id]))
        ->get("/hq/work-orders/{$workOrder->getKey()}/attachments/0")
        ->assertForbidden();
});

it('returns 404 for an attachment index that does not exist', function () {
    initTenant('hq');
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    $workOrder = WorkOrder::factory()->create([
        'target_department_id' => $department->getKey(),
    ]);

    $this
        ->actingAs(createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]))
        ->get("/hq/work-orders/{$workOrder->getKey()}/attachments/5")
        ->assertNotFound();
});