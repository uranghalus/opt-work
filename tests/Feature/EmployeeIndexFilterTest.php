<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
<<<<<<< HEAD
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => '9201',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    (new RoleAndPermissionSeeder)->run();

    $this->reader = createUser(['tenant_id' => $this->hq->id]);
    $this->reader->givePermissionTo('employee.read');
=======
use App\Models\Position;
use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Langkah 6 rencana 2026-10-22: filter index karyawan (departemen/divisi/
 * posisi single-select, has_account tri-state, status all/active/banned)
 * — preseden tri-state TenantController, filter opsi diservis server-side.
 */
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

    (new RoleAndPermissionSeeder)->run();

    initTenant('hq');

    $this->department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT']);
    $this->otherDepartment = Department::create(['kode_department' => 'DEP-HR', 'nama_department' => 'HR']);
    $this->position = Position::create(['nama_position' => 'Developer', 'department_id' => $this->department->id]);
    $this->otherPosition = Position::create(['nama_position' => 'Manager', 'department_id' => $this->department->id]);
    $this->division = Division::create([
        'kode_division' => 'DIV-OPS',
        'nama_division' => 'Operasional',
        'department_id' => $this->department->id,
    ]);

    $this->admin = makeSuperAdmin(createUser());
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
});

afterEach(function () {
    Tenant::forgetCurrent();
});

<<<<<<< HEAD
function filterReader(): User
{
    return test()->reader;
}

it('filters employees by department', function () {
    $department = Department::factory()->create(['tenant_id' => $this->hq->id]);
    $inDept = Employee::factory()->create([
        'tenant_id' => $this->hq->id,
        'department_id' => $department->id,
    ]);
    Employee::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(filterReader())
        ->get(route('employees.index', ['tenant' => 'hq', 'department_id' => $department->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $inDept->id)
            ->where('filters.department_id', $department->id));
});

it('filters employees by division', function () {
    $division = Division::factory()->create(['tenant_id' => $this->hq->id]);
    $inDiv = Employee::factory()->create([
        'tenant_id' => $this->hq->id,
        'division_id' => $division->id,
    ]);
    Employee::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(filterReader())
        ->get(route('employees.index', ['tenant' => 'hq', 'division_id' => $division->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $inDiv->id));
});

it('filters employees by ban status', function () {
    $banned = Employee::factory()->create([
        'tenant_id' => $this->hq->id,
        'banned_at' => now(),
    ]);
    $active = Employee::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(filterReader())
        ->get(route('employees.index', ['tenant' => 'hq', 'status' => 'banned']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $banned->id));

    $this->actingAs(filterReader())
        ->get(route('employees.index', ['tenant' => 'hq', 'status' => 'active']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $active->id));
});

it('filters employees by linked-account state', function () {
    $linked = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $linked->id,
    ]);
    $unlinked = Employee::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(filterReader())
        ->get(route('employees.index', ['tenant' => 'hq', 'akun' => 'linked']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $linked->id));

    $this->actingAs(filterReader())
        ->get(route('employees.index', ['tenant' => 'hq', 'akun' => 'unlinked']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $unlinked->id));
});

it('combines the organizational filters with the search term', function () {
    $department = Department::factory()->create(['tenant_id' => $this->hq->id]);
    Employee::factory()->create([
        'tenant_id' => $this->hq->id,
        'department_id' => $department->id,
        'nama_employee' => 'Kontributor',
    ]);
    Employee::factory()->create([
        'tenant_id' => $this->hq->id,
        'department_id' => $department->id,
        'nama_employee' => 'Lainnya',
    ]);
    Employee::factory()->create([
        'tenant_id' => $this->hq->id,
        'nama_employee' => 'Kontributor Luar Dept',
    ]);

    $this->actingAs(filterReader())
        ->get(route('employees.index', [
            'tenant' => 'hq',
            'department_id' => $department->id,
            'search' => 'Kontributor',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.nama_employee', 'Kontributor'));
});

it('rejects invalid filter values', function () {
    $this->actingAs(filterReader())
        ->get(route('employees.index', ['tenant' => 'hq', 'status' => 'whatever']))
        ->assertSessionHasErrors('status');

    $this->actingAs(filterReader())
        ->get(route('employees.index', ['tenant' => 'hq', 'akun' => 'maybe']))
        ->assertSessionHasErrors('akun');

    // department_id/division_id are UUIDs (not integers) — see the
    // organization tables migration.
    $this->actingAs(filterReader())
        ->get(route('employees.index', ['tenant' => 'hq', 'department_id' => '999']))
        ->assertSessionHasErrors('department_id');
});

it('exposes department and division filter options for the page', function () {
    $department = Department::factory()->create([
        'tenant_id' => $this->hq->id,
        'nama_department' => 'Maintenance',
    ]);
    $division = Division::factory()->create([
        'tenant_id' => $this->hq->id,
        'nama_division' => 'Produksi',
    ]);

    $this->actingAs(filterReader())
        ->get(route('employees.index', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('filter_options.departments')
            ->has('filter_options.divisions')
            ->where('filter_options.departments.0.id', $department->id)
            ->where('filter_options.divisions.0.id', $division->id));
=======
it('combines department, position, and status filters with server-driven options', function () {
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'Alpha Dev',
        'email' => 'alpha@example.com',
        'department_id' => $this->department->id,
        'position_id' => $this->position->id,
    ]);
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-002',
        'nama_employee' => 'Beta Manager',
        'email' => 'beta@example.com',
        'department_id' => $this->department->id,
        'position_id' => $this->otherPosition->id,
    ]);
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-003',
        'nama_employee' => 'Gamma Dev Banned',
        'email' => 'gamma@example.com',
        'department_id' => $this->department->id,
        'position_id' => $this->position->id,
        'banned_at' => now(),
    ]);
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-004',
        'nama_employee' => 'Delta HR',
        'email' => 'delta@example.com',
        'department_id' => $this->otherDepartment->id,
        'position_id' => $this->position->id,
    ]);

    $response = $this->actingAs($this->admin)->get(
        '/hq/employees?department_id='.$this->department->id.'&position_id='.$this->position->id.'&status=active'
    );

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.total', 1)
            ->where('employees.data.0.nama_employee', 'Alpha Dev')
            ->where('filters.department_id', $this->department->id)
            ->where('filters.position_id', $this->position->id)
            ->where('filters.status', 'active')
            ->has('filter_options.departments')
            ->has('filter_options.divisions')
            ->has('filter_options.positions'));
});

it('filters by account linkage in both directions', function () {
    $linked = Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-101',
        'nama_employee' => 'Linked Karyawan',
        'email' => 'linked.karyawan@example.com',
        'department_id' => $this->department->id,
        'position_id' => $this->position->id,
    ]);
    $unlinked = Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-102',
        'nama_employee' => 'Unlinked Karyawan',
        'email' => 'unlinked.karyawan@example.com',
        'department_id' => $this->department->id,
        'position_id' => $this->position->id,
    ]);

    $akun = createUser(['tenant_id' => $this->hq->id]);
    $akun->update(['employee_id' => $linked->id]);

    $this->actingAs($this->admin)->get('/hq/employees?has_account=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.nama_employee', $linked->nama_employee)
            ->where('filters.has_account', true));

    $this->actingAs($this->admin)->get('/hq/employees?has_account=0')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.nama_employee', $unlinked->nama_employee)
            ->where('filters.has_account', false));
});

it('persists the organization filters across pages', function () {
    Employee::factory()->count(15)->create([
        'tenant_id' => $this->hq->id,
        'department_id' => $this->department->id,
        'position_id' => $this->position->id,
    ]);

    $this->actingAs($this->admin)->get(
        '/hq/employees?department_id='.$this->department->id
        .'&division_id='.$this->division->id
        .'&position_id='.$this->position->id
        .'&has_account=0&status=all&page=2'
    )
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.department_id', $this->department->id)
            ->where('filters.division_id', $this->division->id)
            ->where('filters.position_id', $this->position->id)
            ->where('filters.has_account', false)
            ->where('filters.status', 'all')
            ->where('employees.current_page', 2));
});

it('keeps filtered results inside the current tenant', function () {
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-HQ',
        'nama_employee' => 'HQ Banned',
        'email' => 'hq.banned@example.com',
        'banned_at' => now(),
    ]);

    Tenant::forgetCurrent();
    initTenant('plant-1');
    Employee::create([
        'tenant_id' => $this->plant1->id,
        'nik_employee' => 'NIK-P1',
        'nama_employee' => 'Plant One Banned',
        'email' => 'p1.banned@example.com',
        'banned_at' => now(),
    ]);
    Tenant::forgetCurrent();
    initTenant('hq');

    $this->actingAs($this->admin)->get('/hq/employees?status=banned')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.total', 1)
            ->where('employees.data.0.nama_employee', 'HQ Banned'));
});

it('returns an empty set when no karyawan matches the filters', function () {
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-201',
        'nama_employee' => 'Solo Karyawan',
        'email' => 'solo@example.com',
        'department_id' => $this->department->id,
        'position_id' => $this->position->id,
    ]);

    // The fixture employee has no linked akun, so has_account=1 matches nothing.
    $this->actingAs($this->admin)->get(
        '/hq/employees?department_id='.$this->department->id.'&has_account=1'
    )
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 0)
            ->where('employees.total', 0));
});

it('rejects an unknown status filter', function () {
    $this->actingAs($this->admin)->get('/hq/employees?status=maybe')
        ->assertSessionHasErrors('status');
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
});
