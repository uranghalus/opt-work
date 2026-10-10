<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

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

    // Seed roles
    (new class extends Seeder {
        public function run(): void {
            (new \Database\Seeders\RoleAndPermissionSeeder)->run();
        }
    })->run();
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('returns paginated employees scoped to current tenant', function () {
    initTenant('hq');
    $division = Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT', 'division_id' => $division->id]);
    $position = Position::create(['nama_position' => 'Developer', 'department_id' => $department->id]);

    Employee::factory()->count(15)->create([
        'tenant_id' => $this->hq->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    // Plant 1 should not see HQ employees
    Tenant::forgetCurrent();
    initTenant('plant-1');
    Employee::factory()->count(5)->create(['tenant_id' => $this->plant1->id]);

    $admin = makeSuperAdmin(createUser());
    $response = $this->actingAs($admin)->get('/plant-1/employees');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 5)
            ->where('employees.total', 5)
        );
});

it('searches employees by NIK', function () {
    initTenant('hq');
    $division = Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT', 'division_id' => $division->id]);
    $position = Position::create(['nama_position' => 'Developer', 'department_id' => $department->id]);

    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'John Doe',
        'email' => 'john@example.com',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-002',
        'nama_employee' => 'Jane Smith',
        'email' => 'jane@example.com',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $admin = makeSuperAdmin(createUser());
    $response = $this->actingAs($admin)->get('/hq/employees?search=NIK-001');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
        );
});

it('searches employees by name', function () {
    initTenant('hq');
    $division = Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT', 'division_id' => $division->id]);
    $position = Position::create(['nama_position' => 'Developer', 'department_id' => $department->id]);

    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'John Doe',
        'email' => 'john@example.com',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-002',
        'nama_employee' => 'Jane Smith',
        'email' => 'jane@example.com',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $admin = makeSuperAdmin(createUser());
    $response = $this->actingAs($admin)->get('/hq/employees?search=John');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
        );
});

it('searches employees by email', function () {
    initTenant('hq');
    $division = Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT', 'division_id' => $division->id]);
    $position = Position::create(['nama_position' => 'Developer', 'department_id' => $department->id]);

    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'John Doe',
        'email' => 'john@example.com',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $admin = makeSuperAdmin(createUser());
    $response = $this->actingAs($admin)->get('/hq/employees?search=john@example.com');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
        );
});

it('searches employees by department code', function () {
    initTenant('hq');
    $division = Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT', 'division_id' => $division->id]);
    $department2 = Department::create(['kode_department' => 'DEP-HR', 'nama_department' => 'HR', 'division_id' => $division->id]);
    $position = Position::create(['nama_position' => 'Developer', 'department_id' => $department->id]);

    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'John Doe',
        'email' => 'john@example.com',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-002',
        'nama_employee' => 'Jane Smith',
        'email' => 'jane@example.com',
        'department_id' => $department2->id,
        'position_id' => $position->id,
    ]);

    $admin = makeSuperAdmin(createUser());
    $response = $this->actingAs($admin)->get('/hq/employees?search=DEP-IT');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
        );
});

it('searches employees by position name', function () {
    initTenant('hq');
    $division = Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT', 'division_id' => $division->id]);
    $position1 = Position::create(['nama_position' => 'Developer', 'department_id' => $department->id]);
    $position2 = Position::create(['nama_position' => 'Manager', 'department_id' => $department->id]);

    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'John Doe',
        'email' => 'john@example.com',
        'department_id' => $department->id,
        'position_id' => $position1->id,
    ]);
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-002',
        'nama_employee' => 'Jane Smith',
        'email' => 'jane@example.com',
        'department_id' => $department->id,
        'position_id' => $position2->id,
    ]);

    $admin = makeSuperAdmin(createUser());
    $response = $this->actingAs($admin)->get('/hq/employees?search=Developer');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
        );
});

it('paginates employees with per_page parameter', function () {
    initTenant('hq');
    $division = Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT', 'division_id' => $division->id]);
    $position = Position::create(['nama_position' => 'Developer', 'department_id' => $department->id]);

    Employee::factory()->count(25)->create([
        'tenant_id' => $this->hq->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $admin = makeSuperAdmin(createUser());

    // Default per_page (10)
    $response = $this->actingAs($admin)->get('/hq/employees');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 10)
            ->where('employees.per_page', 10)
            ->where('employees.total', 25)
        );

    // per_page=25
    $response = $this->actingAs($admin)->get('/hq/employees?per_page=25');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 25)
            ->where('employees.per_page', 25)
        );

    // per_page=50 (capped at total)
    $response = $this->actingAs($admin)->get('/hq/employees?per_page=50');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 25)
            ->where('employees.per_page', 50)
        );
});

it('sorts employees by specified column and direction', function () {
    initTenant('hq');
    $division = Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT', 'division_id' => $division->id]);
    $position = Position::create(['nama_position' => 'Developer', 'department_id' => $department->id]);

    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'Alpha',
        'email' => 'alpha@example.com',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-002',
        'nama_employee' => 'Beta',
        'email' => 'beta@example.com',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-003',
        'nama_employee' => 'Gamma',
        'email' => 'gamma@example.com',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $admin = makeSuperAdmin(createUser());

    // Sort by nama_employee asc (default)
    $response = $this->actingAs($admin)->get('/hq/employees?sort=nama_employee&direction=asc');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 3)
        );

    // Sort by nama_employee desc
    $response = $this->actingAs($admin)->get('/hq/employees?sort=nama_employee&direction=desc');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 3)
        );

    // Sort by nik_employee
    $response = $this->actingAs($admin)->get('/hq/employees?sort=nik_employee&direction=asc');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 3)
        );
});

it('includes department, position, division, and user relations in response', function () {
    initTenant('hq');
    $division = Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT', 'division_id' => $division->id]);
    $position = Position::create(['nama_position' => 'Developer', 'department_id' => $department->id]);
    $user = createUser(['tenant_id' => $this->hq->id]);

    $employee = Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'John Doe',
        'email' => 'john@example.com',
        'number' => '081234567890',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'division_id' => $division->id,
    ]);

    // Link user to employee
    $user->update(['employee_id' => $employee->id]);

    $admin = makeSuperAdmin(createUser());
    $response = $this->actingAs($admin)->get('/hq/employees');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data.0.department')
            ->has('employees.data.0.position')
            ->has('employees.data.0.division')
            ->has('employees.data.0.user')
        );
});

it('preserves URL state on pagination', function () {
    initTenant('hq');
    $division = Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $department = Department::create(['kode_department' => 'DEP-IT', 'nama_department' => 'IT', 'division_id' => $division->id]);
    $position = Position::create(['nama_position' => 'Developer', 'department_id' => $department->id]);

    Employee::factory()->count(25)->create([
        'tenant_id' => $this->hq->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $admin = makeSuperAdmin(createUser());
    $response = $this->actingAs($admin)->get('/hq/employees?search=test&per_page=25&sort=nama_employee&direction=desc&page=2');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->where('filters.search', 'test')
            ->where('filters.per_page', 25)
            ->where('filters.sort', 'nama_employee')
            ->where('filters.direction', 'desc')
            ->where('employees.current_page', 2)
        );
});

it('denies access for users without tenant linkage', function () {
    initTenant('hq');
    Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $user = createUser(); // No tenant_id

    $response = $this->actingAs($user)->get('/hq/employees');
    $response->assertForbidden();
});

it('denies access for users from another tenant', function () {
    initTenant('hq');
    Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $user = createUser(['tenant_id' => $this->plant1->id]);

    $response = $this->actingAs($user)->get('/hq/employees');
    $response->assertForbidden();
});

it('allows access for users with correct tenant linkage', function () {
    initTenant('hq');
    Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $user = createUser(['tenant_id' => $this->hq->id]);

    $response = $this->actingAs($user)->get('/hq/employees');
    $response->assertOk();
});

it('super admin can access any tenant employees', function () {
    initTenant('hq');
    Division::create(['kode_division' => 'DIV-OPS', 'nama_division' => 'Operasional']);
    $admin = makeSuperAdmin(createUser());

    $response = $this->actingAs($admin)->get('/hq/employees');
    $response->assertOk();

    $response = $this->actingAs($admin)->get('/plant-1/employees');
    $response->assertOk();
});