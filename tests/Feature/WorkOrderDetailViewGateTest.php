<?php

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * D3 — gate detail WO (design .scratch/wo-review-decisions/design.md):
 * `work-order.view` = pihak terlibat (requester / hod atau deputy dept
 * tujuan / pelaksana di assigned_employees) ATAU permission `work-order.read`.
 * Roles default staff memegang read; `guest` (nol permission, ADR-0006)
 * akhirnya terblokir di halaman detail, bukan cuma di daftar.
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
});

afterEach(function () {
    Tenant::forgetCurrent();
});

function gateTargetWorkOrder(): WorkOrder
{
    initTenant('hq');
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
    ]);
    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    return WorkOrder::create([
        'nomor_wo' => 'WO-DGATE-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Perbaikan AC',
        'description' => 'AC ruang rapat mati',
        'status' => WorkOrderStatus::Assigned,
        'hod_action' => 'execute_immediately',
        'assigned_at' => Carbon::today(),
        'deadline_date' => Carbon::today()->addDays(6),
    ]);
}

it('blocks a zero-permission guest member and audits the denial', function () {
    $workOrder = gateTargetWorkOrder();

    $guest = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $guest->assignRole('guest');

    $this->actingAs($guest)
        ->get("/hq/work-orders/{$workOrder->getKey()}")
        ->assertForbidden();

    $denial = app(AuditEvent::class)
        ->newQuery()
        ->where('action', 'access.denied')
        ->sole();

    expect($denial->actor_user_id)->toBe($guest->getKey())
        ->and($denial->context['route'])->toBe('work-orders.show');
});

it('shows the work order to its requester', function () {
    $workOrder = gateTargetWorkOrder();

    $this->actingAs(User::find($workOrder->requester_user_id))
        ->get("/hq/work-orders/{$workOrder->getKey()}")
        ->assertOk();
});

it('shows the work order to a cross-department assignee', function () {
    $workOrder = gateTargetWorkOrder();
    $otherDepartment = Department::create([
        'kode_department' => 'DEP-FM',
        'nama_department' => 'Maintenance',
    ]);
    $assigneeEmployee = Employee::create([
        'nik_employee' => fake()->unique()->numerify('NIK-########'),
        'nama_employee' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'department_id' => $otherDepartment->getKey(),
    ]);
    $assigneeUser = createUser([
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
        'employee_id' => $assigneeEmployee->getKey(),
    ]);
    $workOrder->forceFill(['assigned_employees' => [$assigneeEmployee->getKey()]])->save();

    $this->actingAs($assigneeUser)
        ->get("/hq/work-orders/{$workOrder->getKey()}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/show')
            ->where('workOrder.id', $workOrder->getKey()));
});

it('shows the work order to the department head', function () {
    $workOrder = gateTargetWorkOrder();
    $hod = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $workOrder->targetDepartment->forceFill(['hod_user_id' => $hod->getKey()])->save();

    $this->actingAs($hod)
        ->get("/hq/work-orders/{$workOrder->getKey()}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/show')
            ->where('workOrder.id', $workOrder->getKey()));
});

it('shows the work order to a member holding work-order.read', function () {
    $workOrder = gateTargetWorkOrder();

    $reader = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $reader->givePermissionTo('work-order.read');

    $this->actingAs($reader)
        ->get("/hq/work-orders/{$workOrder->getKey()}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/show')
            ->where('workOrder.id', $workOrder->getKey()));
});

it('blocks a member of a role without work-order.read', function () {
    $workOrder = gateTargetWorkOrder();

    Role::create([
        'name' => 'tanpa-read',
        'guard_name' => 'web',
    ]);
    $outsider = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $outsider->assignRole('tanpa-read');

    $this->actingAs($outsider)
        ->get("/hq/work-orders/{$workOrder->getKey()}")
        ->assertForbidden();
});

it('keeps another tenant 404 even for a member with work-order.read', function () {
    $workOrder = gateTargetWorkOrder();

    $plantMember = createUser(['tenant_id' => Tenant::where('code', 'plant-1')->first()->id]);
    $plantMember->givePermissionTo('work-order.read');

    $this->actingAs($plantMember)
        ->get("/plant-1/work-orders/{$workOrder->getKey()}")
        ->assertNotFound();
});

it('gates the attachment stream like show', function () {
    // Result attachment: guest terblokir, pelaksana lolos.
    initTenant('hq');
    Storage::fake('public');
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
    ]);
    $otherDepartment = Department::create([
        'kode_department' => 'DEP-FM',
        'nama_department' => 'Maintenance',
    ]);
    $assigneeEmployee = Employee::create([
        'nik_employee' => fake()->unique()->numerify('NIK-########'),
        'nama_employee' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'department_id' => $otherDepartment->getKey(),
    ]);
    $assigneeUser = createUser([
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
        'employee_id' => $assigneeEmployee->getKey(),
    ]);
    $guest = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $guest->assignRole('guest');

    initTenant('hq');

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-DGATE-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $assigneeUser->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Perbaikan AC',
        'description' => 'AC ruang rapat mati',
        'status' => WorkOrderStatus::Assigned,
        'assigned_employees' => [$assigneeEmployee->getKey()],
        'attachments' => ['work-orders/'.tenant()?->getTenantKey().'/result.png'],
    ]);

    // Put a fake file so Storage::exists() returns true
    Storage::disk('public')->put('work-orders/'.tenant()?->getTenantKey().'/result.png', 'fake-image-content');

    $this->actingAs($guest)
        ->get("/hq/work-orders/{$workOrder->getKey()}/attachments/0")
        ->assertForbidden();

    $this->actingAs($assigneeUser)
        ->get("/hq/work-orders/{$workOrder->getKey()}/attachments/0")
        ->assertOk();
});
