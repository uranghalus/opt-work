<?php

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
});

it('uses only permissions the seeder defines', function () {
    $seeded = Permission::pluck('name')->all();

    expect(array_values(array_diff(rbacScanUsedPermissions(), $seeded)))->toBe([]);
});

it('seeds only permissions whose verb is allowed for its resource', function () {
    foreach (Permission::pluck('name') as $name) {
        $resource = Str::beforeLast($name, '.');
        $verb = Str::afterLast($name, '.');

        expect(in_array($verb, rbacAllowedVerbs($resource), true))->toBeTrue(
            "Verb {$verb} is not allowed on resource {$resource} ({$name})"
        );
    }
});

it('covers seeded but unused permissions only via the forward-looking allowlist', function () {
    $seeded = Permission::pluck('name')->all();
    $unused = array_diff($seeded, rbacScanUsedPermissions());

    expect(array_values(array_diff($unused, array_keys(rbacForwardLookingPermissions()))))->toBe([]);
});

it('keeps the forward-looking allowlist honest by failing on gated entries', function () {
    $gated = array_intersect(array_keys(rbacForwardLookingPermissions()), rbacScanUsedPermissions());

    expect(array_values($gated))->toBe([]);
});

/**
 * The per-resource verb map (ADR-0004 §2): every resource carries the CRUD
 * verbs; extra workflow verbs are granted per resource — never globally.
 *
 * @return list<string>
 */
function rbacAllowedVerbs(string $resource): array
{
    return match ($resource) {
        'work-order' => ['read', 'create', 'update', 'delete', 'review', 'assign', 'submit', 'verify', 'extend', 'extend-approve'],
        default => ['read', 'create', 'update', 'delete'],
    };
}

/**
 * Scan the codebase for permission string literals handed to Gate/can checks.
 *
 * @return list<string>
 */
function rbacScanUsedPermissions(): array
{
    $patterns = [
        '/Gate::authorize\(\s*\'([^\']+)\'/',
        '/->can\(\s*\'([^\']+)\'/',
        '/->canAny\(\s*\'([^\']+)\'/',
        '/@can\(\s*\'([^\']+)\'/',
        '/(?<![a-zA-Z0-9_$])can\(\s*\'([^\']+)\'/',
    ];

    $used = [];

    foreach ([app_path(), base_path('routes'), resource_path('js')] as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! in_array($file->getExtension(), ['php', 'ts', 'tsx'], true)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $contents, $matches);
                $used = array_merge($used, $matches[1]);
            }
        }
    }

    return array_values(array_unique($used));
}

/**
 * Permissions seeded ahead of their gating. Every entry needs a reason;
 * entries are removed the moment their gate lands in code, and a stale
 * entry (its permission now used) fails the honesty test.
 *
 * @return array<string, string>
 */
function rbacForwardLookingPermissions(): array
{
    return [
        // work-order — CRUD gating lands with the WO flow, same as the verbs below.
        'work-order.create' => 'gated when the WO flow lands',
        'work-order.read' => 'gated when the WO flow lands',
        'work-order.update' => 'gated when the WO flow lands',
        'work-order.delete' => 'gated when the WO flow lands',
        // work-order workflow verbs — review/assign/submit/verify all
        // gated in WorkOrderController.

        // Master data — gated when master data moves from flags to permissions.
        'division.create' => 'gated when master data moves to permissions',
        'division.read' => 'gated when master data moves to permissions',
        'division.update' => 'gated when master data moves to permissions',
        'division.delete' => 'gated when master data moves to permissions',
        'department.create' => 'gated when master data moves to permissions',
        'department.read' => 'gated when master data moves to permissions',
        'department.update' => 'gated when master data moves to permissions',
        'department.delete' => 'gated when master data moves to permissions',
        'tenant.create' => 'gated when master data moves to permissions',
        'tenant.read' => 'gated when master data moves to permissions',
        'tenant.update' => 'gated when master data moves to permissions',
        'tenant.delete' => 'gated when master data moves to permissions',
        'tenant.create' => 'gated when master data moves to permissions',
        'tenant.read' => 'gated when master data moves to permissions',
        'tenant.update' => 'gated when master data moves to permissions',
        'tenant.delete' => 'gated when master data moves to permissions',

        // Work planning / work data / daily work — gated when those modules land.
        'work-planning.create' => 'gated when the module lands',
        'work-planning.read' => 'gated when the module lands',
        'work-planning.update' => 'gated when the module lands',
        'work-planning.delete' => 'gated when the module lands',
        'work-data.create' => 'gated when the module lands',
        'work-data.read' => 'gated when the module lands',
        'work-data.update' => 'gated when the module lands',
        'work-data.delete' => 'gated when the module lands',
        'daily-work.create' => 'gated when the module lands',
        'daily-work.read' => 'gated when the module lands',
        'daily-work.update' => 'gated when the module lands',

        // akun — role assignment (ADR-0005); update gated in
        // HandleInertiaRequests + EmployeeController, so it is no longer
        // forward-looking. create/delete stay gated when the Akun CRUD
        // surface lands.
        'akun.create' => 'gated when the Akun surface lands',
        'akun.delete' => 'gated when the Akun surface lands',

        // Dashboard / reports — gated when reporting lands.
        'dashboard.read' => 'gated when reporting lands',
        'reports.read' => 'gated when reporting lands',
    ];
}
