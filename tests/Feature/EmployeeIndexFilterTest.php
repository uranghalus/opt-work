<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
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
});

afterEach(function () {
    Tenant::forgetCurrent();
});

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
});
