<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Ticket 05 — the branch switcher.
 *
 * The switcher itself is client-side, so these tests cover the two things it is not
 * allowed to get wrong: what the payload exposes, and whether the server refuses a
 * branch the user may not operate.
 *
 * Hiding a branch from the list is not enforcement. A user who hand-types
 * `/plant-1/work-orders` must still be turned away by `EnsureTenantAccess`.
 */
beforeEach(function () {
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    createTestTenant([
        'optigate_company_id' => 2,
        'code' => 'plant-1',
        'name' => 'Plant 1',
    ]);
});

/** Reads the shared `tenants` prop from an Inertia response. */
function switcherPayload(TestResponse $response): array
{
    return $response->viewData('page')['props']['tenants'];
}

test('a branch user is offered only their own branch', function () {
    $hq = Tenant::where('code', 'hq')->first();
    $response = $this->actingAs(User::factory()->create(['tenant_id' => $hq->id]))
        ->get('/hq/departments')
        ->assertOk();

    expect(array_column(switcherPayload($response), 'code'))->toBe(['hq']);
});

test('a super admin is offered every branch with its display fields', function () {
    $response = $this->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/hq/departments')
        ->assertOk();

    expect(switcherPayload($response))->toHaveCount(2)
        ->and(switcherPayload($response)[0])->toHaveKeys(['id', 'name', 'code', 'is_active'])
        ->and(switcherPayload($response)[0]['name'])->toBeString()->not->toBeEmpty();
});

test('the payload reports whether a branch is active so the switcher can hide it', function () {
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $plant1->update(['is_active' => false]);

    $response = $this->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/hq/departments')
        ->assertOk();

    $byCode = collect(switcherPayload($response))->keyBy('code');

    expect($byCode['hq']['is_active'])->toBeTrue()
        ->and($byCode['plant-1']['is_active'])->toBeFalse();
});

test('an archived branch is still reachable by direct URL', function () {
    // Archiving hides a branch from the switcher; it must not break historical links.
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $plant1->update(['is_active' => false]);

    $this->actingAs(makeSuperAdmin(User::factory()->create()))
        ->get('/plant-1/departments')
        ->assertOk();
});

test('a branch user is refused another branch URL even by hand', function () {
    // The invariant the switcher cannot enforce on its own.
    $hq = Tenant::where('code', 'hq')->first();
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $this->actingAs(User::factory()->create(['tenant_id' => $hq->id]))
        ->get('/plant-1/departments')
        ->assertForbidden();
});

test('a branch user is refused another branch write even by hand', function () {
    $hq = Tenant::where('code', 'hq')->first();
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $this->actingAs(User::factory()->create(['tenant_id' => $hq->id]))
        ->post('/plant-1/departments', ['kode_department' => 'X1', 'nama_department' => 'Sneaky'])
        ->assertForbidden();

    // The row must not exist in the other branch either.
    initTenant('plant-1');
    expect(Department::query()->where('kode_department', 'X1')->exists())->toBeFalse();

    Tenant::forgetCurrent();
});

test('a user with no home branch is refused everywhere', function () {
    $hq = Tenant::where('code', 'hq')->first();
    $this->actingAs(User::factory()->create(['tenant_id' => null]))
        ->get('/hq/departments')
        ->assertForbidden();
});

test('a super admin may move between branches freely', function () {
    $user = makeSuperAdmin(User::factory()->create());

    $this->actingAs($user)->get('/hq/departments')->assertOk();
    $this->actingAs($user)->get('/plant-1/departments')->assertOk();
});