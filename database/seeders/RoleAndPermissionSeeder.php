<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Canonical RBAC seed (see ADR-0004 hak-akses surface).
 *
 * Re-run semantics:
 * - Permissions are firstOrCreate: never renamed, never deleted. A permission
 *   deleted via Hak Akses is re-created here (it could only be deleted while
 *   unattached, so nothing silently re-attaches).
 * - Role defaults sync ONLY on first creation: re-runs never touch an existing
 *   role's permissions, so Hak Akses customizations survive.
 * - New permissions for existing roles are granted by the per-release command
 *   app:grant-default-permissions, never by making this seeder smarter.
 *
 * Verb map (enforced two-way by the consistency test): every resource carries
 * read/create/update/delete; work-order additionally carries the PRD lifecycle
 * verbs review/assign/submit/verify.
 */
class RoleAndPermissionSeeder extends Seeder
{
    /**
     * The nine canonical system roles, in PRD order.
     *
     * @var list<string>
     */
    public const SYSTEM_ROLES = [
        'super_admin',
        'admin_tenant',
        'general_manager',
        'deputy_general_manager',
        'hod',
        'team_leader',
        'karyawan',
        'field_staff',
        'viewer',
    ];

    /**
     * Verbs every resource may carry (ADR-0004 §2).
     *
     * @var list<string>
     */
    public const BASE_VERBS = ['read', 'create', 'update', 'delete'];

    /**
     * Extra workflow verbs granted per resource — never globally.
     *
     * @var array<string, list<string>>
     */
    public const RESOURCE_VERBS = [
        'work-order' => ['review', 'assign', 'submit', 'verify'],
    ];

    /**
     * The full allowed verb list for a resource.
     *
     * @return list<string>
     */
    public static function allowedVerbs(string $resource): array
    {
        return [...self::BASE_VERBS, ...self::RESOURCE_VERBS[$resource] ?? []];
    }

    public function run(): void
    {
        $superAdminEmail = config('auth.super_admin_email');

        if (empty($superAdminEmail)) {
            throw new RuntimeException('config(auth.super_admin_email) is empty: refusing to seed a super admin user with a null email.');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::catalogue() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::roleDefaults() as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($permissions);
            }
        }

        Role::whereIn('name', self::SYSTEM_ROLES)->update(['is_system' => true]);

        User::where('email', 'admin@example.com')->first()?->forceFill(['is_super_admin' => true])->save();

        // Akun superadmin operasional. Akses lintas cabang datang dari flag
        // is_super_admin (TenantAccess); peran super_admin sengaja nol izin —
        // bypass di Gate adalah satu-satunya sumber kekuasaannya.
        $superadmin = User::where('email', $superAdminEmail)->first()
            ?? User::factory()->create([
                'name' => 'Super Admin',
                'email' => $superAdminEmail,
            ]);
        $superadmin->forceFill(['is_super_admin' => true])->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The full permission catalogue: resource.action, closed verb map.
     *
     * Shared with app:grant-default-permissions — the per-release step reads
     * the same catalogue and default map.
     *
     * @return list<string>
     */
    public static function catalogue(): array
    {
        $masterDataPermissions = [
            'division.create', 'division.read', 'division.update', 'division.delete',
            'department.create', 'department.read', 'department.update', 'department.delete',
            'employee.read',
            'tenant.create', 'tenant.read', 'tenant.update', 'tenant.delete',
        ];

        $workOrderPermissions = [
            'work-order.create', 'work-order.read', 'work-order.update', 'work-order.delete',
            // PRD lifecycle verbs (work-order only): review → assign → submit → verify.
            'work-order.review', 'work-order.assign', 'work-order.submit', 'work-order.verify',
        ];

        $workPlanningPermissions = [
            'work-planning.create', 'work-planning.read', 'work-planning.update', 'work-planning.delete',
        ];

        $workDataPermissions = [
            'work-data.create', 'work-data.read', 'work-data.update', 'work-data.delete',
        ];

        $dailyWorkPermissions = [
            'daily-work.create', 'daily-work.read', 'daily-work.update',
        ];

        $hakAksesPermissions = [
            'role.create', 'role.read', 'role.update', 'role.delete',
            'permission.create', 'permission.read', 'permission.update', 'permission.delete',
        ];

        // Akun — role assignment on the user record of a linked employee
        // (ADR-0005). create/delete land with the Akun CRUD surface.
        $akunPermissions = [
            'akun.create', 'akun.read', 'akun.update', 'akun.delete',
        ];

        return [
            ...$masterDataPermissions,
            ...$workOrderPermissions,
            ...$workPlanningPermissions,
            ...$workDataPermissions,
            ...$dailyWorkPermissions,
            ...$akunPermissions,
            'dashboard.read',
            'reports.read',
            ...$hakAksesPermissions,
        ];
    }

    /**
     * Default permission sets, synced only when a role is first created.
     * super_admin stays empty: its Gate bypass is the single source of power.
     *
     * Shared with app:grant-default-permissions — the per-release step grants
     * any default missing from an existing role (additive-only).
     *
     * @return array<string, list<string>>
     */
    public static function roleDefaults(): array
    {
        $masterDataPermissions = [
            'division.create', 'division.read', 'division.update', 'division.delete',
            'department.create', 'department.read', 'department.update', 'department.delete',
            'employee.read',
            'tenant.create', 'tenant.read', 'tenant.update', 'tenant.delete',
        ];

        $workOrderPermissions = [
            'work-order.create', 'work-order.read', 'work-order.update', 'work-order.delete',
            'work-order.review', 'work-order.assign', 'work-order.submit', 'work-order.verify',
        ];

        $workPlanningPermissions = [
            'work-planning.create', 'work-planning.read', 'work-planning.update', 'work-planning.delete',
        ];

        $workDataPermissions = [
            'work-data.create', 'work-data.read', 'work-data.update', 'work-data.delete',
        ];

        $dailyWorkPermissions = [
            'daily-work.create', 'daily-work.read', 'daily-work.update',
        ];

        // Akun — role assignment on the user record of a linked employee
        // (ADR-0005). create/delete land with the Akun CRUD surface.
        $akunPermissions = [
            'akun.create', 'akun.read', 'akun.update', 'akun.delete',
        ];

        $readMasterData = fn (array $permissions) => array_map(
            fn (string $p) => str_replace(['.create', '.update', '.delete'], '.read', $p),
            $permissions,
        );

        return [
            'super_admin' => [],

            'admin_tenant' => [
                ...$masterDataPermissions,
                ...$akunPermissions,
                ...$workOrderPermissions,
                ...$workPlanningPermissions,
                ...$workDataPermissions,
                ...$dailyWorkPermissions,
                'dashboard.read', 'reports.read',
            ],

            'general_manager' => [
                ...$readMasterData($masterDataPermissions),
                'work-order.read', 'work-order.review', 'work-order.verify',
                ...$workPlanningPermissions,
                ...$workDataPermissions,
                ...$dailyWorkPermissions,
                'dashboard.read', 'reports.read',
            ],

            'deputy_general_manager' => [
                ...$readMasterData($masterDataPermissions),
                'work-order.read', 'work-order.review', 'work-order.verify',
                ...$workPlanningPermissions,
                ...$workDataPermissions,
                ...$dailyWorkPermissions,
                'dashboard.read', 'reports.read',
            ],

            'hod' => [
                'division.read', 'department.read', 'department.update', 'employee.read',
                'work-order.read', 'work-order.review', 'work-order.assign', 'work-order.verify',
                ...$workPlanningPermissions,
                ...$workDataPermissions,
                ...$dailyWorkPermissions,
                'dashboard.read', 'reports.read',
            ],

            'team_leader' => [
                'division.read', 'department.read', 'employee.read',
                'work-order.read', 'work-order.review', 'work-order.assign',
                ...$workDataPermissions,
                ...$dailyWorkPermissions,
                'dashboard.read',
            ],

            'karyawan' => [
                'division.read', 'department.read', 'employee.read',
                'work-order.create', 'work-order.read', 'work-order.submit',
                ...$workDataPermissions,
                'daily-work.read', 'daily-work.update',
                'dashboard.read',
            ],

            'field_staff' => [
                'division.read', 'department.read', 'employee.read',
                'work-order.read',
                ...$workDataPermissions,
                ...$dailyWorkPermissions,
                'dashboard.read',
            ],

            'viewer' => [
                ...$readMasterData($masterDataPermissions),
                ...$readMasterData($workOrderPermissions),
                ...$readMasterData($workPlanningPermissions),
                ...$readMasterData($workDataPermissions),
                ...$readMasterData($dailyWorkPermissions),
                'dashboard.read', 'reports.read',
            ],
        ];
    }
}
