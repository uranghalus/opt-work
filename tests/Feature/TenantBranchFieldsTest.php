<?php

use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Database\QueryException;

test('branch identity lives in real columns, not the data json blob', function () {
    $tenant = Tenant::factory()->create(['name' => 'Plant 7', 'code' => 'P07']);

    expect($tenant->name)->toBe('Plant 7')
        ->and($tenant->code)->toBe('P07')
        ->and($tenant->is_active)->toBeTrue()
        ->and($tenant->data)->toBeNull()
        ->and(Tenant::query()->where('code', 'P07')->exists())->toBeTrue();
});

test('the seeder persists branch names and codes', function () {
    (new TenantSeeder)->run();

    $hq = Tenant::query()->findOrFail('hq');

    expect($hq->name)->toBe('Head Office')
        ->and($hq->code)->toBe('HQ')
        ->and(Tenant::query()->findOrFail('plant-1')->name)->toBe('Plant 1');
});

test('the seeder is safe to run twice', function () {
    (new TenantSeeder)->run();
    (new TenantSeeder)->run();

    expect(Tenant::query()->count())->toBe(2);
});

test('label falls back to the slug when a name is missing', function () {
    $tenant = new Tenant(['id' => 'ghost']);
    $tenant->name = null;

    expect($tenant->label())->toBe('ghost');
});

test('branch codes are unique', function () {
    Tenant::factory()->create(['code' => 'HQ']);

    expect(fn () => Tenant::factory()->create(['code' => 'HQ']))
        ->toThrow(QueryException::class);
});

test('a branch can be archived without being deleted', function () {
    $tenant = Tenant::factory()->inactive()->create();

    expect($tenant->fresh()->is_active)->toBeFalse()
        ->and(Tenant::query()->whereKey($tenant->getKey())->exists())->toBeTrue();
});
