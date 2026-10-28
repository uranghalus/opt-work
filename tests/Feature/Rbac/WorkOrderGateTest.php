<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\WorkOrderCategory;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();

    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('rejects create work order without work-order.create permission', function () {
    initTenant('hq');
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $this->actingAs($user)->get('/hq/work-orders/create')->assertForbidden();
    $this->actingAs($user)->post('/hq/work-orders', [
        'title' => 'Perbaikan AC',
        'description' => 'AC ruang rapat mati',
        'category' => WorkOrderCategory::Normal->value,
        'target_department_id' => Department::factory()->create()->getKey(),
    ])->assertForbidden();
});

it('allows create work order with work-order.create permission', function () {
    initTenant('hq');
    $hq = Tenant::where('code', 'hq')->first();
    $user = createUser(['tenant_id' => $hq->id]);
    $user->givePermissionTo('work-order.create');

    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
        'hod_user_id' => $user->getKey(),
    ]);

    $response = $this->actingAs($user)->get('/hq/work-orders/create');
    $response->assertOk();
});

it('rejects work order index without work-order.read permission', function () {
    initTenant('hq');
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $this->actingAs($user)->get('/hq/work-orders')->assertForbidden();
});

it('allows work order index with work-order.read permission', function () {
    initTenant('hq');
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('work-order.read');

    $this->actingAs($user)->get('/hq/work-orders')->assertOk();
});

it('rejects updateRequest without work-order.update permission', function () {
    initTenant('hq');
    $hq = Tenant::where('code', 'hq')->first();
    $user = createUser(['tenant_id' => $hq->id]);
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
        'hod_user_id' => $user->getKey(),
    ]);
    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-GATE-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $user->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Gate test WO',
        'description' => 'desc',
        'status' => 'waiting_hod',
    ]);

    $this->actingAs($user)
        ->patch("/hq/work-orders/{$workOrder->getKey()}/request", [
            'title' => 'Revisi',
            'description' => 'Revisi desc',
            'category' => WorkOrderCategory::Normal->value,
            'target_department_id' => $department->getKey(),
        ])
        ->assertForbidden();
});
