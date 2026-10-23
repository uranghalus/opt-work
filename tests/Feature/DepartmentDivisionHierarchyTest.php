<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Tenant;

beforeEach(function () {
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('links a department to many divisions and a division to its parent', function () {
    initTenant('hq');

    $department = Department::create([
        'kode_department' => 'IT',
        'nama_department' => 'Information Technology',
    ]);

    $application = Division::create([
        'kode_division' => 'APP',
        'nama_division' => 'Application',
        'department_id' => $department->id,
    ]);

    $infrastructure = Division::create([
        'kode_division' => 'INF',
        'nama_division' => 'Infrastructure',
        'department_id' => $department->id,
    ]);

    expect($department->divisions()->orderBy('nama_division')->pluck('id')->all())
        ->toBe([$application->id, $infrastructure->id])
        ->and($application->department?->id)->toBe($department->id);
});

it('renders all divisions on the department show page', function () {
    initTenant('hq');

    $department = Department::create([
        'kode_department' => 'IT',
        'nama_department' => 'Information Technology',
    ]);

    Division::create([
        'kode_division' => 'APP',
        'nama_division' => 'Application',
        'department_id' => $department->id,
    ]);
    Division::create([
        'kode_division' => 'INF',
        'nama_division' => 'Infrastructure',
        'department_id' => $department->id,
    ]);

    $admin = makeSuperAdmin(createUser());

    $payload = $this->actingAs($admin)
        ->get('/hq/departments/'.$department->getKey())
        ->assertOk()
        ->viewData('page')['props']['department'];

    expect(collect($payload['divisions'])->pluck('nama_division')->all())
        ->toBe(['Application', 'Infrastructure']);
});

it('renders the parent department and the explicit direct state on the division page', function () {
    initTenant('hq');

    $department = Department::create([
        'kode_department' => 'IT',
        'nama_department' => 'Information Technology',
    ]);

    $application = Division::create([
        'kode_division' => 'APP',
        'nama_division' => 'Application',
        'department_id' => $department->id,
    ]);
    $direct = Division::create([
        'kode_division' => 'DIR',
        'nama_division' => 'Direct',
        'department_id' => null,
        'is_direct' => true,
    ]);

    $admin = makeSuperAdmin(createUser());

    $applicationPayload = $this->actingAs($admin)
        ->get('/hq/divisions/'.$application->getKey())
        ->assertOk()
        ->viewData('page')['props']['division'];

    $directPayload = $this->actingAs($admin)
        ->get('/hq/divisions/'.$direct->getKey())
        ->assertOk()
        ->viewData('page')['props']['division'];

    expect($applicationPayload['department']['nama_department'])->toBe('Information Technology')
        ->and($applicationPayload['is_direct'])->toBeFalse()
        ->and($directPayload['department'])->toBeNull()
        ->and($directPayload['is_direct'])->toBeTrue();
});
