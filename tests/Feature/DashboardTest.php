<?php

use App\Models\Tenant;
use App\Models\User;

test('guests are redirected to the sso portal', function () {
    $response = $this->get(route('dashboard', ['tenant' => 'hq']));
    $response->assertRedirect(route('saml.redirect'));
});

test('authenticated users can visit the dashboard', function () {
    $tenant = Tenant::factory()->create([
        'code' => 'hq',
        'optigate_company_id' => 1,
        'name' => 'Headquarters',
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard', ['tenant' => 'hq']));
    $response->assertOk();
});

test('shares only the branches a user may operate', function () {
    $hq = Tenant::factory()->create([
        'code' => 'hq',
        'optigate_company_id' => 1,
        'name' => 'Headquarters',
    ]);
    $plant1 = Tenant::factory()->create([
        'code' => 'plant-1',
        'optigate_company_id' => 2,
        'name' => 'Plant 1',
    ]);

    $branchUser = User::factory()->create(['tenant_id' => $hq->id]);
    $platformUser = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($branchUser)
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('tenants', [['id' => $hq->id, 'name' => 'Headquarters', 'code' => 'hq', 'is_active' => true]])
                ->missing('auth.role')
                ->etc(),
        );

    $this->actingAs($platformUser)
        ->get(route('dashboard', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('tenants', 2)
                ->etc(),
        );
});
