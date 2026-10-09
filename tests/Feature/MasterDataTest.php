<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Tenant::query()->firstOrCreate(['id' => 'hq'], ['name' => 'Head Office']);
    Tenant::query()->firstOrCreate(['id' => 'plant-1'], ['name' => 'Plant 1']);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('creates a division scoped to the active cabang', function () {
    $admin = makeSuperAdmin(createUser());

    $response = $this
        ->actingAs($admin)
        ->post('/hq/divisions', [
            'kode_division' => 'DIV-OPS',
            'nama_division' => 'Operasional',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('divisions', [
        'kode_division' => 'DIV-OPS',
        'nama_division' => 'Operasional',
        'tenant_id' => 'hq',
    ]);
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
    $user = createUser(['tenant_id' => 'plant-1']);

    $this
        ->actingAs($user)
        ->get('/hq/divisions')
        ->assertForbidden();
});

it('allows reading cabang pages with tenant linkage alone', function () {
    $user = createUser(['tenant_id' => 'hq']);

    $this
        ->actingAs($user)
        ->get('/hq/divisions')
        ->assertOk();
});

it('allows creating a division without any role', function () {
    $user = createUser(['tenant_id' => 'hq']);

    $this
        ->actingAs($user)
        ->post('/hq/divisions', [
            'kode_division' => 'DIV-OPS',
            'nama_division' => 'Operasional',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('divisions', ['kode_division' => 'DIV-OPS']);
});

it('rejects duplicate department code within the same cabang', function () {
    $admin = makeSuperAdmin(createUser());

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
    // The role catalogue is retained even though no gate reads it: RBAC returns
    // at the end of the project and the seed data should already be correct.
    // Only super-admin access is live today, via the is_super_admin flag.
    $seeder = new class extends Seeder
    {
        public function run(): void
        {
            (new RoleAndPermissionSeeder)->run();
        }
    };

    $seeder->run();

    $expectedRoles = [
        'super_admin', 'admin_tenant', 'general_manager', 'deputy_general_manager',
        'hod', 'team_leader', 'karyawan', 'field_staff', 'viewer',
    ];

    foreach ($expectedRoles as $role) {
        expect(Role::query()->where('name', $role)->exists())->toBeTrue($role.' missing');
    }

    expect(Role::query()->where('name', 'super_admin')->first()->hasPermissionTo('division.create'))->toBeTrue();
    expect(Role::query()->where('name', 'viewer')->first()->hasPermissionTo('division.read'))->toBeTrue();
    expect(Role::query()->where('name', 'viewer')->first()->hasPermissionTo('division.create'))->toBeFalse();
});

it('lets a super admin operate any branch without a home branch', function () {
    $admin = makeSuperAdmin(createUser());

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
