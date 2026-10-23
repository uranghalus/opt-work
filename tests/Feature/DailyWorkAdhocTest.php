<?php

use App\Models\DailyWork;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\DailyWorkAssigned;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Task 3 — FR-5.2: HOD menambahkan tugas tambahan (adhoc) untuk karyawan
 * bawahan pada tanggal tertentu → notifikasi (database + broadcast; WA
 * outbox no-op tanpa WAHA_URL) ke akun yang ter-link.
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
 * HOD + karyawan bawahan ber-akun.
 *
 * @return array{0: User, 1: Employee, 2: User, 3: Department}
 */
function makeHodWithLinkedSubordinate(): array
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $department = Department::factory()->create(['tenant_id' => $tenantId]);

    $hodEmployee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $hod = createUser(['tenant_id' => $tenantId, 'employee_id' => $hodEmployee->getKey()]);
    $hod->givePermissionTo(['daily-work.read', 'daily-work.create']);

    $subordinate = Employee::factory()->create([
        'tenant_id' => $tenantId,
        'department_id' => $department->getKey(),
    ]);
    $subordinateUser = createUser(['tenant_id' => $tenantId, 'employee_id' => $subordinate->getKey()]);

    $department->update(['hod_user_id' => $hod->getKey()]);

    return [$hod, $subordinate, $subordinateUser, $department];
}

it('creates an adhoc task and notifies the linked akun', function () {
    [$hod, $subordinate, $subordinateUser] = makeHodWithLinkedSubordinate();

    Notification::fake();

    $this->actingAs($hod)
        ->post('/hq/daily-work/adhoc', [
            'employee_id' => $subordinate->getKey(),
            'work_date' => '2026-10-26',
            'title' => 'Ganti lampu area parkir',
            'location' => 'Basement 1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $dailyWork = DailyWork::query()
        ->where('employee_id', $subordinate->getKey())
        ->whereDate('work_date', '2026-10-26')
        ->firstOrFail();

    expect($dailyWork->title)->toBe('Ganti lampu area parkir')
        ->and($dailyWork->source)->toBe('adhoc')
        ->and($dailyWork->location)->toBe('Basement 1')
        ->and($dailyWork->created_by)->toBe($hod->getKey());

    // Notifikasi (database + broadcast + WA outbox) ke akun karyawan
    // yang ter-link (FR-7.1 — Daily Work baru ditambahkan).
    Notification::assertSentTo($subordinateUser, DailyWorkAssigned::class);
});

it('does not notify when the karyawan has no linked akun', function () {
    [$hod, $subordinate] = makeHodWithLinkedSubordinate();
    $subordinate->user()->delete();

    Notification::fake();

    $this->actingAs($hod)
        ->post('/hq/daily-work/adhoc', [
            'employee_id' => $subordinate->getKey(),
            'work_date' => '2026-10-26',
            'title' => 'Tanpa akun',
        ])
        ->assertRedirect();

    $dailyWork = DailyWork::query()->where('employee_id', $subordinate->getKey())->firstOrFail();
    expect($dailyWork)->not->toBeNull();
    Notification::assertNothingSent();
});

it('rejects an adhoc task for an employee outside scope with 403', function () {
    [$hod] = makeHodWithLinkedSubordinate();
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $outsider = Employee::factory()->create(['tenant_id' => $tenantId]);

    $this->actingAs($hod)
        ->post('/hq/daily-work/adhoc', [
            'employee_id' => $outsider->getKey(),
            'work_date' => '2026-10-26',
            'title' => 'Di luar scope',
        ])
        ->assertForbidden();

    expect(DailyWork::count())->toBe(0);
});

it('rejects an adhoc task without the create permission with 403', function () {
    [$hod, $subordinate] = makeHodWithLinkedSubordinate();
    $hod->revokePermissionTo('daily-work.create');

    $this->actingAs($hod)
        ->post('/hq/daily-work/adhoc', [
            'employee_id' => $subordinate->getKey(),
            'work_date' => '2026-10-26',
            'title' => 'Tanpa izin',
        ])
        ->assertForbidden();

    expect(DailyWork::count())->toBe(0);
});

it('validates the adhoc payload', function () {
    [$hod, $subordinate] = makeHodWithLinkedSubordinate();

    $this->actingAs($hod)
        ->post('/hq/daily-work/adhoc', [
            'employee_id' => $subordinate->getKey(),
            'work_date' => '26-10-2026', // format salah
            'title' => '',
        ])
        ->assertSessionHasErrors(['work_date', 'title']);

    expect(DailyWork::count())->toBe(0);
});
