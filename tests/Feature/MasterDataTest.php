<?php

use App\Models\Department;
use App\Models\Division;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Database\Models\Tenant;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    Tenant::query()->firstOrCreate(['id' => 'hq']);
    Tenant::query()->firstOrCreate(['id' => 'plant-1']);
});

afterEach(function () {
    Tenancy::end();
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

    Tenancy::end();
    initTenant('plant-1');

    expect(Division::query()->count())->toBe(0);
});

it('returns 404 for a foreign cabang department via route binding', function () {
    initTenant('hq');
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    Tenancy::end();

    $admin = makeSuperAdmin(createUser());

    $this
        ->actingAs($admin)
        ->get('/plant-1/departments/'.$department->getKey())
        ->assertNotFound();
});

it('denies cabang pages for users without tenant linkage', function () {
    $user = createUser();

    givePermission($user, 'division.read');

    $this
        ->actingAs($user)
        ->get('/hq/divisions')
        ->assertForbidden();
});

it('denies cabang pages for users from another cabang', function () {
    $user = createUser(['tenant_id' => 'plant-1']);

    givePermission($user, 'division.read');

    $this
        ->actingAs($user)
        ->get('/hq/divisions')
        ->assertForbidden();
});

it('allows reading cabang pages with permission and tenant linkage', function () {
    $user = createUser(['tenant_id' => 'hq']);

    givePermission($user, 'division.read');

    $this
        ->actingAs($user)
        ->get('/hq/divisions')
        ->assertOk();
});

it('denies creating a division without the create permission', function () {
    $user = createUser(['tenant_id' => 'hq']);

    givePermission($user, 'division.read');

    $this
        ->actingAs($user)
        ->post('/hq/divisions', [
            'kode_division' => 'DIV-OPS',
            'nama_division' => 'Operasional',
        ])
        ->assertForbidden();
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

it('lets the super admin bypass permission checks', function () {
    $admin = makeSuperAdmin(createUser());

    expect($admin->can('division.create'))->toBeTrue();
});
