<?php

use App\Models\DailyWork;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Task 2 — FR-5.4: karyawan mengubah status (belum/proses/selesai) dan
 * lokasi item Daily Work miliknya sendiri; transisi done mengisi done_at;
 * item milik karyawan lain ditolak 403.
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    initTenant('hq');
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/**
 * @return array{0: DailyWork, 1: User, 2: Employee}
 */
function makeOwnedDailyWork(): array
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();
    $employee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $user = createUser(['tenant_id' => $tenantId, 'employee_id' => $employee->getKey()]);
    $user->givePermissionTo('daily-work.read');
    $user->givePermissionTo('daily-work.update');

    $dailyWork = DailyWork::factory()->create([
        'employee_id' => $employee->getKey(),
        'work_date' => '2026-10-26',
    ]);

    return [$dailyWork, $user, $employee];
}

it('lets the owner update status and location of their own item', function () {
    [$dailyWork, $user] = makeOwnedDailyWork();

    $this->actingAs($user)
        ->from('/hq/daily-work')
        ->patch("/hq/daily-work/{$dailyWork->getKey()}", [
            'status' => 'in_progress',
            'location' => 'Ruang Server Lt. 3',
        ])
        // Redirect balik ke daftar tanggal item itu (bukan hari ini),
        // supaya konteks daftar tanggal D yang sedang dibuka tetap.
        ->assertRedirect('/hq/daily-work?date=2026-10-26');

    $dailyWork->refresh();
    expect($dailyWork->status)->toBe('in_progress')
        ->and($dailyWork->location)->toBe('Ruang Server Lt. 3');
});

it('fills done_at when the item moves to done', function () {
    [$dailyWork, $user] = makeOwnedDailyWork();

    $this->actingAs($user)->patch("/hq/daily-work/{$dailyWork->getKey()}", [
        'status' => 'done',
    ])->assertRedirect();

    $dailyWork->refresh();
    expect($dailyWork->status)->toBe('done')
        ->and($dailyWork->done_at)->not->toBeNull();
});

it('rejects updating an item owned by another employee with 403', function () {
    [$dailyWork, $user] = makeOwnedDailyWork();

    $tenantId = Tenant::where('code', 'hq')->first()->getKey();
    $otherEmployee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $otherUser = createUser(['tenant_id' => $tenantId, 'employee_id' => $otherEmployee->getKey()]);
    $otherUser->givePermissionTo('daily-work.update');

    $this->actingAs($otherUser)
        ->patch("/hq/daily-work/{$dailyWork->getKey()}", ['status' => 'done'])
        ->assertForbidden();
});

it('rejects a user without daily-work update permission with 403', function () {
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();
    $employee = Employee::factory()->create(['tenant_id' => $tenantId]);
    // Pemilik item tapi TANPA izin daily-work.update.
    $user = createUser(['tenant_id' => $tenantId, 'employee_id' => $employee->getKey()]);

    $dailyWork = DailyWork::factory()->create([
        'employee_id' => $employee->getKey(),
        'work_date' => '2026-10-26',
    ]);

    $this->actingAs($user)
        ->patch("/hq/daily-work/{$dailyWork->getKey()}", ['status' => 'done'])
        ->assertForbidden();
});
