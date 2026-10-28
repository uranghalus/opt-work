<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Tenant;
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

it('rejects division index without division.read permission', function () {
    initTenant('hq');
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $this->actingAs($user)->get('/hq/divisions')->assertForbidden();
});

it('allows division index with division.read permission', function () {
    initTenant('hq');
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('division.read');

    $this->actingAs($user)->get('/hq/divisions')->assertOk();
});

it('rejects division show without division.read permission', function () {
    initTenant('hq');
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $division = Division::create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
        'kode_division' => 'DIV-IT',
        'nama_division' => 'IT',
    ]);

    $this->actingAs($user)->get("/hq/divisions/{$division->getKey()}")->assertForbidden();
});

it('rejects department index without department.read permission', function () {
    initTenant('hq');
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $this->actingAs($user)->get('/hq/departments')->assertForbidden();
});

it('allows department index with department.read permission', function () {
    initTenant('hq');
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('department.read');

    $this->actingAs($user)->get('/hq/departments')->assertOk();
});

it('rejects department show without department.read permission', function () {
    initTenant('hq');
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $department = Department::create([
        'kode_department' => 'DEP-IT',
        'nama_department' => 'IT',
    ]);

    $this->actingAs($user)->get("/hq/departments/{$department->getKey()}")->assertForbidden();
});

it('serves tenant index to a super admin', function () {
    $admin = makeSuperAdmin(createUser());

    $this->actingAs($admin)->get('/tenants')->assertOk();
});

it('serves tenant show to a super admin', function () {
    $admin = makeSuperAdmin(createUser());

    $this->actingAs($admin)->get('/tenants/hq')->assertOk();
});
