<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

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

afterEach(function () {
    Tenant::forgetCurrent();
});

it('divisions are read-only (sync-only): create returns 405', function () {
    $admin = makeSuperAdmin(createUser());
    $hq = Tenant::where('code', 'hq')->first();

    $this
        ->actingAs($admin)
        ->post('/hq/divisions', [
            'kode_division' => 'DIV-OPS',
            'nama_division' => 'Operasional',
        ])
        ->assertMethodNotAllowed();
});

it('divisions are read-only (sync-only): no role can create a division', function () {
    $hq = Tenant::where('code', 'hq')->first();
    $user = createUser(['tenant_id' => $hq->id]);

    $this
        ->actingAs($user)
        ->post('/hq/divisions', [
            'kode_division' => 'DIV-OPS',
            'nama_division' => 'Operasional',
        ])
        ->assertMethodNotAllowed();
});

it('isolates master data per cabang', function () {
    initTenant('hq');
    Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);

    expect(Division::query()->count())->toBe(1);

    Tenant::forgetCurrent();
    initTenant('plant-1');

    expect(Division::query()->count())->toBe(0);
});

it('returns 404 for a foreign cabang department via route binding', function () {
    initTenant('hq');
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    Tenant::forgetCurrent();

    $admin = makeSuperAdmin(createUser());

    $this
        ->actingAs($admin)
        ->get('/plant-1/departments/'.$department->getKey())
        ->assertNotFound();
});

it('denies cabang pages for users without tenant linkage', function () {
    $user = createUser();

    $this
        ->actingAs($user)
        ->get('/hq/divisions')
        ->assertForbidden();
});

it('denies cabang pages for users from another cabang', function () {
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $user = createUser(['tenant_id' => $plant1->id]);

    $this
        ->actingAs($user)
        ->get('/hq/divisions')
        ->assertForbidden();
});

it('allows reading cabang pages with tenant linkage alone', function () {
    $hq = Tenant::where('code', 'hq')->first();
    $user = createUser(['tenant_id' => $hq->id]);

    $this
        ->actingAs($user)
        ->get('/hq/divisions')
        ->assertOk();
});

it('allows super admin to view divisions on any branch', function () {
    $admin = makeSuperAdmin(createUser());
    $hq = Tenant::where('code', 'hq')->first();
    $plant1 = Tenant::where('code', 'plant-1')->first();

    expect($admin->tenant_id)->toBeNull();

    $this
        ->actingAs($admin)
        ->get('/hq/divisions')
        ->assertOk();

    $this
        ->actingAs($admin)
        ->get('/plant-1/divisions')
        ->assertOk();
});

it('rejects duplicate department code within the same cabang', function () {
    $admin = makeSuperAdmin(createUser());
    $hq = Tenant::where('code', 'hq')->first();

    $this
        ->actingAs($admin)
        ->post('/hq/departments', [
            'kode_department' => 'DEP-IT',
            'nama_department' => 'IT',
        ])
        ->assertRedirect();

    $response = $this
        ->actingAs($admin)
        ->post('/hq/departments', [
            'kode_department' => 'DEP-IT',
            'nama_department' => 'IT Duplicate',
        ]);

    $response->assertSessionHasErrors('kode_department');
    $this->assertDatabaseCount('departments', 1);
});

it('seeds the baseline roles and permissions', function () {
    // The role catalogue is seeded even where no gate reads it yet: forward-
    // looking permissions carry a forward-looking allowlist entry (ADR-0004).
    // super_admin holds ZERO database permissions — its Gate bypass via
    // User::isSuperAdmin() is the single source of power.
    $seeder = new class extends Seeder
    {
        public function run(): void
        {
            (new RoleAndPermissionSeeder)->run();
        }
    };

    $seeder->run();

    foreach (RoleAndPermissionSeeder::SYSTEM_ROLES as $role) {
        expect(Role::query()->where('name', $role)->exists())->toBeTrue($role.' missing');
    }

    expect(Role::query()->where('name', 'super_admin')->first()->hasPermissionTo('division.create'))->toBeFalse();
    expect(Role::query()->where('name', 'viewer')->first()->hasPermissionTo('division.read'))->toBeTrue();
    expect(Role::query()->where('name', 'viewer')->first()->hasPermissionTo('division.create'))->toBeFalse();
});

it('lets a super admin operate any branch without a home branch', function () {
    $admin = makeSuperAdmin(createUser());
    $hq = Tenant::where('code', 'hq')->first();
    $plant1 = Tenant::where('code', 'plant-1')->first();

    expect($admin->tenant_id)->toBeNull();

    $this
        ->actingAs($admin)
        ->get('/hq/divisions')
        ->assertOk();

    $this
        ->actingAs($admin)
        ->get('/plant-1/divisions')
        ->assertOk();
});
