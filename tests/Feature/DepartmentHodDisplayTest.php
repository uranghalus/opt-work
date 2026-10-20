<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/** Reads one department row from an Inertia departments list response. */
function departmentsPageData(TestResponse $response): array
{
    return $response->viewData('page')['props']['departments']['data'];
}

function makeHodEmployee(string $email, ?string $photoUrl = null): Employee
{
    $employee = Employee::withoutGlobalScopes()->make([
        'nama_employee' => 'Budi HOD',
        'email' => $email,
    ]);
    $employee->tenant_id = Tenant::where('code', 'hq')->first()->id;
    $employee->photo_url = $photoUrl;
    $employee->save();

    return $employee;
}

function makeHodAccount(string $email, ?string $photoUrl = null): array
{
    $employee = makeHodEmployee($email, $photoUrl);

    $user = User::create([
        'name' => 'Budi HOD',
        'email' => $email,
        'password' => bcrypt('password'),
        'employee_id' => $employee->id,
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
    ]);

    return [$employee, $user];
}

function makeDepartment(string $kode, string $nama): Department
{
    $department = Department::withoutGlobalScopes()->make([
        'kode_department' => $kode,
        'nama_department' => $nama,
    ]);
    $department->tenant_id = Tenant::where('code', 'hq')->first()->id;
    $department->save();

    return $department;
}

it('exposes the hod display payload on the departments index', function () {
    [, $user] = makeHodAccount('budi.hod@tataoptima.com', 'https://portal.test/budi.png');

    $department = makeDepartment('IT', 'Information Technology');
    $department->forceFill(['hod_user_id' => $user->id])->save();

    $admin = makeSuperAdmin(createUser());

    $rows = departmentsPageData(
        $this->actingAs($admin)->get('/hq/departments')->assertOk(),
    );

    $row = collect($rows)->firstWhere('kode_department', 'IT');

    expect($row['hod_display']['name'])->toBe('Budi HOD')
        ->and($row['hod_display']['email'])->toBe('budi.hod@tataoptima.com')
        ->and($row['hod_display']['photo_url'])->toBe('https://portal.test/budi.png');
});

it('falls back to the synced employee when the hod has no local account', function () {
    $employee = makeHodEmployee('budi.hod@tataoptima.com', 'https://portal.test/budi.png');

    $department = makeDepartment('IT', 'Information Technology');
    $department->forceFill(['hod_employee_id' => $employee->id])->save();

    $admin = makeSuperAdmin(createUser());

    $rows = departmentsPageData(
        $this->actingAs($admin)->get('/hq/departments')->assertOk(),
    );

    $row = collect($rows)->firstWhere('kode_department', 'IT');

    expect($row['hod_display']['name'])->toBe('Budi HOD')
        ->and($row['hod_display']['email'])->toBe('budi.hod@tataoptima.com')
        ->and($row['hod_display']['photo_url'])->toBe('https://portal.test/budi.png');
});

it('returns a null hod display when none is set', function () {
    makeDepartment('FIN', 'Finance');

    $admin = makeSuperAdmin(createUser());

    $rows = departmentsPageData(
        $this->actingAs($admin)->get('/hq/departments')->assertOk(),
    );

    $row = collect($rows)->firstWhere('kode_department', 'FIN');

    expect($row['hod_display'])->toBeNull();
});

it('exposes the hod display payload on the department show page', function () {
    [, $user] = makeHodAccount('budi.hod@tataoptima.com');

    $department = makeDepartment('IT', 'Information Technology');
    $department->forceFill(['hod_user_id' => $user->id])->save();

    $admin = makeSuperAdmin(createUser());

    $payload = $this->actingAs($admin)
        ->get('/hq/departments/'.$department->getKey())
        ->assertOk()
        ->viewData('page')['props']['department'];

    expect($payload['hod_display']['name'])->toBe('Budi HOD')
        ->and($payload['hod_display']['email'])->toBe('budi.hod@tataoptima.com')
        ->and($payload['manager'])->toBeNull();
});

it('falls back to the synced employee on the department show page', function () {
    $employee = makeHodEmployee('budi.hod@tataoptima.com');

    $department = makeDepartment('IT', 'Information Technology');
    $department->forceFill(['hod_employee_id' => $employee->id])->save();

    $admin = makeSuperAdmin(createUser());

    $payload = $this->actingAs($admin)
        ->get('/hq/departments/'.$department->getKey())
        ->assertOk()
        ->viewData('page')['props']['department'];

    expect($payload['hod_display']['name'])->toBe('Budi HOD')
        ->and($payload['hod_display']['email'])->toBe('budi.hod@tataoptima.com');
});
