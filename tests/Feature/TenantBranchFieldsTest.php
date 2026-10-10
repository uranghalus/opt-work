<?php

use App\Models\Tenant;
use Illuminate\Database\QueryException;

test('tenant has correct structure with optigate_company_id, code, name, is_active, deactivated_at', function () {
    $tenant = createTestTenant([
        'optigate_company_id' => '999',
        'code' => 'test-tenant',
        'name' => 'Test Tenant',
    ]);

    expect($tenant->optigate_company_id)->toBe('999')
        ->and($tenant->code)->toBe('test-tenant')
        ->and($tenant->name)->toBe('Test Tenant')
        ->and($tenant->is_active)->toBeTrue()
        ->and($tenant->deactivated_at)->toBeNull()
        ->and($tenant->id)->not->toBeNull() // ULID
        ->and(Tenant::query()->where('code', 'test-tenant')->exists())->toBeTrue();
});

test('optigate_company_id is unique', function () {
    createTestTenant([
        'optigate_company_id' => '1',
        'code' => 'tenant-1',
        'name' => 'Tenant 1',
    ]);

    expect(fn () => createTestTenant([
        'optigate_company_id' => '1', // duplicate
        'code' => 'tenant-2',
        'name' => 'Tenant 2',
    ]))->toThrow(QueryException::class);
});

test('code is unique', function () {
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'unique-code',
        'name' => 'Tenant 1',
    ]);

    expect(fn () => createTestTenant([
        'optigate_company_id' => 2,
        'code' => 'unique-code', // duplicate
        'name' => 'Tenant 2',
    ]))->toThrow(QueryException::class);
});

test('a tenant can be deactivated without being deleted', function () {
    $tenant = createTestTenant([
        'optigate_company_id' => '1',
        'code' => 'tenant-1',
        'name' => 'Tenant 1',
    ]);

    $tenant->update(['is_active' => false, 'deactivated_at' => now()]);

    expect($tenant->fresh()->is_active)->toBeFalse()
        ->and($tenant->fresh()->deactivated_at)->not->toBeNull()
        ->and(Tenant::query()->where('id', $tenant->id)->exists())->toBeTrue();
});

test('tenant route binding uses code (slug)', function () {
    $tenant = createTestTenant([
        'optigate_company_id' => '1',
        'code' => 'my-tenant',
        'name' => 'My Tenant',
    ]);

    // Route model binding should resolve by code
    $resolved = Tenant::where('code', 'my-tenant')->first();
    expect($resolved->id)->toBe($tenant->id);
});