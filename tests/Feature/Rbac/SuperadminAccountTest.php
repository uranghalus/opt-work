<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Role;

/**
 * Platform account invariants (`.scratch/guest-role` issue 03):
 *
 * Root cause recorded in the diagnosis: the `super_admin` role was present,
 * but nothing ever linked the account to its karyawan record
 * (`users.employee_id`), so the Karyawan page showed "Belum ada akun yang
 * terhubung" for the super admin's own employee record.
 */
beforeEach(function () {
    config()->set('auth.super_admin_email', 'platform-superadmin@optigate.test');

    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('seeds the platform account with flag, role, and a link to its karyawan', function () {
    initTenant('hq');
    $employee = Employee::create([
        'nik_employee' => 'NIK-SUPER',
        'nama_employee' => 'Super Admin',
        'email' => config('auth.super_admin_email'),
    ]);

    // Seeding runs without a current tenant (production reality): the link
    // lookup must still find the karyawan record.
    Tenant::forgetCurrent();

    (new RoleAndPermissionSeeder)->run();

    $superadmin = User::query()->where('email', config('auth.super_admin_email'))->sole();

    expect($superadmin->is_super_admin)->toBeTrue()
        ->and($superadmin->getRoleNames()->all())->toBe(['super_admin'])
        ->and($superadmin->fresh()->employee_id)->toBe($employee->getKey());
});

it('is idempotent across seeder re-runs and never duplicates the pivot', function () {
    initTenant('hq');
    Employee::create([
        'nik_employee' => 'NIK-SUPER',
        'nama_employee' => 'Super Admin',
        'email' => config('auth.super_admin_email'),
    ]);

    (new RoleAndPermissionSeeder)->run();
    (new RoleAndPermissionSeeder)->run();

    $superadmin = User::query()->where('email', config('auth.super_admin_email'))->sole();

    expect($superadmin->getRoleNames()->all())->toBe(['super_admin'])
        ->and(Role::findByName('super_admin')->users()->count())->toBe(1);
});

it('lets the platform account through a gated route', function () {
    (new RoleAndPermissionSeeder)->run();

    $superadmin = User::query()->where('email', config('auth.super_admin_email'))->sole();

    $this->actingAs($superadmin)
        ->get('/hak-akses')
        ->assertOk();
});

it('repairs an existing database through the one-off command', function () {
    initTenant('hq');
    $employee = Employee::create([
        'nik_employee' => 'NIK-SUPER',
        'nama_employee' => 'Super Admin',
        'email' => config('auth.super_admin_email'),
    ]);

    // An existing database where the account exists with the role but was
    // never linked (the diagnosed state).
    (new RoleAndPermissionSeeder)->run();
    User::query()->where('email', config('auth.super_admin_email'))
        ->sole()
        ->forceFill(['employee_id' => null, 'is_super_admin' => false])
        ->save();

    $this->artisan('app:grant-super-admin-role')
        ->assertSuccessful();

    $superadmin = User::query()->where('email', config('auth.super_admin_email'))->sole();

    expect($superadmin->is_super_admin)->toBeTrue()
        ->and($superadmin->fresh()->employee_id)->toBe($employee->getKey())
        ->and($superadmin->getRoleNames()->all())->toBe(['super_admin']);
});

it('changes nothing in dry-run mode', function () {
    initTenant('hq');
    Employee::create([
        'nik_employee' => 'NIK-SUPER',
        'nama_employee' => 'Super Admin',
        'email' => config('auth.super_admin_email'),
    ]);

    (new RoleAndPermissionSeeder)->run();
    User::query()->where('email', config('auth.super_admin_email'))
        ->sole()
        ->forceFill(['employee_id' => null, 'is_super_admin' => false])
        ->save();

    $this->artisan('app:grant-super-admin-role', ['--dry-run' => true])
        ->assertSuccessful();

    $superadmin = User::query()->where('email', config('auth.super_admin_email'))->sole();

    expect($superadmin->fresh()->is_super_admin)->toBeFalse()
        ->and($superadmin->fresh()->employee_id)->toBeNull();
});
