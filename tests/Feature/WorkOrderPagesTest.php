<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;

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

function makePageWorkOrder(array $overrides = []): WorkOrder
{
    $tenant = Tenant::where('code', 'hq')->first();
    $hod = createUser(['tenant_id' => $tenant->id]);
    $department = Department::create([
        'kode_department' => 'DEP-OPS',
        'nama_department' => 'Operations',
        'hod_user_id' => $hod->getKey(),
    ]);
    $requester = createUser(['tenant_id' => $tenant->id]);

    return WorkOrder::create(array_merge([
        'nomor_wo' => 'WO-PAGE-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Page Test WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::WaitingHod,
        'assigned_at' => null,
        'deadline_date' => null,
        'is_escalated' => false,
        'extend_count' => 0,
    ], $overrides));
}

function seedPageDepartmentEmployees(WorkOrder $workOrder): void
{
    Employee::factory()->count(3)->create([
        'department_id' => $workOrder->target_department_id,
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
    ]);
}

function actorPageWithPermission(string $permission): User
{
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo($permission);

    return $user;
}

function actorPageWithoutPermission(): User
{
    return createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
}

it('renders the HOD review page for an authorized user', function () {
    $workOrder = makePageWorkOrder();
    seedPageDepartmentEmployees($workOrder);
    $actor = actorPageWithPermission('work-order.review');

    $this->actingAs($actor)
        ->get(route('work-orders.hod-review', [
            'tenant' => 'hq',
            'work_order' => $workOrder->getKey(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/hod-review')
            ->where('workOrder.id', $workOrder->getKey())
            ->has('employees'));
});

it('renders the assign page for an authorized user', function () {
    $workOrder = makePageWorkOrder();
    seedPageDepartmentEmployees($workOrder);
    $actor = actorPageWithPermission('work-order.assign');

    $this->actingAs($actor)
        ->get(route('work-orders.assign-form', [
            'tenant' => 'hq',
            'work_order' => $workOrder->getKey(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/assign')
            ->where('workOrder.id', $workOrder->getKey())
            ->has('employees'));
});

it('renders the submit results page for an authorized user', function () {
    $workOrder = makePageWorkOrder(['status' => WorkOrderStatus::Assigned]);
    $actor = actorPageWithPermission('work-order.submit');

    $this->actingAs($actor)
        ->get(route('work-orders.submit-results', [
            'tenant' => 'hq',
            'work_order' => $workOrder->getKey(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/submit-results')
            ->where('workOrder.id', $workOrder->getKey()));
});

it('renders the verify page for an authorized user', function () {
    $workOrder = makePageWorkOrder(['status' => WorkOrderStatus::PendingVerify]);
    $actor = actorPageWithPermission('work-order.verify');

    $this->actingAs($actor)
        ->get(route('work-orders.verify-form', [
            'tenant' => 'hq',
            'work_order' => $workOrder->getKey(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/verify')
            ->where('workOrder.id', $workOrder->getKey()));
});

it('returns 403 for HOD review without work-order.review permission', function () {
    $workOrder = makePageWorkOrder();
    $actor = actorPageWithoutPermission();

    $this->actingAs($actor)
        ->get(route('work-orders.hod-review', [
            'tenant' => 'hq',
            'work_order' => $workOrder->getKey(),
        ]))
        ->assertForbidden();
});

it('returns 403 for assign without work-order.assign permission', function () {
    $workOrder = makePageWorkOrder();
    $actor = actorPageWithoutPermission();

    $this->actingAs($actor)
        ->get(route('work-orders.assign-form', [
            'tenant' => 'hq',
            'work_order' => $workOrder->getKey(),
        ]))
        ->assertForbidden();
});

it('returns 403 for submit results without work-order.submit permission', function () {
    $workOrder = makePageWorkOrder();
    $actor = actorPageWithoutPermission();

    $this->actingAs($actor)
        ->get(route('work-orders.submit-results', [
            'tenant' => 'hq',
            'work_order' => $workOrder->getKey(),
        ]))
        ->assertForbidden();
});

it('returns 403 for verify without work-order.verify permission', function () {
    $workOrder = makePageWorkOrder();
    $actor = actorPageWithoutPermission();

    $this->actingAs($actor)
        ->get(route('work-orders.verify-form', [
            'tenant' => 'hq',
            'work_order' => $workOrder->getKey(),
        ]))
        ->assertForbidden();
});
