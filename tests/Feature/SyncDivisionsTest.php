<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Tenant;
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
 * An Optigate API division-list response envelope.
 *
 * @param  array<int, array<string, mixed>>  $divisions
 */
function fakePortalDivisions(array $divisions = []): void
{
    Http::fake([
        '*/api/companies/*/divisions*' => Http::response([
            'success' => true,
            'data' => $divisions,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => count($divisions),
                'total' => count($divisions),
            ],
        ]),
    ]);
}

it('creates divisions from the portal payload keyed by the portal division id', function () {
    Tenant::forgetCurrent();

    fakePortalDivisions([
        ['id' => 1, 'code' => 'SEC', 'name' => 'Sekretariat'],
        ['id' => 2, 'code' => 'AST', 'name' => 'Asset Management'],
    ]);

    $this->artisan('app:sync-divisions', ['--tenant' => $this->hq->id])->assertSuccessful();

    $division = Division::withoutGlobalScopes()->where('optigate_division_id', '2')->first();

    expect($division)->not->toBeNull()
        ->and($division->tenant_id)->toBe($this->hq->id)
        ->and($division->kode_division)->toBe('AST')
        ->and($division->nama_division)->toBe('Asset Management')
        ->and($division->is_active)->toBeTrue();
});

it('maps the portal parent department and keeps direct divisions explicit', function () {
    Tenant::forgetCurrent();

    $department = Department::withoutGlobalScopes()->make([
        'kode_department' => 'IT',
        'nama_department' => 'Information Technology',
        'optigate_department_id' => '10',
    ]);
    $department->tenant_id = $this->hq->id;
    $department->save();

    fakePortalDivisions([
        ['id' => 1, 'code' => 'APP', 'name' => 'Application', 'department_id' => 10, 'is_direct' => false],
        ['id' => 2, 'code' => 'DIR', 'name' => 'Direct', 'department_id' => null, 'is_direct' => true],
    ]);

    $this->artisan('app:sync-divisions', ['--tenant' => $this->hq->id])->assertSuccessful();

    $application = Division::withoutGlobalScopes()->where('optigate_division_id', '1')->first();
    $direct = Division::withoutGlobalScopes()->where('optigate_division_id', '2')->first();

    expect($application)->not->toBeNull()
        ->and($application->department_id)->toBe($department->id)
        ->and($application->is_direct)->toBeFalse()
        ->and($direct)->not->toBeNull()
        ->and($direct->department_id)->toBeNull()
        ->and($direct->is_direct)->toBeTrue();
});

it('scopes the portal request to the tenant optigate company', function () {
    Tenant::forgetCurrent();

    fakePortalDivisions();

    $this->artisan('app:sync-divisions', ['--tenant' => $this->hq->id])->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/companies/1/divisions');
    });
});

it('is idempotent: rerunning the sync updates instead of duplicating', function () {
    Tenant::forgetCurrent();

    $portalId = 7;

    Http::fake([
        '*/api/companies/*/divisions*' => Http::sequence([
            ['data' => [['id' => $portalId, 'code' => 'DIV', 'name' => 'First Name']], 'meta' => ['total' => 1, 'last_page' => 1]],
            ['data' => [['id' => $portalId, 'code' => 'DIV', 'name' => 'Second Name']], 'meta' => ['total' => 1, 'last_page' => 1]],
        ]),
    ]);

    $this->artisan('app:sync-divisions', ['--tenant' => $this->hq->id])->assertSuccessful();
    $this->artisan('app:sync-divisions', ['--tenant' => $this->hq->id])->assertSuccessful();

    $divisions = Division::withoutGlobalScopes()->where('optigate_division_id', (string) $portalId)->get();

    expect($divisions)->toHaveCount(1)
        ->and($divisions->first()->nama_division)->toBe('Second Name');
});

it('syncs only the tenant matching the option', function () {
    Tenant::forgetCurrent();

    Http::fake([
        '*/api/companies/*/divisions*' => Http::sequence([
            Http::response(['data' => [['id' => 1, 'code' => 'HQ-DIV', 'name' => 'HQ Division']], 'meta' => ['total' => 1, 'last_page' => 1]]),
            Http::response(['data' => [['id' => 2, 'code' => 'BR-DIV', 'name' => 'Branch Division']], 'meta' => ['total' => 1, 'last_page' => 1]]),
        ]),
    ]);

    $branch = createTestTenant([
        'optigate_company_id' => '2',
        'code' => 'branch',
        'name' => 'Branch',
    ]);

    $this->artisan('app:sync-divisions', ['--tenant' => $this->hq->id])->assertSuccessful();

    expect(Division::withoutGlobalScopes()->where('tenant_id', $this->hq->id)->where('optigate_division_id', '1')->exists())->toBeTrue()
        ->and(Division::withoutGlobalScopes()->where('tenant_id', $this->hq->id)->where('optigate_division_id', '2')->exists())->toBeFalse()
        ->and(Division::withoutGlobalScopes()->where('tenant_id', $branch->id)->where('optigate_division_id', '2')->exists())->toBeFalse();
});

it('deactivates divisions that disappeared from the portal', function () {
    Tenant::forgetCurrent();

    $stale = Division::withoutGlobalScopes()->make([
        'optigate_division_id' => '99',
        'kode_division' => 'OLD',
        'nama_division' => 'Old Division',
        'is_active' => true,
    ]);
    $stale->tenant_id = $this->hq->id;
    $stale->save();

    fakePortalDivisions([
        ['id' => 1, 'code' => 'SEC', 'name' => 'Sekretariat'],
    ]);

    $this->artisan('app:sync-divisions', ['--tenant' => $this->hq->id])->assertSuccessful();

    $stale = Division::withoutGlobalScopes()->where('optigate_division_id', '99')->first();

    expect($stale)->not->toBeNull()
        ->and($stale->kode_division)->toBe('OLD')
        ->and($stale->is_active)->toBeFalse()
        ->and(Division::withoutGlobalScopes()->where('tenant_id', $this->hq->id)->where('optigate_division_id', '1')->first()?->is_active)->toBeTrue();
});

it('persists last_division_synced_at on the tenant after a successful sync', function () {
    Tenant::forgetCurrent();

    fakePortalDivisions([
        ['id' => 1, 'code' => 'SEC', 'name' => 'Sekretariat'],
    ]);

    $this->hq->update(['last_division_synced_at' => null]);

    $this->artisan('app:sync-divisions', ['--tenant' => $this->hq->id])->assertSuccessful();

    expect($this->hq->fresh()->last_division_synced_at)->not->toBeNull();
});

it('preserves existing data when sync fails', function () {
    Tenant::forgetCurrent();

    $existing = Division::withoutGlobalScopes()->make([
        'optigate_division_id' => '10',
        'kode_division' => 'PREVIOUS',
        'nama_division' => 'Previous Name',
        'is_active' => true,
    ]);
    $existing->tenant_id = $this->hq->id;
    $existing->save();

    $this->hq->update(['last_division_synced_at' => null]);

    Http::fake([
        '*/api/companies/*/divisions*' => Http::response([
            'data' => [['id' => 10, 'code' => 'NEW', 'name' => 'New Name']],
            'meta' => ['total' => 1, 'last_page' => 1],
        ]),
    ]);

    // Use a non-existent tenant id — command returns SUCCESS but writes nothing.
    $this->artisan('app:sync-divisions', ['--tenant' => 'nonexistent'])->assertSuccessful();

    // The existing division should be untouched.
    $fresh = Division::withoutGlobalScopes()->where('optigate_division_id', '10')->first();
    expect($fresh)->not->toBeNull()
        ->and($fresh->kode_division)->toBe('PREVIOUS')
        ->and($fresh->nama_division)->toBe('Previous Name')
        ->and($fresh->is_active)->toBeTrue()
        ->and($this->hq->fresh()->last_division_synced_at)->toBeNull();
});

it('handles pagination with last_page check', function () {
    Tenant::forgetCurrent();

    Http::fake([
        '*/api/companies/*/divisions*' => Http::sequence([
            Http::response(['data' => [['id' => 1, 'code' => 'DIV1', 'name' => 'Division 1']], 'meta' => ['total' => 2, 'last_page' => 2]]),
            Http::response(['data' => [['id' => 2, 'code' => 'DIV2', 'name' => 'Division 2']], 'meta' => ['total' => 2, 'last_page' => 2]]),
        ]),
    ]);

    $this->artisan('app:sync-divisions', ['--tenant' => $this->hq->id])->assertSuccessful();

    expect(Division::withoutGlobalScopes()->where('tenant_id', $this->hq->id)->count())->toBe(2);
});
