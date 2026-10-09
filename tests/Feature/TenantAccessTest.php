<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantAccess;

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

afterEach(function () {
    Tenant::forgetCurrent();
});

test('a branch user may operate only their own branch', function () {
    $access = app(TenantAccess::class);
    $hq = Tenant::where('code', 'hq')->first();
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $user = User::factory()->create(['tenant_id' => $hq->id]);

    expect($access->canOperate($user, $hq->code))->toBeTrue()
        ->and($access->canOperate($user, $plant1->code))->toBeFalse();
});

test('a super admin may operate any branch and needs no home branch', function () {
    $access = app(TenantAccess::class);
    $hq = Tenant::where('code', 'hq')->first();
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $user = makeSuperAdmin(User::factory()->create());

    expect($user->tenant_id)->toBeNull()
        ->and($access->canOperate($user, $hq->code))->toBeTrue()
        ->and($access->canOperate($user, $plant1->code))->toBeTrue();
});

test('a user without a home branch may operate nothing', function () {
    $access = app(TenantAccess::class);
    $hq = Tenant::where('code', 'hq')->first();
    $user = User::factory()->create();

    expect($access->canOperate($user, $hq->code))->toBeFalse()
        ->and($access->operableTenants($user))->toHaveCount(0);
});

test('operating a null branch is never allowed', function () {
    $access = app(TenantAccess::class);

    expect($access->canOperate(makeSuperAdmin(User::factory()->create()), null))->toBeFalse();
});

test('operable tenants are the same set canOperate enforces', function () {
    $access = app(TenantAccess::class);
    $hq = Tenant::where('code', 'hq')->first();
    $plant1 = Tenant::where('code', 'plant-1')->first();
    $branchUser = User::factory()->create(['tenant_id' => $hq->id]);
    $ids = $access->operableTenants($branchUser)->map(fn (Tenant $t) => $t->getTenantKey())->all();

    expect($ids)->toBe([$hq->id])
        ->and($access->canOperate($branchUser, $plant1->code))->toBeFalse();

    $platformUser = makeSuperAdmin(User::factory()->create());
    expect($access->operableTenants($platformUser))->toHaveCount(2);
});

test('only a super admin may manage branch records', function () {
    $access = app(TenantAccess::class);
    $hq = Tenant::where('code', 'hq')->first();

    expect($access->canManageTenants(User::factory()->create(['tenant_id' => $hq->id])))->toBeFalse()
        ->and($access->canManageTenants(makeSuperAdmin(User::factory()->create())))->toBeTrue();
});