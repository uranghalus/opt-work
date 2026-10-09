<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

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

test('guests are redirected to the sso portal', function () {
    $this->get('/settings/tenants')->assertRedirect(route('saml.redirect'));
});

test('a branch administrator cannot administer branch records', function () {
    // Branch admins run their own branch's data, not the branch record itself.
    $hq = Tenant::where('code', 'hq')->first();
    $this
        ->actingAs(User::factory()->create(['tenant_id' => $hq->id]))
        ->get('/settings/tenants')
        ->assertForbidden();
});

test('a super admin reaches the branch list', function () {
    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/settings/tenants')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('settings/tenants/index')->etc(),
        );
});

test('a branch list shows names not slugs', function () {
    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/settings/tenants')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('tenants.data.0.name', 'Head Office')
                ->where('tenants.data.0.code', 'hq')
                ->etc(),
        );
});

test('a branch detail page reports its tenant-scoped row counts', function () {
    $hq = Tenant::where('code', 'hq')->first();
    $hq->makeCurrent();
    // WorkOrderFactory builds its own target department, so create the work
    // orders first and read the department count back rather than assuming.
    WorkOrder::factory()->count(2)->create();
    $expectedDepartments = Department::query()->count();
    Tenant::forgetCurrent();

    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/settings/tenants/hq')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('branch.id', $hq->id)
                ->where('branch.name', 'Head Office')
                ->where('branch.code', 'hq')
                ->where('usage.work order', 2)
                ->where('usage.department', $expectedDepartments)
                ->where('usage.karyawan', 0)
                ->etc(),
        );
});

test('an inactive branch is still reachable by url', function () {
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $plant1->update(['is_active' => false]);

    // Archiving must not orphan historical links.
    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/settings/tenants/plant-1')
        ->assertOk();
});

test('branch writes are refused for a branch administrator', function () {
    $hq = Tenant::where('code', 'hq')->first();
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $this
        ->actingAs(User::factory()->create(['tenant_id' => $hq->id]))
        ->post('/settings/tenants', ['name' => 'Cabang Baru'])
        ->assertStatus(405); // Route no longer exists (manual create disabled)

    $this
        ->actingAs(User::factory()->create(['tenant_id' => $hq->id]))
        ->delete('/settings/tenants/plant-1')
        ->assertStatus(405); // Route no longer exists (manual delete disabled)

    expect(Tenant::where('code', 'plant-1')->exists())->toBeTrue();
});