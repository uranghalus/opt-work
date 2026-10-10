<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

/**
 * An Optigate API company-list response envelope.
 *
 * @param  array<int, array<string, mixed>>  $companies
 */
function optigateCompaniesResponse(array $companies, ?int $total = null): array
{
    return [
        'success' => true,
        'data' => $companies,
        'meta' => [
            'current_page' => 1,
            'per_page' => count($companies),
            'total' => $total ?? count($companies),
        ],
    ];
}

beforeEach(function () {
    config([
        'services.optigate_portal.url' => 'https://gate.test',
        'services.optigate_portal.token' => 'test-token',
        'services.optigate_portal.verify' => true,
    ]);
});

it('creates tenants from optigate companies', function () {
    Http::fake([
        'gate.test/api/companies*' => Http::response(optigateCompaniesResponse([
            ['id' => 1, 'code' => 'TOP', 'name' => 'PT Tata Optima Property'],
            ['id' => 2, 'code' => 'DM', 'name' => 'PT Dutamall'],
        ])),
    ]);

    $this->artisan('app:sync-tenants')->assertSuccessful();

    expect(Tenant::query()->count())->toBe(2);

    $top = Tenant::query()->where('optigate_company_id', 1)->sole();

    expect($top->code)->toBe('top')
        ->and($top->name)->toBe('PT Tata Optima Property')
        ->and($top->is_active)->toBeTrue()
        ->and($top->deactivated_at)->toBeNull();
});

it('updates changed company data without duplicating tenants', function () {
    Tenant::factory()->create(['optigate_company_id' => 1, 'code' => 'top', 'name' => 'Old Name']);

    Http::fake([
        'gate.test/api/companies*' => Http::response(optigateCompaniesResponse([
            ['id' => 1, 'code' => 'TOP', 'name' => 'PT Tata Optima Property'],
        ])),
    ]);

    $this->artisan('app:sync-tenants')->assertSuccessful();

    expect(Tenant::query()->count())->toBe(1)
        ->and(Tenant::query()->where('optigate_company_id', 1)->sole()->name)->toBe('PT Tata Optima Property');
});

it('deactivates tenants whose company disappeared from the api', function () {
    Tenant::factory()->create(['optigate_company_id' => 1, 'code' => 'gone', 'name' => 'Gone Inc', 'is_active' => true]);
    // No optigate_company_id: not managed by the sync, must stay untouched.
    Tenant::factory()->create(['code' => 'manual', 'name' => 'Not API Managed', 'is_active' => true, 'optigate_company_id' => null]);

    Http::fake([
        'gate.test/api/companies*' => Http::response(optigateCompaniesResponse([
            ['id' => 2, 'code' => 'DM', 'name' => 'PT Dutamall'],
        ])),
    ]);

    $this->artisan('app:sync-tenants')->assertSuccessful();

    $gone = Tenant::query()->where('code', 'gone')->sole();
    $manual = Tenant::query()->where('code', 'manual')->sole();

    // API-managed tenant is deactivated, never deleted.
    expect($gone->is_active)->toBeFalse()
        ->and($gone->deactivated_at)->not->toBeNull()
        ->and(Tenant::query()->count())->toBe(3)
        // A tenant without an optigate_company_id is not the sync's business.
        ->and($manual->is_active)->toBeTrue()
        ->and($manual->deactivated_at)->toBeNull();
});

it('deactivates every api managed tenant when the api returns no companies', function () {
    Tenant::factory()->create(['optigate_company_id' => 1, 'code' => 'top', 'name' => 'Top', 'is_active' => true]);

    Http::fake([
        'gate.test/api/companies*' => Http::response(optigateCompaniesResponse([])),
    ]);

    $this->artisan('app:sync-tenants')->assertSuccessful();

    expect(Tenant::query()->where('code', 'top')->sole()->is_active)->toBeFalse();
});

it('reactivates a tenant when its company reappears in the api', function () {
    Tenant::factory()->create([
        'optigate_company_id' => 1,
        'code' => 'top',
        'name' => 'Top',
        'is_active' => false,
        'deactivated_at' => now(),
    ]);

    Http::fake([
        'gate.test/api/companies*' => Http::response(optigateCompaniesResponse([
            ['id' => 1, 'code' => 'TOP', 'name' => 'PT Tata Optima Property'],
        ])),
    ]);

    $this->artisan('app:sync-tenants')->assertSuccessful();

    $top = Tenant::query()->where('optigate_company_id', 1)->sole();

    expect($top->is_active)->toBeTrue()
        ->and($top->deactivated_at)->toBeNull()
        ->and($top->name)->toBe('PT Tata Optima Property');
});

it('is idempotent: running the sync twice produces no duplicates', function () {
    Http::fake([
        'gate.test/api/companies*' => Http::response(optigateCompaniesResponse([
            ['id' => 1, 'code' => 'TOP', 'name' => 'PT Tata Optima Property'],
            ['id' => 2, 'code' => 'DM', 'name' => 'PT Dutamall'],
        ])),
    ]);

    $this->artisan('app:sync-tenants')->assertSuccessful();
    $this->artisan('app:sync-tenants')->assertSuccessful();

    expect(Tenant::query()->count())->toBe(2);
});

it('follows pagination until every company is fetched', function () {
    Http::fake([
        'gate.test/api/companies*' => Http::sequence()
            ->push(optigateCompaniesResponse([
                ['id' => 1, 'code' => 'TOP', 'name' => 'PT Tata Optima Property'],
                ['id' => 2, 'code' => 'DM', 'name' => 'PT Dutamall'],
            ], total: 3))
            ->push(optigateCompaniesResponse([
                ['id' => 3, 'code' => 'SEC', 'name' => 'PT Sekretariat'],
            ], total: 3)),
    ]);

    $this->artisan('app:sync-tenants')->assertSuccessful();

    expect(Tenant::query()->count())->toBe(3)
        ->and(Http::recorded())->toHaveCount(2);
});

it('fails fast on unauthorized without creating tenants', function () {
    Http::fake([
        'gate.test/api/companies*' => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    $this->artisan('app:sync-tenants')->assertFailed();

    expect(Tenant::query()->count())->toBe(0);
});

it('retries server errors before succeeding', function () {
    Http::fake([
        'gate.test/api/companies*' => Http::sequence()
            ->push(['message' => 'Server error'], 500)
            ->push(optigateCompaniesResponse([
                ['id' => 1, 'code' => 'TOP', 'name' => 'PT Tata Optima Property'],
            ])),
    ]);

    $this->artisan('app:sync-tenants')->assertSuccessful();

    expect(Tenant::query()->count())->toBe(1)
        ->and(Http::recorded())->toHaveCount(2);
});
