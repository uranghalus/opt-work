<?php

use App\Models\DailyWork;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Task 5 — inbox "Perlu Verifikasi" (plan .scratch/daily-work-verification/
 * plan.md): antrean lintas tanggal & lintas bawahan sesuai scope
 * DailyWorkVerifierScope + can flag daily-work.verify untuk nav.
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
 * Department + HOD + dua bawahan (masing-masing punya akun) + item
 * submitted di dua tanggal berbeda.
 *
 * @return array{0: User, 1: Department, 2: Employee, 3: Employee}
 */
function makeInboxScope(): array
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $department = Department::factory()->create(['tenant_id' => $tenantId]);

    $hodEmployee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $hod = createUser(['tenant_id' => $tenantId, 'employee_id' => $hodEmployee->getKey()]);
    $hod->assignRole('hod');
    $department->update(['hod_user_id' => $hod->getKey()]);

    $first = Employee::factory()->create([
        'tenant_id' => $tenantId,
        'department_id' => $department->getKey(),
    ]);
    $second = Employee::factory()->create([
        'tenant_id' => $tenantId,
        'department_id' => $department->getKey(),
    ]);

    return [$hod, $department, $first, $second];
}

/**
 * Item submitted milik satu employee pada satu tanggal.
 */
function makeSubmittedItem(Employee $employee, string $workDate): DailyWork
{
    return DailyWork::factory()->submitted()->create([
        'employee_id' => $employee->getKey(),
        'work_date' => $workDate,
    ]);
}

it('queues submitted items across subordinates and dates, newest first', function () {
    [$hod, , $first, $second] = makeInboxScope();

    $older = makeSubmittedItem($first, '2099-12-31');
    $older->update(['submitted_at' => now()->subHours(3)]);
    $newer = makeSubmittedItem($second, '2100-01-01');
    $newer->update(['submitted_at' => now()->subHour()]);

    $this->actingAs($hod)
        ->get('/hq/daily-work/verification')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('daily-work/verification')
            ->where('verificationQueue.total', 2)
            ->where('verificationQueue.data.0.id', $newer->getKey())
            ->where('verificationQueue.data.1.id', $older->getKey())
            ->where('verificationQueue.data.0.work_date', '2100-01-01')
            ->where('verificationQueue.data.1.work_date', '2099-12-31'));
});

it('hides items outside the scope and items outside the pending state', function () {
    [$hod, , $first, $second] = makeInboxScope();

    $visible = makeSubmittedItem($first, '2100-01-01');

    // Item di department lain (HOD lain) — tidak boleh bocor.
    $foreignDepartment = Department::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
    ]);
    $foreignEmployee = Employee::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
        'department_id' => $foreignDepartment->getKey(),
    ]);
    makeSubmittedItem($foreignEmployee, '2100-01-01');

    // Item bawahan yang sudah selesai / belum diserahkan — bukan antrean.
    DailyWork::factory()->verified()->create([
        'employee_id' => $second->getKey(),
        'work_date' => '2100-01-01',
    ]);
    DailyWork::factory()->create([
        'employee_id' => $second->getKey(),
        'work_date' => '2100-01-01',
    ]);

    $this->actingAs($hod)
        ->get('/hq/daily-work/verification')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('verificationQueue.total', 1)
            ->where('verificationQueue.data.0.id', $visible->getKey()));
});

it('filters the queue by employee and search with pagination meta', function () {
    [$hod, , $first, $second] = makeInboxScope();

    $patrol = makeSubmittedItem($first, '2100-01-01');
    $other = makeSubmittedItem($second, '2100-01-01');
    $other->update(['submitted_at' => now()->subHour()]);

    $this->actingAs($hod)
        ->get('/hq/daily-work/verification?employee_id='.$first->getKey())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('verificationQueue.total', 1)
            ->where('verificationQueue.data.0.id', $patrol->getKey()));

    $this->actingAs($hod)
        ->get('/hq/daily-work/verification?search='.urlencode($patrol->title))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('verificationQueue.total', 1)
            ->where('verificationQueue.data.0.id', $patrol->getKey()));

    $this->actingAs($hod)
        ->get('/hq/daily-work/verification')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('verificationQueue.current_page')
            ->has('verificationQueue.last_page')
            ->where('verificationQueue.total', 2));
});

it('gates the inbox to the verifier scope', function () {
    [$hod, $department, , ] = makeInboxScope();

    // TL department sama — boleh.
    $tlEmployee = Employee::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
        'department_id' => $department->getKey(),
    ]);
    $teamLeader = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->getKey(), 'employee_id' => $tlEmployee->getKey()]);
    $teamLeader->assignRole('team_leader');

    $this->actingAs($teamLeader)
        ->get('/hq/daily-work/verification')
        ->assertOk();

    // Admin tenant — boleh (tanpa department link).
    $admin = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->getKey()]);
    $admin->assignRole('admin_tenant');
    $this->actingAs($admin)
        ->get('/hq/daily-work/verification')
        ->assertOk();

    // Karyawan (tanpa daily-work.verify) — 403.
    $karyawanEmployee = Employee::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
    ]);
    $karyawan = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->getKey(), 'employee_id' => $karyawanEmployee->getKey()]);
    $karyawan->assignRole('karyawan');
    $this->actingAs($karyawan)
        ->get('/hq/daily-work/verification')
        ->assertForbidden();

    // Guest — 403.
    $guest = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->getKey()]);
    $guest->assignRole('guest');
    $this->actingAs($guest)
        ->get('/hq/daily-work/verification')
        ->assertForbidden();

    // HOD dari setup — 200 (sanity).
    $this->actingAs($hod)
        ->get('/hq/daily-work/verification')
        ->assertOk();
});

it('shapes the verification queue payload', function () {
    [$hod, , $first] = makeInboxScope();

    $item = makeSubmittedItem($first, '2100-01-01');
    $item->update(['proof_notes' => 'Unit aman', 'proof_attachments' => ['daily-work/hq/a.jpg', 'daily-work/hq/b.jpg']]);

    $this->actingAs($hod)
        ->get('/hq/daily-work/verification')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('verificationQueue.data.0.id', $item->getKey())
            ->where('verificationQueue.data.0.status', 'pending_verify')
            ->where('verificationQueue.data.0.proof_notes', 'Unit aman')
            ->has('verificationQueue.data.0.proof_attachments', 2)
            ->where('verificationQueue.data.0.employee.id', $first->getKey())
            ->where('verificationQueue.data.0.employee.nama_employee', $first->nama_employee)
            ->where('verificationQueue.data.0.verification.rejected', false)
            ->where('verificationQueue.data.0.verification.verified_by_name', null)
            ->where('verificationQueue.data.0.verification.rejection_note', null)
            ->where('verificationQueue.data.0.submitted_at', fn ($value) => $value !== null));
});

it('exposes the daily-work verify can flag per role', function () {
    [$hod, $department] = makeInboxScope();

    // Kunci flag mengandung titik ('daily-work.verify') — dot-path json()
    // akan salah pecah; ambil array can lalu akses kuncinya.
    $assertFlag = function ($response, bool $expected) {
        $response->assertInertia(fn ($page) =>
            expect($page->toArray()['props']['auth']['can']['daily-work.verify'])->toBe($expected));
    };

    $assertFlag($this->actingAs($hod)->get('/hq/daily-work/verification')->assertOk(), true);

    $tlEmployee = Employee::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
        'department_id' => $department->getKey(),
    ]);
    $teamLeader = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->getKey(), 'employee_id' => $tlEmployee->getKey()]);
    $teamLeader->assignRole('team_leader');
    $assertFlag($this->actingAs($teamLeader)->get('/hq/daily-work/verification')->assertOk(), true);

    $admin = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->getKey()]);
    $admin->assignRole('admin_tenant');
    $assertFlag($this->actingAs($admin)->get('/hq/daily-work/verification')->assertOk(), true);

    $karyawanEmployee = Employee::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
    ]);
    $karyawan = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->getKey(), 'employee_id' => $karyawanEmployee->getKey()]);
    $karyawan->assignRole('karyawan');
    $assertFlag($this->actingAs($karyawan)->get('/hq/daily-work')->assertOk(), false);
});
