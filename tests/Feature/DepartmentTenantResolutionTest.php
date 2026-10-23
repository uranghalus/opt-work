<?php

use App\Models\Department;

it('show resolves the department inside the route tenant', function () {
    $admin = makeSuperAdmin(createUser());

    $hq = createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    $plant = createTestTenant([
        'optigate_company_id' => 2,
        'code' => 'plant-1',
        'name' => 'Plant 1',
    ]);

    $hqDepartment = Department::withoutGlobalScopes()->make([
        'kode_department' => 'IT',
        'nama_department' => 'HQ IT',
    ]);
    $hqDepartment->tenant_id = $hq->id;
    $hqDepartment->save();

    $plantDepartment = Department::withoutGlobalScopes()->make([
        'kode_department' => 'IT',
        'nama_department' => 'Plant IT',
    ]);
    $plantDepartment->tenant_id = $plant->id;
    $plantDepartment->save();

    $payload = $this->actingAs($admin)
        ->get('/hq/departments/'.$hqDepartment->getKey())
        ->assertOk()
        ->viewData('page')['props']['department'];

    expect($payload['id'])->toBe($hqDepartment->id)
        ->and($payload['nama_department'])->toBe('HQ IT');
});

it('foreign tenant department is 404 on show', function () {
    $admin = makeSuperAdmin(createUser());

    createTestTenant([
        'optigate_company_id' => 2,
        'code' => 'plant-1',
        'name' => 'Plant 1',
    ]);
    $hq = createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);

    $department = Department::withoutGlobalScopes()->make([
        'kode_department' => 'IT',
        'nama_department' => 'IT',
    ]);
    $department->tenant_id = $hq->id;
    $department->save();

    $this->actingAs($admin)
        ->get('/plant-1/departments/'.$department->getKey())
        ->assertNotFound();
});
