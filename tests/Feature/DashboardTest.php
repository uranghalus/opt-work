<?php

use App\Models\Tenant;
use App\Models\User;

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

test('shares only the branches a user may operate', function () {
    Tenant::query()->firstOrCreate(['id' => 'hq']);
    Tenant::query()->firstOrCreate(['id' => 'plant-1']);

    $branchUser = User::factory()->create(['tenant_id' => 'hq']);
    $platformUser = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($branchUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('tenants', [['id' => 'hq', 'name' => 'hq', 'code' => null, 'is_active' => true]])
                ->missing('auth.role')
                ->etc(),
        );

    $this->actingAs($platformUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('tenants', 2)
                ->etc(),
        );
});
