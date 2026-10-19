<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.optigate_portal.url' => 'https://gate.test',
        'services.optigate_portal.token' => 'test-token',
    ]);

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
    $this->get('/tenants')->assertRedirect(route('saml.redirect'));
});

test('a branch administrator cannot administer branch records', function () {
    // Branch admins run their own branch's data, not the branch record itself.
    $hq = Tenant::where('code', 'hq')->first();
    $this
        ->actingAs(User::factory()->create(['tenant_id' => $hq->id]))
        ->get('/tenants')
        ->assertForbidden();
});

test('a super admin reaches the branch list', function () {
    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/tenants')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('tenants/index')->etc(),
        );
});

test('a branch list shows names not slugs', function () {
    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/tenants')
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
        ->get('/tenants/hq')
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
        ->get('/tenants/plant-1')
        ->assertOk();
});

test('branch writes are refused for a branch administrator', function () {
    $hq = Tenant::where('code', 'hq')->first();
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $this
        ->actingAs(User::factory()->create(['tenant_id' => $hq->id]))
        ->post('/tenants', ['name' => 'Cabang Baru'])
        ->assertStatus(405); // Route no longer exists (manual create disabled)

    $this
        ->actingAs(User::factory()->create(['tenant_id' => $hq->id]))
        ->delete('/tenants/plant-1')
        ->assertStatus(405); // Route no longer exists (manual delete disabled)

    expect(Tenant::where('code', 'plant-1')->exists())->toBeTrue();
});

test('a super admin can run the sync from the ui and sees the summary flash', function () {
    Http::fake([
        'gate.test/api/companies*' => Http::response([
            'success' => true,
            'data' => [
                ['id' => 1, 'code' => 'HQ', 'name' => 'Head Office'],
            ],
            'meta' => ['current_page' => 1, 'per_page' => 1, 'total' => 1],
        ]),
    ]);

    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->post('/tenants/sync')
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Sinkronisasi selesai'));
});

test('the sync endpoint is forbidden for a branch administrator', function () {
    $hq = Tenant::where('code', 'hq')->first();

    $this
        ->actingAs(User::factory()->create(['tenant_id' => $hq->id]))
        ->post('/tenants/sync')
        ->assertForbidden();

    expect(Tenant::where('code', 'hq')->first()->name)->toBe('Head Office');
});

test('a failed sync reports an error flash instead of a success message', function () {
    // The command retries transient 5xx 3x, then fails; answer every attempt with 500.
    Http::fake(['gate.test/api/companies*' => Http::response(status: 500)]);

    $this
        ->actingAs(makeSuperAdmin(User::factory()->create()))
        ->post('/tenants/sync')
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Tenant::query()->count())->toBe(2);
});
