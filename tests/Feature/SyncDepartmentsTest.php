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
            ['data' => [['id' => $portalId, 'code' => 'IT', 'name' => 'First Name']], 'meta' => ['total' => 1]],
            ['data' => [['id' => $portalId, 'code' => 'IT', 'name' => 'Second Name']], 'meta' => ['total' => 1]],
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
    ]);
    $stale->tenant_id = $this->hq->id;
    $stale->save();

    fakePortalDepartments([
        ['id' => 1, 'code' => 'HRD', 'name' => 'Human Resources Department'],
    ]);

    $this->artisan('app:sync-departments', ['--tenant' => $this->hq->id])->assertSuccessful();

    $stale = Department::withoutGlobalScopes()->where('optigate_department_id', '99')->first();

    expect($stale)->not->toBeNull()
        ->and($stale->kode_department)->toContain('-sync-deleted-')
        ->and(Department::withoutGlobalScopes()->where('tenant_id', $this->hq->id)->where('kode_department', 'HRD')->exists())->toBeTrue();
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
        '*/api/companies/1/departments*' => Http::response(['data' => [['id' => 1, 'code' => 'HQ-DEP', 'name' => 'HQ Dept']]]),
        '*/api/companies/2/departments*' => Http::response(['data' => [['id' => 2, 'code' => 'BR-DEP', 'name' => 'Branch Dept']]]),
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
