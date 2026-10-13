<?php

use App\Models\Role;
use Spatie\Permission\Contracts\Role as RoleContract;

it('resolves the spatie role class to App\Models\Role', function () {
    expect(config('permission.models.role'))->toBe(Role::class);
    expect(app(RoleContract::class))->toBeInstanceOf(Role::class);
});

it('casts is_system to a boolean defaulting to false', function () {
    $role = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

    expect($role->is_system)->toBeFalse();

    $role->forceFill(['is_system' => true])->save();

    expect($role->fresh()->is_system)->toBeTrue();
});
