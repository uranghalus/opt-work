<?php

use App\Models\Employee;
use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Seeder;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => '1',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    $this->bm = createTestTenant([
        'optigate_company_id' => '2',
        'code' => 'bm',
        'name' => 'Big Mall Samarinda',
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

function switchingPost(array $payload = [])
{
    return test()->actingAs(test()->admin)->post('/tenant/switching', $payload);
}

it('switches to another tenant and stays on the employees list', function () {
    $this->admin = makeSuperAdmin(createUser());

    switchingPost([
        'tenant' => 'bm',
        'route' => ['name' => 'employees.index', 'params' => []],
    ])->assertRedirect('/bm/employees');

    expect(Tenant::current()?->code)->toBe('bm');
});

it('maps a record-level URL to the parent list page of the target tenant', function () {
    $this->admin = makeSuperAdmin(createUser());

    $employee = Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'John Doe',
    ]);

    switchingPost([
        'tenant' => 'bm',
        'route' => ['name' => 'employees.show', 'params' => ['employee' => $employee->id]],
    ])->assertRedirect('/bm/employees');
});

it('drops the query string when switching', function () {
    $this->admin = makeSuperAdmin(createUser());

    $response = $this->actingAs($this->admin)
        ->post('/tenant/switching?page=2&search=x', [
            'tenant' => 'bm',
            'route' => ['name' => 'employees.index', 'params' => []],
        ]);

    expect($response->headers->get('Location'))->not->toContain('page=2')
        ->and($response->headers->get('Location'))->not->toContain('search=');
});

it('falls back to the dashboard with a message when the current page is not a tenant page', function () {
    $this->admin = makeSuperAdmin(createUser());

    switchingPost([
        'tenant' => 'bm',
        'route' => ['name' => 'settings.roles', 'params' => []],
    ])->assertRedirect('/bm/dashboard');

    expect(session('error'))->toContain('halaman');
});

it('forbids a normal user from switching to another tenant', function () {
    $this->admin = createUser(['tenant_id' => $this->hq->id]);

    switchingPost([
        'tenant' => 'bm',
        'route' => ['name' => 'employees.index', 'params' => []],
    ])->assertForbidden();
});

it('returns 404 when the target tenant code is unknown', function () {
    $this->admin = makeSuperAdmin(createUser());

    switchingPost([
        'tenant' => 'no-such-tenant',
        'route' => ['name' => 'employees.index', 'params' => []],
    ])->assertNotFound();
});

it('shares the current route name and parameters with the frontend', function () {
    $this->admin = makeSuperAdmin(createUser());

    $this->actingAs($this->admin)->get('/hq/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('route.name', 'dashboard')
            ->where('route.params.tenant', 'hq'));
});
