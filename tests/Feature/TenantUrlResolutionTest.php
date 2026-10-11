<?php

use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Seeder;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => '1',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    $this->plant1 = createTestTenant([
        'optigate_company_id' => '2',
        'code' => 'plant-1',
        'name' => 'Plant 1',
    ]);

    (new class extends Seeder
    {
        public function run(): void
        {
            (new RoleAndPermissionSeeder)->run();
        }
    })->run();
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('returns 404 for an unknown tenant code in the URL', function () {
    $admin = makeSuperAdmin(createUser());

    $this->actingAs($admin)->get('/no-such-tenant/dashboard')->assertNotFound();
});

it('never matches partially: a string that merely contains a code is 404', function () {
    $admin = makeSuperAdmin(createUser());

    $this->actingAs($admin)->get('/hq-extra/dashboard')->assertNotFound();
});

it('resolves tenant codes case-insensitively', function () {
    // Isolate from tenants made current during setup: the URL must be the
    // only thing that can put 'hq' into the shared props.
    Tenant::forgetCurrent();

    $admin = makeSuperAdmin(createUser());

    $this->actingAs($admin)->get('/HQ/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('activeTenant', 'hq'));
});

it('denies a normal user another tenant URL', function () {
    $user = createUser(['tenant_id' => $this->hq->id]);

    $this->actingAs($user)->get('/plant-1/dashboard')->assertForbidden();
});

it('lets the superadmin open any tenant URL', function () {
    $admin = makeSuperAdmin(createUser());

    $this->actingAs($admin)->get('/plant-1/dashboard')->assertOk();
});
