<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => '9001',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    (new RoleAndPermissionSeeder)->run();
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('resolves the position tree route end to end', function () {
    $parent = Position::factory()->create([
        'nama_position' => 'Head of Department',
        'level' => 3,
        'level_label' => 'Manager',
    ]);
    $child = Position::factory()->create([
        'nama_position' => 'Teknisi Senior',
        'parent_position_id' => $parent->getKey(),
        'level' => 7,
    ]);
    Employee::factory()->count(2)->create(['position_id' => $child->getKey()]);

    $user = createUser(['tenant_id' => $this->hq->id]);
    $user->assignRole('karyawan');

    // X-Inertia request: assert JSON payload (route → container → controller →
    // Inertia) tanpa render blade — sebelum fix import, route ini selalu 500
    // karena class controller tidak ter-resolve.
    //
    // X-Inertia-Version wajib persis versi server — ambil dari middleware
    // (Inertia::getVersion() masih kosong sebelum request pertama; mismatch
    // memicu 409 refresh Inertia).
    $version = app(HandleInertiaRequests::class)->version(request());

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->get('/hq/positions/tree')
        ->assertOk()
        ->assertJsonPath('component', 'positions/tree')
        ->assertJsonCount(1, 'props.rootPositions')
        ->assertJsonPath('props.rootPositions.0.id', $parent->getKey())
        ->assertJsonPath('props.rootPositions.0.children.0.id', $child->getKey())
        ->assertJsonPath('props.rootPositions.0.children.0.employees_count', 2);
});
