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

function makeHodAccount(string $email, ?string $photoUrl = null): array
{
    $employee = Employee::withoutGlobalScopes()->make([
        'nama_employee' => 'Budi HOD',
        'email' => $email,
    ]);
    $employee->tenant_id = Tenant::where('code', 'hq')->first()->id;
    $employee->photo_url = $photoUrl;
    $employee->save();

    $user = User::create([
        'name' => 'Budi HOD',
        'email' => $email,
        'password' => bcrypt('password'),
        'employee_id' => $employee->id,
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
    ]);

    return [$employee, $user];
}

it('exposes the hod display payload on the departments index', function () {
    [$employee, $user] = makeHodAccount('budi.hod@tataoptima.com', 'https://portal.test/budi.png');

    $department = Department::withoutGlobalScopes()->make([
        'kode_department' => 'IT',
        'nama_department' => 'Information Technology',
    ]);
    $department->tenant_id = Tenant::where('code', 'hq')->first()->id;
    $department->save();
    $department->forceFill(['hod_user_id' => $user->id])->save();

    $admin = makeSuperAdmin(createUser());

    $rows = departmentsPageData(
        $this->actingAs($admin)->get('/hq/departments')->assertOk(),
    );

    $row = collect($rows)->firstWhere('kode_department', 'IT');

    expect($row['hod']['name'])->toBe('Budi HOD')
        ->and($row['hod']['email'])->toBe('budi.hod@tataoptima.com')
        ->and($row['hod']['employee']['photo_url'])->toBe('https://portal.test/budi.png')
        ->and($row['hod']['id'])->toBe($user->id);
});

it('returns a null hod on the index when none is set', function () {
    $department = Department::withoutGlobalScopes()->make([
        'kode_department' => 'FIN',
        'nama_department' => 'Finance',
    ]);
    $department->tenant_id = Tenant::where('code', 'hq')->first()->id;
    $department->save();

    $admin = makeSuperAdmin(createUser());

    $rows = departmentsPageData(
        $this->actingAs($admin)->get('/hq/departments')->assertOk(),
    );

    $row = collect($rows)->firstWhere('kode_department', 'FIN');

    expect($row['hod'])->toBeNull();
});

it('exposes the hod display payload on the department show page', function () {
    $tenant = Tenant::where('code', 'hq')->first();
    [, $user] = makeHodAccount('budi.hod@tataoptima.com');

    $department = Department::withoutGlobalScopes()->make([
        'kode_department' => 'IT',
        'nama_department' => 'Information Technology',
    ]);
    $department->tenant_id = $tenant->id;
    $department->save();
    $department->forceFill(['hod_user_id' => $user->id])->save();

    $admin = makeSuperAdmin(createUser());

    $payload = $this->actingAs($admin)
        ->get('/hq/departments/'.$department->getKey())
        ->assertOk()
        ->viewData('page')['props']['department'];

    expect($payload['hod']['name'])->toBe('Budi HOD')
        ->and($payload['hod']['email'])->toBe('budi.hod@tataoptima.com')
        ->and($payload['manager'])->toBeNull();
});
