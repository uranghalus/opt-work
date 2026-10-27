<?php

use App\Models\AuditEvent;
use App\Models\DailyWork;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * Task 3 — verifikasi serta tolak bukti Daily Work oleh HOD/TL terkait
 * (plan .scratch/daily-work-verification/plan.md): scope DailyWorkVerifier,
 * state guard 409, gate objek daily-work.view untuk proxy attachment,
 * dan registrasi permission daily-work.verify di seeder.
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    initTenant('hq');

    Storage::fake('s3');
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/**
 * Department menyertakan HOD (role hod) + bawahan pemilik item yang
 * sudah menyerahkan bukti (state submitted). Manager/TL/admin tenant
 * dibuat per test dari department yang dikembalikan di sini.
 *
 * @return array{0: DailyWork, 1: User, 2: Department, 3: Employee, 4: User}
 */
function makeSubmittedDailyWorkForVerification(): array
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $department = Department::factory()->create(['tenant_id' => $tenantId]);

    $hodEmployee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $hod = createUser(['tenant_id' => $tenantId, 'employee_id' => $hodEmployee->getKey()]);
    $hod->assignRole('hod');
    $department->update(['hod_user_id' => $hod->getKey()]);

    $subordinate = Employee::factory()->create([
        'tenant_id' => $tenantId,
        'department_id' => $department->getKey(),
    ]);
    $owner = createUser(['tenant_id' => $tenantId, 'employee_id' => $subordinate->getKey()]);

    $dailyWork = DailyWork::factory()->submitted()->create([
        'employee_id' => $subordinate->getKey(),
        'work_date' => '2100-01-01',
    ]);

    return [$dailyWork, $hod, $department, $subordinate, $owner];
}

/**
 * TL ber-role team_leader pada department tertentu (atau tanpa link
 * karyawan bila $departmentId null).
 */
function makeTeamLeader(int|string|null $departmentId): App\Models\User
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $employee = $departmentId === null
        ? Employee::factory()->create(['tenant_id' => $tenantId])
        : Employee::factory()->create(['tenant_id' => $tenantId, 'department_id' => $departmentId]);

    $teamLeader = createUser(['tenant_id' => $tenantId, 'employee_id' => $employee->getKey()]);
    $teamLeader->assignRole('team_leader');

    return $teamLeader;
}

it('waives verification by the HOD and marks the item done', function () {
    [$dailyWork, $hod] = makeSubmittedDailyWorkForVerification();

    $submittedAt = $dailyWork->submitted_at;
    $doneAt = $dailyWork->done_at;

    $this->actingAs($hod)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/verify")
        ->assertRedirect();

    $dailyWork->refresh();

    expect($dailyWork->status)->toBe('done')
        ->and($dailyWork->verified_by)->toBe($hod->getKey())
        ->and($dailyWork->verified_at)->not->toBeNull()
        ->and($dailyWork->done_at->equalTo($doneAt))->toBeTrue()
        ->and($dailyWork->submitted_at->equalTo($submittedAt))->toBeTrue()
        ->and($dailyWork->proof_notes)->not->toBeNull();
});

it('refuses a second verification attempt with 409', function () {
    [$dailyWork, $hod] = makeSubmittedDailyWorkForVerification();

    $this->actingAs($hod)->post("/hq/daily-work/{$dailyWork->getKey()}/verify");

    $this->actingAs($hod)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/verify")
        ->assertStatus(409);
});

it('lets a team leader of the same department verify', function () {
    [$dailyWork] = makeSubmittedDailyWorkForVerification();

    $teamLeader = makeTeamLeader($dailyWork->employee->department_id);

    $this->actingAs($teamLeader)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/verify")
        ->assertRedirect();

    $dailyWork->refresh();

    expect($dailyWork->status)->toBe('done')
        ->and($dailyWork->verified_by)->toBe($teamLeader->getKey());
});

it('refuses a team leader from a different department with 403', function () {
    [$dailyWork] = makeSubmittedDailyWorkForVerification();

    $otherDepartment = Department::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
    ]);
    $outsiderLeader = makeTeamLeader($otherDepartment->getKey());

    $this->actingAs($outsiderLeader)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/verify")
        ->assertForbidden();

    $dailyWork->refresh();
    expect($dailyWork->status)->toBe('pending_verify');
});

it('refuses a team leader without a linked employee with 403', function () {
    [$dailyWork] = makeSubmittedDailyWorkForVerification();

    $teamLeader = makeTeamLeader(null);
    $teamLeader->update(['employee_id' => null]);

    $this->actingAs($teamLeader)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/verify")
        ->assertForbidden();
});

it('refuses the HOD of another department with 403', function () {
    [$dailyWork] = makeSubmittedDailyWorkForVerification();

    $otherDepartment = Department::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
    ]);
    $foreignHod = makeTeamLeader($otherDepartment->getKey());
    $foreignHod->assignRole('hod');
    $otherDepartment->update(['hod_user_id' => $foreignHod->getKey()]);

    $this->actingAs($foreignHod)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/verify")
        ->assertForbidden();
});

it('refuses the owner verifying his own proof with 403', function () {
    [$dailyWork, , , , $owner] = makeSubmittedDailyWorkForVerification();

    // Owner diberi permission verifikasi agar 403 diuji pada LAPIS SCOPE
    // (service), bukan pada gerbang permission.
    $owner->givePermissionTo('daily-work.verify');

    $this->actingAs($owner)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/verify")
        ->assertForbidden();

    $dailyWork->refresh();
    expect($dailyWork->status)->toBe('pending_verify');
});

it('lets a tenant admin verify without any department link', function () {
    [$dailyWork] = makeSubmittedDailyWorkForVerification();

    $admin = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->getKey()]);
    $admin->assignRole('admin_tenant');

    $this->actingAs($admin)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/verify")
        ->assertRedirect();

    $dailyWork->refresh();
    expect($dailyWork->status)->toBe('done')
        ->and($dailyWork->verified_by)->toBe($admin->getKey());
});

it('rejects the proof and keeps it for rework', function () {
    [$dailyWork, $hod] = makeSubmittedDailyWorkForVerification();

    $this->actingAs($hod)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/reject", [
            'rejection_note' => 'Foto kurang jelas, ambil ulang',
        ])
        ->assertRedirect();

    $dailyWork->refresh();

    expect($dailyWork->status)->toBe('in_progress')
        ->and($dailyWork->rejected_by)->toBe($hod->getKey())
        ->and($dailyWork->rejected_at)->not->toBeNull()
        ->and($dailyWork->rejection_note)->toBe('Foto kurang jelas, ambil ulang')
        ->and($dailyWork->proof_attachments)->toHaveCount(2)
        ->and($dailyWork->verified_by)->toBeNull();
});

it('requires a rejection note', function () {
    [$dailyWork, $hod] = makeSubmittedDailyWorkForVerification();

    $this->actingAs($hod)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/reject", [])
        ->assertSessionHasErrors(['rejection_note']);
});

it('refuses rejecting items outside the pending verification state with 409', function () {
    [$dailyWork, $hod] = makeSubmittedDailyWorkForVerification();

    // Item belum diserahkan (pending).
    $stale = DailyWork::factory()->create([
        'employee_id' => $dailyWork->employee_id,
        'work_date' => '2100-01-01',
    ]);

    $this->actingAs($hod)
        ->post("/hq/daily-work/{$stale->getKey()}/reject", [
            'rejection_note' => 'Terlalu dulu',
        ])
        ->assertStatus(409);

    // Item sudah selesai (done).
    $this->actingAs($hod)->post("/hq/daily-work/{$dailyWork->getKey()}/verify");
    $dailyWork->refresh();

    $this->actingAs($hod)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/reject", [
            'rejection_note' => 'Sudah terlambat',
        ])
        ->assertStatus(409);
});

it('serves proof attachments only to the owner or the verifier scope', function () {
    [$dailyWork, $hod, , , $owner] = makeSubmittedDailyWorkForVerification();

    // Tulis file bukti nyata di disk fake agar redirect proxy terjadi.
    $path = sprintf('daily-work/%s/bukti-nyata-1.jpg', Tenant::where('code', 'hq')->first()->getKey());
    Storage::disk('s3')->put($path, 'binary');

    $dailyWork->update(['proof_attachments' => [$path]]);

    // Owner boleh (own item).
    $this->actingAs($owner)
        ->get("/hq/daily-work/{$dailyWork->getKey()}/attachments/0")
        ->assertRedirect();

    // Verifier scope boleh.
    $this->actingAs($hod)
        ->get("/hq/daily-work/{$dailyWork->getKey()}/attachments/0")
        ->assertRedirect();

    // User lain (bukan owner, bukan scope) ditolak.
    $stranger = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->getKey()]);
    $this->actingAs($stranger)
        ->get("/hq/daily-work/{$dailyWork->getKey()}/attachments/0")
        ->assertForbidden();
});

it('audits a denied proof access', function () {
    [$dailyWork] = makeSubmittedDailyWorkForVerification();

    $guest = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->getKey()]);
    $guest->assignRole('guest');

    $this->actingAs($guest)
        ->get("/hq/daily-work/{$dailyWork->getKey()}/attachments/0")
        ->assertForbidden();

    $event = app(AuditEvent::class)
        ->newQuery()
        ->where('action', 'access.denied')
        ->sole();

    expect($event->actor_user_id)->toBe($guest->getKey())
        ->and($event->context['route'])->toBe('daily-work.attachment');
});

it('answers 404 for an unknown attachment index', function () {
    [$dailyWork, $hod] = makeSubmittedDailyWorkForVerification();

    $this->actingAs($hod)
        ->get("/hq/daily-work/{$dailyWork->getKey()}/attachments/5")
        ->assertNotFound();
});

it('answers 404 when the proof file is missing from storage', function () {
    [$dailyWork, $hod] = makeSubmittedDailyWorkForVerification();

    $dailyWork->update(['proof_attachments' => ['daily-work/hilang.jpg']]);

    $this->actingAs($hod)
        ->get("/hq/daily-work/{$dailyWork->getKey()}/attachments/0")
        ->assertNotFound();
});

it('blocks proof access of another tenant before the lookup', function () {
    [$dailyWork, $hod] = makeSubmittedDailyWorkForVerification();

    createTestTenant([
        'optigate_company_id' => 2,
        'code' => 'cabang',
        'name' => 'Cabang Tes',
    ]);
    initTenant('cabang');

    $this->actingAs($hod)
        ->get("/cabang/daily-work/{$dailyWork->getKey()}/attachments/0")
        // tenant.access memblok URL lintas tenant SEBELUM lookup (pola WO).
        ->assertForbidden();
});

it('seeds the daily-work verify permission for the verifying roles only', function () {
    expect(Permission::query()->where('name', 'daily-work.verify')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'daily-work.view')->exists())->toBeTrue();

    $defaults = RoleAndPermissionSeeder::roleDefaults();

    foreach (['hod', 'team_leader', 'admin_tenant'] as $role) {
        expect($defaults[$role])->toContain('daily-work.verify')
            ->and($defaults[$role])->toContain('daily-work.view');
    }

    foreach (['general_manager', 'deputy_general_manager', 'karyawan', 'field_staff', 'viewer'] as $role) {
        expect($defaults[$role])->not->toContain('daily-work.verify')
            ->and($defaults[$role])->not->toContain('daily-work.view');
    }
});
