<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
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

function fakePortalUsers(array $users = [])
{
    Http::fake([
        '*/api/users*' => Http::response(['data' => $users]),
    ]);
}

it('creates employees from the portal payload keyed by the portal user id', function () {
    fakePortalUsers([
        [
            'id' => '019fdfb7-4959-7b3b-9a99-bcfd4e963fa1',
            'name' => 'Muhammad Ridha Fatahillah',
            'email' => 'ridho@tataoptima.com',
            'nik' => '6371012304950001',
            'whatsapp_number' => '081234567890',
            'photo_url' => 'https://s3.test/avatar.jpg',
        ],
    ]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertSuccessful();

    $employee = Employee::withoutGlobalScopes()->where('optigate_user_id', '019fdfb7-4959-7b3b-9a99-bcfd4e963fa1')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->tenant_id)->toBe($this->hq->id)
        ->and($employee->nama_employee)->toBe('Muhammad Ridha Fatahillah')
        ->and($employee->nik_employee)->toBe('6371012304950001')
        ->and($employee->number)->toBe('081234567890');
});

it('creates employees without a current tenant (CLI context regression)', function () {
    // The button/scheduler runs landlord-side: no Tenant::current() exists,
    // so the creating hook cannot backfill tenant_id. Regression for
    // "Field 'tenant_id' doesn't have a default value".
    Tenant::forgetCurrent();

    fakePortalUsers([
        ['id' => '019fdfb7-cli', 'name' => 'CLI User', 'nik' => 'NIK-CLI'],
    ]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertSuccessful();

    $employee = Employee::withoutGlobalScopes()->where('optigate_user_id', '019fdfb7-cli')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->tenant_id)->toBe($this->hq->id);
});

it('scopes the portal request to the tenant optigate company', function () {
    Tenant::forgetCurrent();

    fakePortalUsers([
        ['id' => '019fdfb7-scoped', 'name' => 'Scoped User', 'nik' => 'NIK-SC'],
    ]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/users')
            && str_contains($request->url(), 'company_id=1');
    });
});

it('is idempotent: rerunning the sync updates instead of duplicating', function () {
    $portalId = '019fdfb7-4959-7b3b-9a99-bcfd4e963fa1';

    // One fake serving different payloads per call: a second Http::fake()
    // call would not replace the stubs mid-test.
    Http::fake([
        '*/api/users*' => Http::sequence([
            ['data' => [['id' => $portalId, 'name' => 'First Name', 'nik' => 'NIK-1']]],
            ['data' => [['id' => $portalId, 'name' => 'Second Name', 'nik' => 'NIK-1']]],
        ]),
    ]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertSuccessful();
    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertSuccessful();

    expect(Employee::withoutGlobalScopes()->where('tenant_id', $this->hq->id)->count())->toBe(1)
        ->and(Employee::withoutGlobalScopes()->where('optigate_user_id', $portalId)->first()->nama_employee)
        ->toBe('Second Name');
});

it('never creates application accounts during the sync', function () {
    fakePortalUsers([
        ['id' => '019fdfb7-1', 'name' => 'No Account', 'email' => 'noaccount@tataoptima.com', 'nik' => 'NIK-2'],
    ]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertSuccessful();

    expect(User::query()->where('email', 'noaccount@tataoptima.com')->exists())->toBeFalse()
        ->and(Employee::withoutGlobalScopes()->where('optigate_user_id', '019fdfb7-1')->first()->user)->toBeNull();
});

it('fails gracefully when the portal is unreachable', function () {
    Http::fake(['*/api/users*' => Http::response(null, 500)]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertFailed();
});

it('maps the portal department_id to the local department on each employee', function () {
    Tenant::forgetCurrent();

    $department = Department::withoutGlobalScopes()->make([
        'optigate_department_id' => '2',
        'kode_department' => 'IT',
        'nama_department' => 'Information Technology',
    ]);
    $department->tenant_id = $this->hq->id;
    $department->save();

    fakePortalUsers([
        ['id' => 'emp-1', 'name' => 'Sari', 'department_id' => 2],
        ['id' => 'emp-2', 'name' => 'Tanpa Dept'],
        ['id' => 'emp-3', 'name' => 'Dept Tak Dikenal', 'department_id' => 42],
    ]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertSuccessful();

    $known = Employee::withoutGlobalScopes()->where('optigate_user_id', 'emp-1')->first();
    $blank = Employee::withoutGlobalScopes()->where('optigate_user_id', 'emp-2')->first();
    $unknown = Employee::withoutGlobalScopes()->where('optigate_user_id', 'emp-3')->first();

    expect($known->department_id)->toBe($department->id)
        ->and($blank->department_id)->toBeNull()
        // department portal yang belum ikut sync department tidak menggantung.
        ->and($unknown->department_id)->toBeNull()
        ->and(Department::withoutGlobalScopes()->find($known->department_id)->is($department))->toBeTrue();
});

it('maps portal position_id to local position and resolves division from position', function () {
    Tenant::forgetCurrent();

    // Create a division first.
    $division = \App\Models\Division::withoutGlobalScopes()->make([
        'optigate_division_id' => 'div-1',
        'kode_division' => 'IT-DEV',
        'nama_division' => 'Software Engineering',
    ]);
    $division->tenant_id = $this->hq->id;
    $division->save();

    // Create a position linked to that division.
    $position = Position::withoutGlobalScopes()->make([
        'optigate_position_id' => 'pos-1',
        'nama_position' => 'Staff Programmer',
        'division_id' => $division->id,
    ]);
    $position->tenant_id = $this->hq->id;
    $position->save();

    fakePortalUsers([
        ['id' => 'emp-1', 'name' => 'Sari', 'position_id' => 'pos-1'],
    ]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertSuccessful();

    $employee = Employee::withoutGlobalScopes()->where('optigate_user_id', 'emp-1')->first();

    expect($employee->position_id)->toBe($position->id)
        ->and($employee->division_id)->toBe($division->id);
});

it('clears department_id when the portal no longer assigns a department', function () {
    Tenant::forgetCurrent();

    $department = Department::withoutGlobalScopes()->make([
        'optigate_department_id' => '2',
        'kode_department' => 'IT',
    ]);
    $department->tenant_id = $this->hq->id;
    $department->save();

    $employee = Employee::withoutGlobalScopes()->make(['nama_employee' => 'Sari']);
    $employee->tenant_id = $this->hq->id;
    $employee->optigate_user_id = 'emp-1';
    $employee->department_id = $department->id;
    $employee->save();

    fakePortalUsers([
        ['id' => 'emp-1', 'name' => 'Sari'],
    ]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertSuccessful();

    expect($employee->refresh()->department_id)->toBeNull();
});
