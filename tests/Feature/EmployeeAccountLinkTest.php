<?php

use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;

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

function accountLinkRoute(Employee $employee, string $name = 'employees.akun.link'): string
{
    return route($name, [
        'tenant' => 'hq',
        'employee' => $employee->id,
    ]);
}

function accountEditor(array $permissions = ['akun.update']): User
{
    $user = createUser(['tenant_id' => Tenant::current()?->id]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('links an unlinked account from the same cabang', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($akun->fresh()->employee_id)->toBe($employee->id);
});

it('rejects an account from another cabang', function () {
    $other = createTestTenant([
        'optigate_company_id' => '9002',
        'code' => 'plant-1',
        'name' => 'Plant 1',
    ]);
    initTenant('hq');

    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $other->id]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertSessionHasErrors('user_id');

    expect($akun->fresh()->employee_id)->toBeNull();
});

it('rejects an account already linked to another employee', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $otherEmployee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertSessionHasErrors('user_id');

    expect($akun->fresh()->employee_id)->toBe($otherEmployee->id);
});

it('rejects the platform super admin account', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $superadmin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $superadmin->id])
        ->assertSessionHasErrors('user_id');

    expect($superadmin->fresh()->employee_id)->toBeNull();
});

it('rejects an account carrying the super_admin role', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);
    $akun->assignRole('super_admin');

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertSessionHasErrors('user_id');

    expect($akun->fresh()->employee_id)->toBeNull();
});

it('rejects linking when the employee already has an account', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $employee->id,
    ]);
    $candidate = User::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $candidate->id])
        ->assertSessionHasErrors('user_id');

    expect($candidate->fresh()->employee_id)->toBeNull();
});

it('unlinks the account without deleting it or its roles', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $employee->id,
    ]);
    $akun->assignRole('hod');

    $this->actingAs(accountEditor())
        ->delete(accountLinkRoute($employee, 'employees.akun.unlink'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($akun->fresh())
        ->employee_id->toBeNull()
        ->and($akun->fresh()->hasRole('hod'))->toBeTrue();
});

it('refuses to unlink a super admin account', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $superadmin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));
    $superadmin->forceFill(['employee_id' => $employee->id])->save();

    $this->actingAs(accountEditor())
        ->delete(accountLinkRoute($employee, 'employees.akun.unlink'))
        ->assertSessionHasErrors('akun');

    expect($superadmin->fresh()->employee_id)->toBe($employee->id);
});

it('requires akun.update for link and unlink', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);
    $viewer = createUser(['tenant_id' => $this->hq->id]);

    $this->actingAs($viewer)
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(accountLinkRoute($employee, 'employees.akun.unlink'))
        ->assertForbidden();
});

it('prevents duplicate employee links at the database level', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $employee->id,
    ]);

    expect(fn () => User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $employee->id,
    ]))->toThrow(QueryException::class);
});

<<<<<<< HEAD
it('offers only unlinked same-cabang accounts via the search endpoint (Q6)', function () {
=======
it('offers only unlinked same-cabang accounts through akun/search', function () {
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $available = User::factory()->create([
        'tenant_id' => $this->hq->id,
        'name' => 'Akun Tersedia',
        'email' => 'available@example.com',
    ]);

    $linkedEmployee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'employee_id' => $linkedEmployee->id,
        'email' => 'linked@example.com',
    ]);

    $other = createTestTenant([
        'optigate_company_id' => '9002',
        'code' => 'plant-1',
        'name' => 'Plant 1',
    ]);
    initTenant('hq');
    User::factory()->create([
        'tenant_id' => $other->id,
        'email' => 'other@example.com',
    ]);

    $superadmin = makeSuperAdmin(createUser(['tenant_id' => $this->hq->id]));
    $superadmin->update(['email' => 'superadmin@example.com']);

    $editor = accountEditor(['employee.read', 'akun.read', 'akun.update']);
    // The editor's own account is a valid tenant account, so link it to a
    // different karyawan to keep this fixture's candidate set deterministic.
    $editor->forceFill([
        'employee_id' => Employee::factory()->create(['tenant_id' => $this->hq->id])->id,
    ])->save();

    $this->actingAs($editor)
        ->get(route('employees.akun.search', ['tenant' => 'hq', 'employee' => $employee->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', $available->email);

    // The show payload no longer bakes in the static candidate list.
    $this->actingAs($editor)
        ->get(route('employees.show', ['tenant' => 'hq', 'employee' => $employee->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/show')
            ->where('employee.has_account', false));

    // Candidates now come from the paginated akun/search endpoint, which
    // applies the same constraints linkAkun does.
    $this->actingAs($editor)
        ->get(accountLinkRoute($employee, 'employees.akun.search'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', $available->email);
});

it('provisions the zero-permission guest role when linking a roleless account', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    $audit = AuditEvent::query()->where('action', 'akun.link')->sole();

    // Q4 + ADR-0008 amendment: the link itself provisions `guest`, so the
    // account clears LoginLanding's no_role gate with zero permissions.
    expect($akun->fresh()->getRoleNames()->all())->toBe(['guest'])
        ->and(Role::findByName('guest')->permissions)->toHaveCount(0)
        ->and($audit->after['auto_role'])->toBe('guest');
});

it('leaves the roles of an account that already has one untouched when linking', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);
    $akun->assignRole('karyawan');

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    $audit = AuditEvent::query()->where('action', 'akun.link')->sole();

    expect($akun->fresh()->getRoleNames()->all())->toBe(['karyawan'])
        ->and($audit->after)->not->toHaveKey('auto_role');
});

it('keeps a provisioned guest through unlink and lands it on the dashboard at login', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);
    $editor = accountEditor();

    $this->actingAs($editor)
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertSessionHas('success');

    $this->actingAs($editor)
        ->delete(accountLinkRoute($employee, 'employees.akun.unlink'))
        ->assertSessionHas('success');

    expect($akun->fresh()->getRoleNames()->all())->toBe(['guest']);

    // Drop the editor's in-memory auth so login.store below runs as a
    // genuine guest (otherwise RedirectIfAuthenticated bounces it).
    $this->app['auth']->forgetGuards();

    // E2E: the provisioned guest clears the no_role gate and lands on the
    // dashboard (permitted routes stay closed — izin nol).
    $this->post(route('login.store'), [
        'email' => $akun->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', ['tenant' => 'hq']));

    $this->get(route('dashboard', ['tenant' => 'hq']))->assertOk();

    expect($akun->refresh()->getAllPermissions()->count())->toBe(0);
});

it('filters akun/search candidates by name or email', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'name' => 'Budi Santoso',
        'email' => 'budi.santoso@example.com',
    ]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'name' => 'Siti Rahayu',
        'email' => 'siti.rahayu@example.com',
    ]);

    $this->actingAs(accountEditor())
        ->getJson(accountLinkRoute($employee, 'employees.akun.search').'?q=Budi')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'budi.santoso@example.com');

    $this->actingAs(accountEditor())
        ->getJson(accountLinkRoute($employee, 'employees.akun.search').'?q=rahayu')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'siti.rahayu@example.com');
});

it('paginates akun/search beyond a hundred candidates', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->count(105)->create(['tenant_id' => $this->hq->id]);

    // Keep the editor out of the candidate set so the counts stay exact.
    $editor = accountEditor();
    $editor->forceFill([
        'employee_id' => Employee::factory()->create(['tenant_id' => $this->hq->id])->id,
    ])->save();

    $first = $this->actingAs($editor)
        ->getJson(accountLinkRoute($employee, 'employees.akun.search'))
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('total', 105)
        ->assertJsonPath('last_page', 11)
        ->assertJsonPath('current_page', 1);

    $firstEmail = $first->json('data.0.email');

    $second = $this->actingAs($editor)
        ->getJson(accountLinkRoute($employee, 'employees.akun.search').'?page=2')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('current_page', 2);

    expect(collect($second->json('data'))->pluck('email')->all())
        ->not->toContain($firstEmail);
});

it('requires akun.update for akun/search', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $viewer = createUser(['tenant_id' => $this->hq->id]);

    $this->actingAs($viewer)
        ->getJson(accountLinkRoute($employee, 'employees.akun.search'))
        ->assertForbidden();
});

it('filters linkable accounts by the search term server-side (Q6)', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
    ]);
    User::factory()->create([
        'tenant_id' => $this->hq->id,
        'name' => 'Siti Aminah',
        'email' => 'siti@example.com',
    ]);

    $this->actingAs(accountEditor())
        ->get(route('employees.akun.search', ['tenant' => 'hq', 'employee' => $employee->id]).'?search=budi')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'budi@example.com');
});

it('paginates linkable accounts beyond the old 100-item cap (Q6)', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);

    User::factory()->count(12)->create(['tenant_id' => $this->hq->id]);

    // The editor's own account is unlinked by default; link it so the
    // candidate set is exactly the 12 factory accounts.
    $editor = accountEditor();
    $editor->forceFill([
        'employee_id' => Employee::factory()->create(['tenant_id' => $this->hq->id])->id,
    ])->save();

    $this->actingAs($editor)
        ->get(route('employees.akun.search', ['tenant' => 'hq', 'employee' => $employee->id]))
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('total', 12);
});

it('requires akun.update for the account search endpoint (Q6)', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $viewer = createUser(['tenant_id' => $this->hq->id]);
    $viewer->givePermissionTo('employee.read');

    $this->actingAs($viewer)
        ->get(route('employees.akun.search', ['tenant' => 'hq', 'employee' => $employee->id]))
        ->assertForbidden();
});

it('auto-assigns karyawan when linking a roleless account (Q4)', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);

    expect($akun->getRoleNames())->toBeEmpty();

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    $akun->refresh();
    expect($akun->employee_id)->toBe($employee->id)
        ->and($akun->getRoleNames()->all())->toBe(['karyawan']);
});

it('never touches the roles of an account that already has one (Q4)', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);
    $akun->assignRole('viewer');

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id])
        ->assertRedirect();

    expect($akun->fresh()->getRoleNames()->all())->toBe(['viewer']);
});

it('keeps the auto-assigned karyawan role after unlink', function () {
    // Unlink removes the link only; the provisioning baseline survives so a
    // relink never re-grants behind anyone's back.
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id]);

    $this->actingAs(accountEditor())
        ->delete(accountLinkRoute($employee, 'employees.akun.unlink'))
        ->assertRedirect();

    $akun->refresh();
    expect($akun->employee_id)->toBeNull()
        ->and($akun->getRoleNames()->all())->toBe(['karyawan']);
});

it('lets a linked-and-provisioned akun land on the dashboard (Q4 end-to-end)', function () {
    // The point of Q4: linking must not produce an account that dies at the
    // no_role gate on its next login.
    $employee = Employee::factory()->create(['tenant_id' => $this->hq->id]);
    $akun = User::factory()->create(['tenant_id' => $this->hq->id]);

    $this->actingAs(accountEditor())
        ->post(accountLinkRoute($employee), ['user_id' => $akun->id]);

    // Drop the editor's session AND resolved guards so the login request
    // runs as a guest (actingAs pins the user on the guard instance).
    $this->flushSession();
    $this->app['auth']->forgetGuards();

    $this->post(route('login.store'), [
        'email' => $akun->fresh()->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', ['tenant' => 'hq']));

    $this->assertAuthenticatedAs($akun->fresh());
});
