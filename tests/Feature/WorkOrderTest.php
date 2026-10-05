<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderCreated;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Database\Models\Tenant;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    Tenant::query()->firstOrCreate(['id' => 'hq']);
    Tenant::query()->firstOrCreate(['id' => 'plant-1']);
    Role::firstOrCreate(['name' => 'hod', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin_tenant', 'guard_name' => 'web']);
});

afterEach(function () {
    Tenancy::end();
});

it('creates a work order, generates its number, and notifies the department hod', function () {
    initTenant('hq');
    $hod = createUser(['tenant_id' => 'hq']);
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => 'hq']);
    givePermission($requester, 'work-order.create');
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
    $requester = createUser(['tenant_id' => 'hq']);
    givePermission($requester, 'work-order.create');
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
    $requester = createUser(['tenant_id' => 'hq']);
    givePermission($requester, 'work-order.create');
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
    $requester = createUser(['tenant_id' => 'hq']);
    givePermission($requester, 'work-order.create');
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
    $manager = createUser(['tenant_id' => 'hq']);
    $department = Department::create([
        'kode_department' => 'DEP-FM',
        'nama_department' => 'Facility',
        'manager_user_id' => $manager->getKey(),
    ]);
    $requester = createUser(['tenant_id' => 'hq']);
    givePermission($requester, 'work-order.create');
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
    $hodUser = createUser(['tenant_id' => 'hq']);
    $hodUser->assignRole('hod');
    $employee = Employee::create(['nik_employee' => 'NIK-HOD-1', 'nama_employee' => 'HOD Dept']);
    $employee->update(['department_id' => $department->getKey()]);
    $hodUser->update(['employee_id' => $employee->getKey()]);
    $requester = createUser(['tenant_id' => 'hq']);
    givePermission($requester, 'work-order.create');
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
    $adminTenant = createUser(['tenant_id' => 'hq']);
    $adminTenant->assignRole('admin_tenant');
    $department = Department::create(['kode_department' => 'DEP-X', 'nama_department' => 'Tanpa HOD']);
    $requester = createUser(['tenant_id' => 'hq']);
    givePermission($requester, 'work-order.create');
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
    Tenancy::end();

    $requester = createUser(['tenant_id' => 'plant-1']);
    givePermission($requester, 'work-order.create');
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

it('denies creating a work order without the create permission', function () {
    $user = createUser(['tenant_id' => 'hq']);

    $this
        ->actingAs($user)
        ->post('/hq/work-orders', [
            'title' => 'Tanpa izin',
            'description' => 'Harusnya ditolak',
            'category' => 'normal',
        ])
        ->assertForbidden();
});

it('lists work orders for the requester and for reviewable departments', function () {
    initTenant('hq');
    $hod = createUser(['tenant_id' => 'hq']);
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => 'hq']);
    $other = createUser(['tenant_id' => 'hq']);
    givePermission($hod, 'work-order.read');
    givePermission($requester, 'work-order.read');

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
