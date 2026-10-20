<?php

use App\Models\Department;
use App\Models\Tenant;

it('edit update destroy resolve manually per tenant', function () {
    $admin = makeSuperAdmin(createUser());
    $tenant = createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);

    $department = Department::withoutGlobalScopes()->make([
        'kode_department' => 'IT',
        'nama_department' => 'IT',
    ]);
    $department->tenant_id = $tenant->id;
    $department->save();

    $this->actingAs($admin)->get('/hq/departments/'.$department->getKey().'/edit')->assertOk();

    $this
        ->actingAs($admin)
        ->put('/hq/departments/'.$department->getKey(), [
            'kode_department' => 'IT2',
            'nama_department' => 'IT Renamed',
        ])
        ->assertRedirect();

    expect($department->fresh()->nama_department)->toBe('IT Renamed');

    $this
        ->actingAs($admin)
        ->delete('/hq/departments/'.$department->getKey())
        ->assertRedirect();

    expect(Department::withoutGlobalScopes()->count())->toBe(0);
});

it('foreign tenant department is 404 on edit and update and destroy', function () {
    createTestTenant([
        'optigate_company_id' => 2,
        'code' => 'plant-1',
        'name' => 'Plant 1',
    ]);
    $admin = makeSuperAdmin(createUser());
    $hq = createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    Tenant::where('code', 'plant-1')->first()->makeCurrent();

    $department = Department::withoutGlobalScopes()->make([
        'kode_department' => 'IT',
        'nama_department' => 'IT',
    ]);
    $department->tenant_id = $hq->id;
    $department->save();

    $this->actingAs($admin)->get('/plant-1/departments/'.$department->getKey().'/edit')->assertNotFound();
    $this->actingAs($admin)->put('/plant-1/departments/'.$department->getKey(), [
        'kode_department' => 'X',
        'nama_department' => 'X',
    ])->assertNotFound();
    $this->actingAs($admin)->delete('/plant-1/departments/'.$department->getKey())->assertNotFound();
});
