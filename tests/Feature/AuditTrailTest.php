<?php

use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->hq = createTestTenant([
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

it('writes no audit row when the action is denied', function () {
    $editor = createUser(['tenant_id' => $this->hq->id]);
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs($editor)
        ->post(auditRoute($employee, 'employees.ban'))
        ->assertForbidden();

    expect(AuditEvent::query()->count())->toBe(0);
});
