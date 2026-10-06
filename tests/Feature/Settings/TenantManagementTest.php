<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

beforeEach(function () {
    Tenant::query()->firstOrCreate(['id' => 'hq'], ['name' => 'Head Office']);
    Tenant::query()->firstOrCreate(['id' => 'plant-1'], ['name' => 'Plant 1']);
});

test('guests are redirected to the sso portal', function () {
    $this->get('/settings/tenants')->assertRedirect(route('saml.redirect'));
});

test('a branch administrator cannot administer branch records', function () {
    // Branch admins run their own branch's data, not the branch record itself.
    $this
        ->actingAs(User::factory()->create(['tenant_id' => 'hq']))
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
                ->where('tenants.data.0.id', 'hq')
                ->etc(),
        );
});

test('creating a branch derives its slug from the code', function () {
    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->post('/settings/tenants', [
            'name' => 'Plant 2',
            'code' => 'P02',
        ])
        ->assertRedirect(route('tenants.show', ['tenant' => 'p02']));

    $branch = Tenant::query()->findOrFail('p02');

    expect($branch->name)->toBe('Plant 2')
        ->and($branch->code)->toBe('P02')
        ->and($branch->is_active)->toBeTrue();
});

test('creating a branch without a code derives its slug from the name', function () {
    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->post('/settings/tenants', ['name' => 'Plant Tiga'])
        ->assertRedirect(route('tenants.show', ['tenant' => 'plant-tiga']));

    expect(Tenant::query()->findOrFail('plant-tiga')->name)->toBe('Plant Tiga');
});

test('a duplicate slug gets a numeric suffix instead of failing', function () {
    $admin = makeSuperAdmin(User::factory()->create());

    $this->actingAs($admin)->post('/settings/tenants', ['name' => 'Head Office']);
    $this->actingAs($admin)->post('/settings/tenants', ['name' => 'Head Office']);

    expect(Tenant::query()->whereKey('head-office')->exists())->toBeTrue()
        ->and(Tenant::query()->whereKey('head-office-2')->exists())->toBeTrue();
});

test('branch names are required and codes stay unique', function () {
    Tenant::query()->create(['id' => 'plant-2', 'name' => 'Plant 2', 'code' => 'P02']);

    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->post('/settings/tenants', ['name' => '', 'code' => 'P02'])
        ->assertSessionHasErrors(['name', 'code']);
});

test('a branch detail page reports its tenant-scoped row counts', function () {
    tenancy()->initialize(Tenant::query()->findOrFail('hq'));
    // WorkOrderFactory builds its own target department, so create the work
    // orders first and read the department count back rather than assuming.
    WorkOrder::factory()->count(2)->create();
    $expectedDepartments = Department::query()->count();
    tenancy()->end();

    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/settings/tenants/hq')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('branch.id', 'hq')
                ->where('branch.name', 'Head Office')
                ->where('usage.work order', 2)
                ->where('usage.department', $expectedDepartments)
                ->where('usage.karyawan', 0)
                ->etc(),
        );
});

test('editing a branch keeps its slug', function () {
    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->put('/settings/tenants/plant-1', [
            'name' => 'Plant Satu Baru',
            'code' => 'P01',
            'is_active' => '0',
        ])
        ->assertRedirect(route('tenants.show', ['tenant' => 'plant-1']));

    $branch = Tenant::query()->findOrFail('plant-1');

    expect($branch->name)->toBe('Plant Satu Baru')
        ->and($branch->is_active)->toBeFalse();
});

test('an inactive branch is still reachable by url', function () {
    Tenant::query()->whereKey('plant-1')->update(['is_active' => false]);

    // Archiving must not orphan historical links.
    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/settings/tenants/plant-1')
        ->assertOk();
});

test('deleting an empty branch works', function () {
    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->delete('/settings/tenants/plant-1')
        ->assertRedirect(route('tenants.index'));

    expect(Tenant::query()->whereKey('plant-1')->exists())->toBeFalse();
});

test('deleting a branch that still holds data is refused', function () {
    tenancy()->initialize(Tenant::query()->findOrFail('hq'));
    Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    tenancy()->end();

    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->from('/settings/tenants/hq')
        ->delete('/settings/tenants/hq')
        ->assertRedirect(route('tenants.show', ['tenant' => 'hq']))
        ->assertSessionHasErrors('tenant_id');

    expect(Tenant::query()->whereKey('hq')->exists())->toBeTrue();
});

test('branch writes are refused for a branch administrator', function () {
    $this
        ->actingAs(User::factory()->create(['tenant_id' => 'hq']))
        ->post('/settings/tenants', ['name' => 'Cabang Baru'])
        ->assertForbidden();

    $this
        ->actingAs(User::factory()->create(['tenant_id' => 'hq']))
        ->delete('/settings/tenants/plant-1')
        ->assertForbidden();

    expect(Tenant::query()->whereKey('plant-1')->exists())->toBeTrue();
});
