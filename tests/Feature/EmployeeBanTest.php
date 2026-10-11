<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

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

    (new class extends Seeder
    {
        public function run(): void
        {
            (new RoleAndPermissionSeeder)->run();
        }
    })->run();
});

afterEach(function () {
    Tenant::forgetCurrent();
});

function banPost(Employee $employee, string $action = 'ban', ?User $as = null)
{
    $actor = $as ?? test()->admin;

    return test()->actingAs($actor)->post(
        route("employees.{$action}", ['tenant' => 'hq', 'employee' => $employee->id]),
    );
}

it('lets the superadmin ban and unban an employee', function () {
    initTenant('hq');
    $this->admin = makeSuperAdmin(createUser());
    $employee = Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'John Doe',
    ]);

    banPost($employee)->assertRedirect();

    $employee->refresh();
    expect($employee->banned_at)->not->toBeNull()
        ->and($employee->isBanned())->toBeTrue();

    banPost($employee, 'unban')->assertRedirect();

    expect($employee->refresh()->banned_at)->toBeNull()
        ->and($employee->isBanned())->toBeFalse();
});

it('names the ban moment, not just a boolean', function () {
    initTenant('hq');
    $this->admin = makeSuperAdmin(createUser());
    $employee = Employee::create([
        'tenant_id' => $this->hq->id,
        'nama_employee' => 'John Doe',
    ]);

    banPost($employee)->assertRedirect();

    expect($employee->refresh()->banned_at->toDateString())->toBe(now()->toDateString());
});

it('forbids a normal user from banning', function () {
    initTenant('hq');
    $this->admin = createUser(['tenant_id' => $this->hq->id]);
    $employee = Employee::create([
        'tenant_id' => $this->hq->id,
        'nama_employee' => 'John Doe',
    ]);

    banPost($employee)->assertForbidden();
    expect($employee->refresh()->banned_at)->toBeNull();
});

it('returns 404 for an unknown employee', function () {
    initTenant('hq');
    $this->admin = makeSuperAdmin(createUser());

    $this->actingAs($this->admin)
        ->post(route('employees.ban', ['tenant' => 'hq', 'employee' => '01nope']))
        ->assertNotFound();
});

it('exposes the account status and ban state in the index response', function () {
    initTenant('hq');
    $this->admin = makeSuperAdmin(createUser());

    $withAccount = Employee::create([
        'tenant_id' => $this->hq->id,
        'nama_employee' => 'Has Account',
        'email' => 'has@example.com',
    ]);
    User::factory()->create(['employee_id' => $withAccount->id]);

    $withoutAccount = Employee::create([
        'tenant_id' => $this->hq->id,
        'nama_employee' => 'No Account',
    ]);

    $response = $this->actingAs($this->admin)->get('/hq/employees');

    $response->assertOk()->assertInertia(function ($page) {
        $page->component('employees/index')
            ->has('employees.data', 2)
            ->where('employees.data.0.has_account', fn ($v) => is_bool($v))
            ->where('employees.data.0.is_banned', false);
    });
});

it('keeps the ban flag when the portal sync runs', function () {
    initTenant('hq');
    $this->admin = makeSuperAdmin(createUser());
    $employee = Employee::create([
        'tenant_id' => $this->hq->id,
        'nama_employee' => 'John Doe',
        'banned_at' => now(),
    ]);
    $portalId = '019fdfb7-4959-7b3b-9a99-bcfd4e963fa1';
    $employee->forceFill(['optigate_user_id' => $portalId])->save();

    config([
        'services.optigate_portal.url' => 'https://gate.test',
        'services.optigate_portal.token' => 'test-token',
        'services.optigate_portal.verify' => true,
    ]);

    Http::fake([
        '*/api/users*' => Http::response([
            'data' => [
                [
                    'id' => $portalId,
                    'name' => 'John Doe (portal)',
                    'nik' => 'NIK-001',
                    'email' => 'john@portal.test',
                ],
            ],
        ]),
    ]);

    $this->artisan('app:sync-employees', ['--tenant' => $this->hq->id])->assertSuccessful();

    $employee->refresh();
    expect($employee->banned_at)->not->toBeNull()
        ->and($employee->nama_employee)->toBe('John Doe (portal)');
});
