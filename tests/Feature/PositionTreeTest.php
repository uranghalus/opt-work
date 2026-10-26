<?php

use App\Http\Middleware\HandleInertiaRequests;
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
    $user = createUser(['tenant_id' => $this->hq->id]);
    $user->assignRole('karyawan');

    // X-Inertia request: assert JSON payload (route → container → controller →
    // Inertia) tanpa render blade — page frontend `positions/tree` belum
    // dibangun (temuan terpisah, lihat TODO); yang dulu selalu 500 di sini
    // adalah resolusi class controller (bug import routes/web.php).
    //
    // X-Inertia-Version wajib persis versi server — ambil dari middleware
    // (Inertia::getVersion() masih kosong sebelum request pertama; mismatch
    // memicu 409 refresh Inertia).
    $version = app(HandleInertiaRequests::class)->version(request());

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->get('/hq/positions/tree')
        ->assertOk()
        ->assertJsonPath('component', 'positions/tree');
});
