<?php

use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DailyWorkService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Task 2 — Materialize-on-view: item template aktif di-upsert idempoten
 * menjadi baris daily_works saat daftar tanggal D dibuka. Kolom progres
 * (status/location/done_at) tidak pernah ditimpa; unik (template_id,
 * work_date) mencegah dobel.
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
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
    initTenant('hq');
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/**
 * @return array{0: Employee, 1: User}
 */
function makeDailyWorkOwner(array $templateOverrides = [], bool $withTemplate = true): array
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();
    $employee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $user = createUser(['tenant_id' => $tenantId, 'employee_id' => $employee->getKey()]);
    $user->givePermissionTo('daily-work.read');
    $user->givePermissionTo('daily-work.update');

    if ($withTemplate) {
        DailyWorkTemplate::factory()->create(array_merge([
            'employee_id' => $employee->getKey(),
            'weekdays' => [1, 2, 3, 4, 5],
        ], $templateOverrides));
    }

    return [$employee, $user];
}

it('materializes monday-to-friday templates on monday but not saturday', function () {
    [$employee, $user] = makeDailyWorkOwner();

    $this->actingAs($user)->get('/hq/daily-work?date=2026-10-26') // Senin
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('daily-work/index')
            ->where('dailyWork.total', 1)
            ->where('dailyWork.data.0.source', 'template'));

    $this->actingAs($user)->get('/hq/daily-work?date=2026-10-31') // Sabtu
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('dailyWork.total', 0));

    expect(DailyWork::whereDate('work_date', '2026-10-26')->count())->toBe(1)
        ->and(DailyWork::whereDate('work_date', '2026-10-31')->count())->toBe(0);
});

it('skips inactive templates for new dates', function () {
    [$employee, $user] = makeDailyWorkOwner(['is_active' => false]);

    $this->actingAs($user)->get('/hq/daily-work?date=2026-10-26')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('dailyWork.total', 0));
});

it('never overwrites progress on re-materialize', function () {
    [$employee, $user] = makeDailyWorkOwner();

    $this->actingAs($user)->get('/hq/daily-work?date=2026-10-26')->assertOk();

    $instance = DailyWork::where('employee_id', $employee->getKey())
        ->whereDate('work_date', '2026-10-26')->firstOrFail();
    $instance->update(['status' => 'done', 'location' => 'Ruang Server', 'done_at' => now()]);

    // Buka daftar yang sama lagi — materialize jalan lagi, progres tetap.
    $this->actingAs($user)->get('/hq/daily-work?date=2026-10-26')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dailyWork.total', 1)
            ->where('dailyWork.data.0.status', 'done')
            ->where('dailyWork.data.0.location', 'Ruang Server'));
});

it('keeps history when the template is deleted', function () {
    [$employee, $user] = makeDailyWorkOwner();

    $this->actingAs($user)->get('/hq/daily-work?date=2026-10-26')->assertOk();

    DailyWorkTemplate::query()->firstOrFail()->delete();

    app(DailyWorkService::class)->materializeFor($employee->fresh(), CarbonImmutable::parse('2026-10-26'));

    $instance = DailyWork::where('employee_id', $employee->getKey())
        ->whereDate('work_date', '2026-10-26')->firstOrFail();
    expect($instance->source)->toBe('template')
        ->and($instance->template_id)->toBeNull();
});

it('renders an empty list safely for a user without linked employee', function () {
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('daily-work.read');

    // Akun tanpa link karyawan TIDAK boleh melihat daftar milik karyawan
    // lain di tenant yang sama (fix 2026-10-24 — sebelumnya seluruh daftar
    // tenant bocor karena filter employee_id hanya dipasang bila ada link).
    $linkedEmployee = Employee::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
    ]);
    DailyWork::factory()->create([
        'employee_id' => $linkedEmployee->getKey(),
        'title' => 'Milik orang lain',
    ]);

    $this->actingAs($user)->get('/hq/daily-work')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('daily-work/index')
            ->where('dailyWork.total', 0)
            ->where('can_update', false));
});

it('rejects an invalid date with 422', function () {
    [, $user] = makeDailyWorkOwner();

    $this->actingAs($user)->get('/hq/daily-work?date=not-a-date')
        ->assertSessionHasErrors('date');
});

it('does not leak other tenants daily works', function () {
    $plantTenantId = Tenant::where('code', 'plant-1')->first()->getKey();
    $plantEmployee = Employee::factory()->create(['tenant_id' => $plantTenantId]);
    DailyWork::factory()->create([
        'employee_id' => $plantEmployee->getKey(),
        'work_date' => '2026-10-26',
        'title' => 'WO Plant 1',
    ]);

    [$employee, $user] = makeDailyWorkOwner([], withTemplate: false);

    $this->actingAs($user)->get('/hq/daily-work?date=2026-10-26')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('dailyWork.total', 0));
});
