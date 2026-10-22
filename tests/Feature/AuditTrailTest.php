<?php

<<<<<<< HEAD
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Seeder;
=======
use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->hq = createTestTenant([
<<<<<<< HEAD
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
=======
        'optigate_company_id' => '9201',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    (new RoleAndPermissionSeeder)->run();
});

afterEach(function () {
    Tenant::forgetCurrent();
});

function auditRoute(Employee $employee, string $name): string
{
    return route($name, [
        'tenant' => 'hq',
        'employee' => $employee->id,
    ]);
}

it('records akun.link with actor, target, tenant and before/after', function () {
    $editor = createUser(['tenant_id' => $this->hq->id]);
    $editor->givePermissionTo('akun.update');

    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs($editor)
        ->post(auditRoute($employee, 'employees.akun.link'), ['user_id' => $akun->id])
        ->assertRedirect();

    $audit = AuditEvent::query()->where('action', 'akun.link')->sole();

    expect($audit->tenant_id)->toBe($this->hq->id)
        ->and($audit->actor_user_id)->toBe($editor->id)
        ->and($audit->actor_email)->toBe($editor->email)
        ->and($audit->subject_type)->toBe(Employee::class)
        ->and($audit->subject_id)->toBe($employee->id)
        ->and($audit->before)->toBe(['user_id' => null])
        ->and($audit->after['user_id'])->toBe($akun->id)
        ->and($audit->after['user_email'])->toBe($akun->email);
});

it('records akun.unlink with the released account', function () {
    $editor = createUser(['tenant_id' => $this->hq->id]);
    $editor->givePermissionTo('akun.update');

    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
    ]);

    $this->actingAs($editor)
        ->delete(auditRoute($employee, 'employees.akun.unlink'))
        ->assertRedirect();

    $audit = AuditEvent::query()->where('action', 'akun.unlink')->sole();

    expect($audit->before['user_id'])->toBe($akun->id)
        ->and($audit->before['user_email'])->toBe($akun->email)
        ->and($audit->after)->toBe(['user_id' => null]);
});

it('records employee.ban and employee.unban around the ban timestamp', function () {
    $admin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs($admin)
        ->post(auditRoute($employee, 'employees.ban'))
        ->assertRedirect();

    $ban = AuditEvent::query()->where('action', 'employee.ban')->sole();

    expect($ban->actor_user_id)->toBe($admin->id)
        ->and($ban->before)->toBe(['banned_at' => null])
        ->and($ban->after['banned_at'])->not->toBeNull();

    $this->actingAs($admin)
        ->post(auditRoute($employee, 'employees.unban'))
        ->assertRedirect();

    $unban = AuditEvent::query()->where('action', 'employee.unban')->sole();

    expect($unban->before['banned_at'])->not->toBeNull()
        ->and($unban->after)->toBe(['banned_at' => null]);
});

it('records akun.roles.sync with the role delta and the akun identity', function () {
    $admin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
    ]);
    $akun->assignRole('karyawan');

    $this->actingAs($admin)
        ->post(auditRoute($employee, 'employees.akun.roles.sync'), ['roles' => ['hod']])
        ->assertRedirect();

    $audit = AuditEvent::query()->where('action', 'akun.roles.sync')->sole();

    expect($audit->before)->toBe(['roles' => ['karyawan']])
        ->and($audit->after)->toBe(['roles' => ['hod']])
        ->and($audit->context['user_id'])->toBe($akun->id)
        ->and($audit->context['user_email'])->toBe($akun->email);
});

it('rolls the action back when the audit row cannot be written', function () {
    $admin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);

    DB::listen(function ($query): void {
        if (str_contains($query->sql, 'audit_events') && str_contains($query->sql, 'insert')) {
            throw new RuntimeException('audit sink unavailable');
        }
    });

    $this->actingAs($admin)
        ->post(auditRoute($employee, 'employees.ban'))
        ->assertStatus(500);

    expect($employee->refresh()->banned_at)->toBeNull()
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('stamps the tenant as data and stays readable without a current tenant', function () {
    $admin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs($admin)
        ->post(auditRoute($employee, 'employees.ban'))
        ->assertRedirect();

    Tenant::forgetCurrent();

    // No tenant global scope (wa_messages pattern): rows stay readable
    // without a current tenant; readers must filter tenant_id explicitly.
    expect(AuditEvent::query()->count())->toBe(1)
        ->and(AuditEvent::query()->where('tenant_id', $this->hq->id)->count())->toBe(1)
        ->and(AuditEvent::query()->where('tenant_id', 'other-tenant')->count())->toBe(0);
});

it('writes an access.denied audit row when the action is denied', function () {
    $editor = createUser(['tenant_id' => $this->hq->id]);
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs($editor)
        ->post(auditRoute($employee, 'employees.ban'))
        ->assertForbidden();

    // FR-6.3 replaces the old "denied actions write nothing" contract:
    // exactly one synthetic access-denied row per blocked request, and
    // no employee.ban row (the denied action never ran).
    expect(AuditEvent::query()->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'access.denied')->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'employee.ban')->count())->toBe(0);
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
});
