<?php

use App\Models\Department;
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

function makeEditWorkOrder(array $overrides = []): WorkOrder
{
    $tenant = Tenant::where('code', 'hq')->first();
    $department = Department::create([
        'kode_department' => 'DEP-OPS',
        'nama_department' => 'Operations',
    ]);
    $requester = createUser(['tenant_id' => $tenant->id]);

    return WorkOrder::create(array_merge([
        'nomor_wo' => 'WO-EDIT-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Edit Test WO',
        'description' => 'Deskripsi uji coba',
        'status' => WorkOrderStatus::WaitingHod,
        'deadline_date' => null,
        'is_escalated' => false,
        'extend_count' => 0,
    ], $overrides));
}

function actorEditWithPermission(string $permission): User
{
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo($permission);

    return $user;
}

function actorEditWithoutPermission(): User
{
    return createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
}

it('renders the edit page for an authorized user', function () {
    $workOrder = makeEditWorkOrder();
    $actor = actorEditWithPermission('work-order.update');

    $this->actingAs($actor)
        ->get(route('work-orders.edit', [
            'tenant' => 'hq',
            'workOrder' => $workOrder->getKey(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/edit')
            ->where('workOrder.id', $workOrder->getKey())
            ->where('workOrder.nomor_wo', $workOrder->nomor_wo)
            ->has('departments')
            ->has('categories'));
});

it('returns 403 for edit without work-order.update permission', function () {
    $workOrder = makeEditWorkOrder();
    $actor = actorEditWithoutPermission();

    $this->actingAs($actor)
        ->get(route('work-orders.edit', [
            'tenant' => 'hq',
            'workOrder' => $workOrder->getKey(),
        ]))
        ->assertForbidden();
});

it('updates a work order and redirects to the detail page', function () {
    $workOrder = makeEditWorkOrder();
    $actor = actorEditWithPermission('work-order.update');
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
    ]);

    $response = $this->actingAs($actor)
        ->put(route('work-orders.update', [
            'tenant' => 'hq',
            'workOrder' => $workOrder->getKey(),
        ]), [
            'title' => 'Judul Diperbarui',
            'description' => 'Deskripsi diperbarui',
            'category' => 'normal',
            'target_department_id' => $department->getKey(),
        ]);

    $response->assertRedirect(
        route('work-orders.show', ['tenant' => 'hq', 'workOrder' => $workOrder->getKey()]),
    );
    $response->assertSessionHas('success');

    $workOrder->refresh();
    expect($workOrder->title)->toBe('Judul Diperbarui');
    expect($workOrder->description)->toBe('Deskripsi diperbarui');
    expect($workOrder->target_department_id)->toBe($department->getKey());
});

it('returns 403 for update without work-order.update permission', function () {
    $workOrder = makeEditWorkOrder();
    $actor = actorEditWithoutPermission();

    $this->actingAs($actor)
        ->put(route('work-orders.update', [
            'tenant' => 'hq',
            'workOrder' => $workOrder->getKey(),
        ]), [
            'title' => 'Judul Diperbarui',
            'description' => 'Deskripsi diperbarui',
            'category' => 'normal',
            'target_department_id' => $workOrder->target_department_id,
        ])
        ->assertForbidden();
});

it('rejects an update with an empty title without changing the work order', function () {
    $workOrder = makeEditWorkOrder();
    $actor = actorEditWithPermission('work-order.update');

    $this->actingAs($actor)
        ->put(route('work-orders.update', [
            'tenant' => 'hq',
            'workOrder' => $workOrder->getKey(),
        ]), ['title' => ''])
        ->assertSessionHasErrors('title');

    expect(WorkOrder::where('id', $workOrder->getKey())->value('title'))->toBe('Edit Test WO');
});

it('returns 404 for an unknown work order on edit, update, and destroy', function () {
    $actor = actorEditWithPermission('work-order.update');
    $unknown = '00000000-0000-0000-0000-000000000000';
    $department = Department::create([
        'kode_department' => 'DEP-X',
        'nama_department' => 'X',
    ]);

    $this->actingAs($actor)
        ->get(route('work-orders.edit', ['tenant' => 'hq', 'workOrder' => $unknown]))
        ->assertNotFound();

    $this->actingAs($actor)
        ->put(route('work-orders.update', ['tenant' => 'hq', 'workOrder' => $unknown]), [
            'title' => 'Judul Diperbarui',
            'description' => 'Deskripsi diperbarui',
            'category' => 'normal',
            'target_department_id' => $department->getKey(),
        ])
        ->assertNotFound();

    $this->actingAs(actorEditWithPermission('work-order.delete'))
        ->delete(route('work-orders.destroy', ['tenant' => 'hq', 'workOrder' => $unknown]))
        ->assertNotFound();
});

it('deletes a work order and redirects to the index', function () {
    $workOrder = makeEditWorkOrder();
    $actor = actorEditWithPermission('work-order.delete');

    $this->actingAs($actor)
        ->delete(route('work-orders.destroy', [
            'tenant' => 'hq',
            'workOrder' => $workOrder->getKey(),
        ]))
        ->assertRedirect(route('work-orders.index', ['tenant' => 'hq']))
        ->assertSessionHas('success');

    expect(WorkOrder::where('id', $workOrder->getKey())->exists())->toBeFalse();
});

it('returns 403 for destroy without work-order.delete permission', function () {
    $workOrder = makeEditWorkOrder();
    $actor = actorEditWithoutPermission();

    $this->actingAs($actor)
        ->delete(route('work-orders.destroy', [
            'tenant' => 'hq',
            'workOrder' => $workOrder->getKey(),
        ]))
        ->assertForbidden();
});
