<?php

use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => '1',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);

    (new class extends Seeder
    {
        public function run(): void
        {
            (new RoleAndPermissionSeeder)->run();
        }
    })->run();

    $this->admin = makeSuperAdmin(createUser());
    $this->employee = Employee::create([
        'tenant_id' => $this->hq->id,
        'nik_employee' => 'NIK-001',
        'nama_employee' => 'John Doe',
    ]);
});

function linkAkunAs(?User $actor = null): void
{
    $akun = createUser(['tenant_id' => test()->hq->id, 'name' => 'Karyawan Baru']);

    test()->actingAs($actor ?? test()->admin)->post(
        route('employees.akun.link', ['tenant' => 'hq', 'employee' => test()->employee->id]),
        ['user_id' => $akun->id],
    )->assertRedirect();
}

it('records an akun.link audit row with actor, tenant, and before/after', function () {
    linkAkunAs();

    $log = AuditLog::query()->where('action', AuditAction::AkunLink)->sole();

    expect($log->tenant_id)->toBe($this->hq->id)
        ->and($log->actor_user_id)->toBe($this->admin->id)
        ->and($log->auditable_type)->toBe((new Employee)->getMorphClass())
        ->and($log->auditable_id)->toBe($this->employee->id)
        ->and($log->before)->toBe(['user_id' => null])
        ->and($log->after['user_id'])->toBeInt()
        ->and($log->reason)->toBeNull();
});

it('records an akun.unlink audit row with the previous link in before', function () {
    linkAkunAs();
    $akun = $this->employee->user()->first();

    $this->actingAs($this->admin)->delete(
        route('employees.akun.unlink', ['tenant' => 'hq', 'employee' => $this->employee->id]),
    )->assertRedirect();

    $log = AuditLog::query()->where('action', AuditAction::AkunUnlink)->sole();

    expect($log->before)->toBe(['user_id' => $akun->id])
        ->and($log->after)->toBe(['user_id' => null])
        ->and($log->actor_user_id)->toBe($this->admin->id);
});

it('records an akun.roles.sync audit row with the role sets before and after', function () {
    linkAkunAs();
    $akun = $this->employee->user()->first();
    $akun->assignRole('karyawan');

    $this->actingAs($this->admin)->post(
        route('employees.akun.roles.sync', ['tenant' => 'hq', 'employee' => $this->employee->id]),
        ['roles' => ['karyawan', 'field_staff']],
    )->assertRedirect();

    $log = AuditLog::query()->where('action', AuditAction::AkunRolesSync)->sole();

    expect($log->auditable_id)->toBe((string) $akun->id)
        ->and($log->before['roles'])->toBe(['karyawan'])
        ->and($log->after['roles'])->toEqualCanonicalizing(['karyawan', 'field_staff'])
        ->and($log->actor_user_id)->toBe($this->admin->id);
});

it('records employee.ban and employee.unban audit rows', function () {
    $this->actingAs($this->admin)->post(
        route('employees.ban', ['tenant' => 'hq', 'employee' => $this->employee->id]),
    )->assertRedirect();

    $this->actingAs($this->admin)->post(
        route('employees.unban', ['tenant' => 'hq', 'employee' => $this->employee->id]),
    )->assertRedirect();

    $ban = AuditLog::query()->where('action', AuditAction::EmployeeBan)->sole();
    $unban = AuditLog::query()->where('action', AuditAction::EmployeeUnban)->sole();

    expect($ban->before)->toBe(['banned_at' => null])
        ->and($ban->after['banned_at'])->not->toBeNull()
        ->and($unban->before['banned_at'])->not->toBeNull()
        ->and($unban->after)->toBe(['banned_at' => null])
        ->and($ban->actor_user_id)->toBe($this->admin->id)
        ->and($unban->actor_user_id)->toBe($this->admin->id);
});

it('writes the audit row in the same transaction as the business write', function () {
    // The audit insert runs inside the link transaction: if the audit write
    // fails, the link must roll back with it (atomic).
    DB::listen(function ($query): void {
        if (str_contains($query->sql, 'audit_logs') && str_contains($query->sql, 'insert')) {
            throw new RuntimeException('audit write failed');
        }
    });

    $akun = createUser(['tenant_id' => $this->hq->id]);

    $this->actingAs($this->admin)->post(
        route('employees.akun.link', ['tenant' => 'hq', 'employee' => $this->employee->id]),
        ['user_id' => $akun->id],
    );

    expect($akun->refresh()->employee_id)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

it('never records a failed business write as a clean audit row', function () {
    // Race guard: linking an already-linked employee errors before any write,
    // so no audit row may exist for the attempt.
    linkAkunAs();

    $other = createUser(['tenant_id' => $this->hq->id]);

    $this->actingAs($this->admin)->post(
        route('employees.akun.link', ['tenant' => 'hq', 'employee' => $this->employee->id]),
        ['user_id' => $other->id],
    )->assertSessionHasErrors('user_id');

    expect(AuditLog::query()->where('action', AuditAction::AkunLink)->count())->toBe(1);
});

it('keeps the audit actor on the superadmin and tenant on the employee', function () {
    // Audit rows are tenant data: the row carries the employee's tenant even
    // though the superadmin has no home tenant of their own.
    linkAkunAs();

    $log = AuditLog::query()->sole();

    expect($this->admin->tenant_id)->toBeNull()
        ->and($log->tenant_id)->toBe($this->hq->id);
});
