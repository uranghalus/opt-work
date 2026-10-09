<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Shared test helpers
|--------------------------------------------------------------------------
|
| Available to every test file so tenant/permission setup is not repeated.
|
*/

/**
 * Create a test tenant with the new structure (synced from Optigate API).
 * optigate_company_id is required and unique.
 * code is used as URL slug.
 * id is ULID (auto-generated).
 */
function createTestTenant(array $attributes = []): Tenant
{
    static $counter = 1;
    
    $defaults = [
        'optigate_company_id' => $counter++,
        'code' => 'test-'.str()->lower(class_basename($attributes['name'] ?? 'tenant')),
        'name' => 'Test Tenant',
        'is_active' => true,
    ];
    
    $tenant = Tenant::create(array_merge($defaults, $attributes));
    $tenant->makeCurrent();
    
    return $tenant;
}

function initTenant(string $tenantCode): Tenant
{
    $tenant = Tenant::where('code', $tenantCode)->firstOrFail();
    $tenant->makeCurrent();
    return $tenant;
}

function createUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

/**
 * Platform-level user: may operate any branch.
 *
 * Replaces the `super_admin` Spatie role, which is deferred along with the rest
 * of RBAC. See `.scratch/tenancy-reconfig/issues/02`.
 */
function makeSuperAdmin(User $user): User
{
    $user->forceFill(['is_super_admin' => true])->save();

    return $user;
}

/** Department head, for the notification fallback chain. */
function makeHod(User $user): User
{
    $user->forceFill(['is_hod' => true])->save();

    return $user;
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
