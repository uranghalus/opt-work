<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Config;

it('seeds the operational superadmin account with the super_admin role', function () {
    Config::set('auth.super_admin_email', 'superadmin@appdutamall.com');

    (new RoleAndPermissionSeeder)->run();

    $superadmin = User::where('email', 'superadmin@appdutamall.com')->firstOrFail();

    expect($superadmin->is_super_admin)->toBeTrue()
        ->and($superadmin->hasRole('super_admin'))->toBeTrue()
        // The Gate::before bypass is the single source of power; DB
        // permissions on the role stay decorative (ADR-0004).
        ->and($superadmin->can('role.read'))->toBeTrue()
        ->and($superadmin->can('akun.update'))->toBeTrue();
});

it('lets the seeded superadmin reach a gated surface with zero DB permissions', function () {
    Config::set('auth.super_admin_email', 'superadmin@appdutamall.com');

    (new RoleAndPermissionSeeder)->run();

    $superadmin = User::where('email', 'superadmin@appdutamall.com')->firstOrFail();

    expect($superadmin->permissions->count())->toBe(0)
        ->and($superadmin->roles->count())->toBe(1);

    $this->actingAs($superadmin)
        ->get('/hak-akses')
        ->assertOk();
});

it('seeds no role for the is_super_admin flag alone but still bypasses the gate', function () {
    // The flag and the role are independent mechanisms (ADR-0005): the flag
    // covers platform surfaces, the role feeds the Gate::before bypass.
    $flagOnly = makeSuperAdmin(createUser());

    expect($flagOnly->isSuperAdmin())->toBeTrue()
        ->and($flagOnly->hasRole('super_admin'))->toBeFalse()
        ->and($flagOnly->can('akun.update'))->toBeTrue();
});
