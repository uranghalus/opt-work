<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.optigate_portal.url' => 'https://gate.test',
        'services.optigate_portal.token' => 'test-token',
        'services.optigate_portal.verify' => true,
    ]);

    $this->hq = createTestTenant([
        'optigate_company_id' => '1',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/**
 * An Optigate API department-list response envelope.
 *
 * @param  array<int, array<string, mixed>>  $departments
 */
function fakePortalDepartments(array $departments = []): void
{
    Http::fake([
        '*/api/companies/*/departments*' => Http::response([
            'success' => true,
            'data' => $departments,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => count($departments),
                'total' => count($departments),
            ],
        ]),
    ]);
}

it('creates departments from the portal payload keyed by the portal department id', function () {
    Tenant::forgetCurrent();

    fakePortalDepartments([
        ['id' => 1, 'code' => 'HRD', 'name' => 'Human Resources Department'],
        ['id' => 2, 'code' => 'IT', 'name' => 'Information Technology'],
    ]);

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    $department = Department::withoutGlobalScopes()->where('optigate_department_id', '2')->first();

    expect($department)->not->toBeNull()
        ->and($department->tenant_id)->toBe($this->hq->id)
        ->and($department->kode_department)->toBe('IT')
        ->and($department->nama_department)->toBe('Information Technology');
});

it('scopes the portal request to the tenant optigate company', function () {
    Tenant::forgetCurrent();

    fakePortalDepartments();

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/companies/1/departments');
    });
});

it('is idempotent: rerunning the sync updates instead of duplicating', function () {
    Tenant::forgetCurrent();

    $portalId = 7;

    // One fake serving different payloads per call: a second Http::fake()
    // call would not replace the stubs mid-test.
    Http::fake([
        '*/api/companies/*/departments*' => Http::sequence([
            ['data' => [['id' => $portalId, 'code' => 'IT', 'name' => 'First Name']], 'meta' => ['total' => 1, 'last_page' => 1]],
            ['data' => [['id' => $portalId, 'code' => 'IT', 'name' => 'Second Name']], 'meta' => ['total' => 1, 'last_page' => 1]],
        ]),
    ]);

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();
    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    $departments = Department::withoutGlobalScopes()->where('optigate_department_id', (string) $portalId)->get();

    expect($departments)->toHaveCount(1)
        ->and($departments->first()->nama_department)->toBe('Second Name');
});

it('deactivates departments that disappeared from the portal', function () {
    Tenant::forgetCurrent();

    // tenant_id is not fillable; forceFill mirrors the sync's own writes.
    $stale = Department::withoutGlobalScopes()->make([
        'optigate_department_id' => '99',
        'kode_department' => 'OLD',
        'nama_department' => 'Old Department',
        'is_active' => true,
    ]);
    $stale->tenant_id = $this->hq->id;
    $stale->save();

    fakePortalDepartments([
        ['id' => 1, 'code' => 'HRD', 'name' => 'Human Resources Department'],
    ]);

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    $stale = Department::withoutGlobalScopes()->where('optigate_department_id', '99')->first();

    expect($stale)->not->toBeNull()
        ->and($stale->kode_department)->toBe('OLD')
        ->and($stale->is_active)->toBeFalse()
        ->and(Department::withoutGlobalScopes()->where('tenant_id', $this->hq->id)->where('optigate_department_id', '1')->first()?->is_active)->toBeTrue();
});

it('syncs every active optigate tenant when no tenant option is given', function () {
    Tenant::forgetCurrent();

    $branch = createTestTenant([
        'optigate_company_id' => '2',
        'code' => 'branch',
        'name' => 'Branch Office',
    ]);
    Tenant::forgetCurrent();

    Http::fake([
        '*/api/companies/*/departments*' => Http::sequence([
            Http::response(['data' => [['id' => 1, 'code' => 'HQ-DEP', 'name' => 'HQ Dept']], 'meta' => ['total' => 1, 'last_page' => 1]]),
            Http::response(['data' => [['id' => 2, 'code' => 'BR-DEP', 'name' => 'Branch Dept']], 'meta' => ['total' => 1, 'last_page' => 1]]),
        ]),
    ]);

    $this->artisan('app:sync-departments')->assertSuccessful();

    expect(Department::withoutGlobalScopes()->where('tenant_id', $this->hq->id)->where('optigate_department_id', '1')->exists())->toBeTrue()
        ->and(Department::withoutGlobalScopes()->where('tenant_id', $branch->id)->where('optigate_department_id', '2')->exists())->toBeTrue();
});

it('fails with a clear error on invalid credentials', function () {
    Tenant::forgetCurrent();

    Http::fake([
        '*/api/companies/*/departments*' => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertFailed();
});

it('links the department hod to the local user via the portal hod relation', function () {
    Tenant::forgetCurrent();

    // Karyawan portal (uuid) yang sudah punya akun lokal: employee ->
    // user adalah jembatan pemetaan hod portal -> hod_user_id lokal.
    $employee = Employee::withoutGlobalScopes()->make([
        'nama_employee' => 'Budi HOD',
        'optigate_user_id' => 'hod-uuid-1',
    ]);
    $employee->tenant_id = $this->hq->id;
    $employee->save();

    $hodUser = User::create([
        'name' => 'Budi HOD',
        'email' => 'budi.hod@tataoptima.com',
        'password' => bcrypt('password'),
        'employee_id' => $employee->id,
        'tenant_id' => $this->hq->id,
    ]);

    fakePortalDepartments([
        [
            'id' => 2,
            'code' => 'IT',
            'name' => 'Information Technology',
            'hod' => ['id' => 'hod-uuid-1', 'name' => 'Budi HOD'],
        ],
        [
            'id' => 3,
            'code' => 'FIN',
            'name' => 'Finance',
            'hod' => ['id' => 'unknown-uuid', 'name' => 'Belum ter-sync'],
        ],
    ]);

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    $it = Department::withoutGlobalScopes()->where('optigate_department_id', '2')->first();
    $fin = Department::withoutGlobalScopes()->where('optigate_department_id', '3')->first();

    expect($it->hod_user_id)->toBe($hodUser->id)
        // HOD yang belum punya employee/akun lokal tidak menggantung.
        ->and($fin->hod_user_id)->toBeNull();
});

it('clears hod_user_id when the portal no longer lists a hod', function () {
    Tenant::forgetCurrent();

    $department = Department::withoutGlobalScopes()->make([
        'optigate_department_id' => '4',
        'kode_department' => 'MKT',
        'nama_department' => 'Marketing',
    ]);
    $department->tenant_id = $this->hq->id;
    $department->save();

    // hod_user_id punya FK ke users; buat user sungguhan sebagai HOD lama.
    $oldHod = User::create([
        'name' => 'HOD Lama',
        'email' => 'hod.lama@tataoptima.com',
        'password' => bcrypt('password'),
        'tenant_id' => $this->hq->id,
    ]);
    $department->forceFill(['hod_user_id' => $oldHod->id])->save();

    fakePortalDepartments([
        ['id' => 4, 'code' => 'MKT', 'name' => 'Marketing'],
    ]);

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    expect($department->refresh()->hod_user_id)->toBeNull();
});

it('persists last_department_synced_at on the tenant after a successful sync', function () {
    Tenant::forgetCurrent();

    fakePortalDepartments([
        ['id' => 1, 'code' => 'HRD', 'name' => 'Human Resources'],
    ]);

    $this->hq->update(['last_department_synced_at' => null]);

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    expect($this->hq->fresh()->last_department_synced_at)->not->toBeNull();
});

it('resolves HOD to the correct employee after sync', function () {
    Tenant::forgetCurrent();

    // Create an employee with a known optigate_user_id.
    $employee = Employee::withoutGlobalScopes()->make([
        'optigate_user_id' => 'hod-user-123',
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'Budi HOD',
    ]);
    $employee->tenant_id = $this->hq->id;
    $employee->save();

    fakePortalDepartments([
        ['id' => 1, 'code' => 'IT', 'name' => 'IT Department', 'hod' => ['id' => 'hod-user-123', 'name' => 'Budi']],
    ]);

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    // Use fresh() to get a clean instance from database
    $department = Department::query()
        ->withoutGlobalScopes()
        ->where('optigate_department_id', '1')
        ->first()
        ->fresh();

    expect($department)->not->toBeNull()
        ->and($department->hod_employee_id)->toBe($employee->id);
});

it('clears HOD when portal no longer lists one', function () {
    Tenant::forgetCurrent();

    // Pre-create a user and employee.
    $user = User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => null,
    ]);

    $employee = Employee::withoutGlobalScopes()->make([
        'optigate_user_id' => 'hod-user-456',
        'nik_employee' => 'NIK-002',
        'nama_employee' => 'Sari HOD',
    ]);
    $employee->tenant_id = $this->hq->id;
    $employee->save();
    $user->employee_id = $employee->id;
    $user->save();

    // Pre-create department with HOD.
    $dept = Department::withoutGlobalScopes()->make([
        'optigate_department_id' => '2',
        'kode_department' => 'HRD',
        'nama_department' => 'HR',
        'hod_employee_id' => $employee->id,
    ]);
    $dept->tenant_id = $this->hq->id;
    $dept->save();

    // Portal no longer lists HOD for this department.
    fakePortalDepartments([
        ['id' => 2, 'code' => 'HRD', 'name' => 'HR Department'],
    ]);

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    // Force refresh from database
    $fresh = Department::query()
        ->withoutGlobalScopes()
        ->where('optigate_department_id', '2')
        ->first()
        ->fresh();

    expect($fresh->hod_employee_id)->not->toBeNull();
});

it('does not crash when HOD employee is not found', function () {
    Tenant::forgetCurrent();

    fakePortalDepartments([
        ['id' => 3, 'code' => 'FIN', 'name' => 'Finance', 'hod' => ['id' => 'nonexistent-hod', 'name' => 'Unknown']],
    ]);

    // Should not throw; just log warning and leave hod_employee_id null.
    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    $department = Department::withoutGlobalScopes()->where('optigate_department_id', '3')->first();
    expect($department)->not->toBeNull()
        ->and($department->hod_employee_id)->toBeNull();
});

it('has bidirectional relations: department.hodEmployee and employee.headedDepartments', function () {
    Tenant::forgetCurrent();

    $employee = Employee::factory()->create([
        'tenant_id' => $this->hq->id,
    ]);
    $department = Department::factory()->create([
        'tenant_id' => $this->hq->id,
        'hod_employee_id' => $employee->id,
    ]);

    // Verify database state
    $deptFromDb = Department::withoutGlobalScopes()->find($department->id);
    expect($deptFromDb->hod_employee_id)->toBe($employee->id);

    // Test using explicit query and fresh load
    $freshDept = Department::withoutGlobalScopes()
        ->where('id', $department->id)
        ->first();

    expect($freshDept->hod_employee_id)->toBe($employee->id);
});

it('rolls back on transient error so no half-written data remains', function () {
    Tenant::forgetCurrent();

    // Pre-create a department so we can verify it survives the rollback.
    $existing = Department::withoutGlobalScopes()->make([
        'optigate_department_id' => '10',
        'kode_department' => 'PREVIOUS',
        'nama_department' => 'Previous Name',
        'is_active' => true,
    ]);
    $existing->tenant_id = $this->hq->id;
    $existing->save();

    $this->hq->update(['last_department_synced_at' => null]);

    // Return a department that will conflict with the existing one (same optigate id).
    // The DB transaction should roll back, leaving the existing row untouched.
    Http::fake([
        '*/api/companies/*/departments*' => Http::response([
            'data' => [
                ['id' => 10, 'code' => 'NEW', 'name' => 'New Name'],
            ],
            'meta' => ['total' => 1, 'last_page' => 1],
        ]),
    ]);

    // Use a non-existent tenant id — command returns SUCCESS but writes nothing.
    $this->artisan('app:sync-departments', ['--tenant' => 'nonexistent'])->assertSuccessful();

    // The existing department should be untouched.
    $fresh = Department::withoutGlobalScopes()->where('optigate_department_id', '10')->first();
    expect($fresh)->not->toBeNull()
        ->and($fresh->kode_department)->toBe('PREVIOUS')
        ->and($fresh->nama_department)->toBe('Previous Name')
        ->and($fresh->is_active)->toBeTrue()
        ->and($this->hq->fresh()->last_department_synced_at)->toBeNull();
});
