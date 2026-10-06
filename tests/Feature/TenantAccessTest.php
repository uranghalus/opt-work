<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantAccess;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    // users.tenant_id is a real FK, so the branches must exist first.
    Tenant::query()->firstOrCreate(['id' => 'hq']);
    Tenant::query()->firstOrCreate(['id' => 'plant-1']);
});

afterEach(function () {
    Tenancy::end();
});

test('a branch user may operate only their own branch', function () {
    $access = app(TenantAccess::class);
    $user = User::factory()->create(['tenant_id' => 'hq']);

    expect($access->canOperate($user, 'hq'))->toBeTrue()
        ->and($access->canOperate($user, 'plant-1'))->toBeFalse();
});

test('a super admin may operate any branch and needs no home branch', function () {
    $access = app(TenantAccess::class);
    $user = makeSuperAdmin(User::factory()->create());

    expect($user->tenant_id)->toBeNull()
        ->and($access->canOperate($user, 'hq'))->toBeTrue()
        ->and($access->canOperate($user, 'plant-1'))->toBeTrue();
});

test('a user without a home branch may operate nothing', function () {
    $access = app(TenantAccess::class);
    $user = User::factory()->create();

    expect($access->canOperate($user, 'hq'))->toBeFalse()
        ->and($access->operableTenants($user))->toHaveCount(0);
});

test('operating a null branch is never allowed', function () {
    $access = app(TenantAccess::class);

    expect($access->canOperate(makeSuperAdmin(User::factory()->create()), null))->toBeFalse();
});

test('operable tenants are the same set canOperate enforces', function () {
    Tenant::query()->firstOrCreate(['id' => 'hq']);
    Tenant::query()->firstOrCreate(['id' => 'plant-1']);
    $access = app(TenantAccess::class);

    $branchUser = User::factory()->create(['tenant_id' => 'hq']);
    $ids = $access->operableTenants($branchUser)->map(fn (Tenant $t) => $t->getTenantKey())->all();

    expect($ids)->toBe(['hq'])
        ->and($access->canOperate($branchUser, 'plant-1'))->toBeFalse();

    $platformUser = makeSuperAdmin(User::factory()->create());
    expect($access->operableTenants($platformUser))->toHaveCount(2);
});

test('only a super admin may manage branch records', function () {
    $access = app(TenantAccess::class);

    expect($access->canManageTenants(User::factory()->create(['tenant_id' => 'hq'])))->toBeFalse()
        ->and($access->canManageTenants(makeSuperAdmin(User::factory()->create())))->toBeTrue();
});
