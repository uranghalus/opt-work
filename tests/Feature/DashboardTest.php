<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('guests are redirected to the sso portal', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('saml.redirect'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('shares the primary role for the navigation identity block', function () {
    Role::firstOrCreate(['name' => 'hod', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('hod');
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('auth.role', 'hod')
                ->etc(),
        );
});
