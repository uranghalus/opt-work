<?php

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

function fakePortalUsers(array $users = [])
{
    Http::fake([
        '*/api/users' => Http::response(['data' => $users]),
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

it('is idempotent: rerunning the sync updates instead of duplicating', function () {
    $portalId = '019fdfb7-4959-7b3b-9a99-bcfd4e963fa1';

    // One fake serving different payloads per call: a second Http::fake()
    // call would not replace the stubs mid-test.
    Http::fake([
        '*/api/users' => Http::sequence([
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
    Http::fake(['*/api/users' => Http::response(null, 500)]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertFailed();
});
