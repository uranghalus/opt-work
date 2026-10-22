<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
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
});

afterEach(function () {
    Tenant::forgetCurrent();
});

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
});
