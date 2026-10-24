<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Permission;
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
 * verbs review/assign/submit/verify plus the extend flow verbs
 * extend/extend-approve (FR-2.3–2.5).
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
        // Staf admin department (OQ-8, keputusan user 2026-10-24): co-approver
        // permintaan WO lintas-department dari department-nya sendiri.
        // Department binding tetap lewat employees.department_id (pola sama
        // dengan rantai role hod).
        'staf_admin_dept',
        'team_leader',
        'karyawan',
        'field_staff',
        'viewer',
        // Pre-provisioning role: assigned at first SSO login to any account
        // with no role and no linked karyawan (`.scratch/guest-role`). Zero
        // permissions by design — a state marker, not access.
        'guest',
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
        'work-order' => ['review', 'assign', 'submit', 'verify', 'extend', 'extend-approve', 'approve-request'],
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

        // Akun superadmin operasional (superadmin@appdutamall.com). Access is
        // enforced by two cooperating mechanisms, never by the email itself:
        // the is_super_admin flag (TenantAccess / platform surfaces) AND the
        // super_admin Spatie role feeding the Gate::before bypass (ADR-0004).
        $superadmin = User::where('email', $superAdminEmail)->first()
            ?? User::factory()->create([
                'name' => 'Super Admin',
                'email' => $superAdminEmail,
            ]);
        $superadmin->forceFill(['is_super_admin' => true])->save();
        $superadmin->assignRole('super_admin');

        // Link the account to the karyawan record carrying the same email
        // (only when unlinked — a conflicting link is never overwritten).
        if ($superadmin->employee_id === null) {
            $employee = Employee::withEmailAnyTenant($superAdminEmail);

            if ($employee) {
                $superadmin->forceFill(['employee_id' => $employee->getKey()])->save();
            }
        }

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
            'work-order.view',
            // PRD lifecycle verbs (work-order only): review → assign → submit → verify.
            'work-order.review', 'work-order.assign', 'work-order.submit', 'work-order.verify',
            // Extend flow (FR-2.3–2.5): request + approval berjenjang TL → HOD.
            'work-order.extend', 'work-order.extend-approve',
            // Gerbang approval lintas-department (ADR-0011, R2).
            'work-order.approve-request',
        ];

        $workPlanningPermissions = [
            'work-planning.create', 'work-planning.read', 'work-planning.update', 'work-planning.delete',
        ];

        $workDataPermissions = [
            'work-data.create', 'work-data.read', 'work-data.update', 'work-data.delete',
        ];

        $dailyWorkPermissions = [
            'daily-work.create', 'daily-work.read', 'daily-work.update', 'daily-work.delete',
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

        // Gateway WhatsApp per tenant (ADR-0009 amendmen): halaman Pengaturan
        // → WhatsApp hanya punya tampilan (read) + aksi kelola sesi (update);
        // tidak ada objek "whatsapp" yang bisa dibuat/dihapus, sehingga tanpa
        // verb create/delete.
        $whatsappPermissions = [
            'whatsapp.read', 'whatsapp.update',
        ];

        return [
            ...$masterDataPermissions,
            ...$workOrderPermissions,
            ...$workPlanningPermissions,
            ...$workDataPermissions,
            ...$dailyWorkPermissions,
            ...$akunPermissions,
            ...$whatsappPermissions,
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
            'work-order.extend', 'work-order.extend-approve',
        ];

        $workPlanningPermissions = [
            'work-planning.create', 'work-planning.read', 'work-planning.update', 'work-planning.delete',
        ];

        $workDataPermissions = [
            'work-data.create', 'work-data.read', 'work-data.update', 'work-data.delete',
        ];

        $dailyWorkPermissions = [
            'daily-work.create', 'daily-work.read', 'daily-work.update', 'daily-work.delete',
        ];

        // Akun — role assignment on the user record of a linked employee
        // (ADR-0005). create/delete land with the Akun CRUD surface.
        $akunPermissions = [
            'akun.create', 'akun.read', 'akun.update', 'akun.delete',
        ];

        // Gateway WhatsApp per tenant (ADR-0009 amendmen) — read + update saja
        // (lihat catatan serupa di catalogue()); default khusus admin_tenant.
        $whatsappPermissions = [
            'whatsapp.read', 'whatsapp.update',
        ];

        $readMasterData = fn (array $permissions) => array_map(
            fn (string $p) => str_replace(['.create', '.update', '.delete'], '.read', $p),
            $permissions,
        );

        // Viewer stays read-only: readMasterData passes non-CRUD verbs
        // through untouched (that is how viewer ends up with the workflow
        // verbs), but the extend verbs are workflow ACTIONS, not read
        // surface — explicitly excluded.
        $viewerWorkOrderPermissions = array_values(array_diff(
            $readMasterData($workOrderPermissions),
            ['work-order.extend', 'work-order.extend-approve'],
        ));

        return [
            'super_admin' => [],

            'admin_tenant' => [
                ...$masterDataPermissions,
                ...$akunPermissions,
                ...$whatsappPermissions,
                ...$workOrderPermissions,
                'work-order.approve-request',
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
                'work-order.extend', 'work-order.extend-approve',
                // Gerbang approval lintas-department (R2): HOD department
                // pengaju menyetujui/menolak permintaan karyawannya.
                'work-order.approve-request',
                ...$workPlanningPermissions,
                ...$workDataPermissions,
                ...$dailyWorkPermissions,
                'dashboard.read', 'reports.read',
            ],

            // Staf admin department — co-approver permintaan lintas-department
            // dari department-nya (keputusan user, OQ-8). Akses minimal:
            // melihat WO + inbox approval.
            'staf_admin_dept' => [
                'work-order.read', 'work-order.approve-request',
                'dashboard.read',
            ],

            'team_leader' => [
                'division.read', 'department.read', 'employee.read',
                'work-order.read', 'work-order.review', 'work-order.assign',
                'work-order.extend', 'work-order.extend-approve',
                ...$workDataPermissions,
                ...$dailyWorkPermissions,
                'dashboard.read',
            ],

            'karyawan' => [
                'division.read', 'department.read', 'employee.read',
                'work-order.create', 'work-order.read', 'work-order.submit',
                'work-order.extend',
                ...$workDataPermissions,
                'daily-work.read', 'daily-work.update',
                'dashboard.read',
            ],

            'field_staff' => [
                'division.read', 'department.read', 'employee.read',
                'work-order.read',
                'work-order.extend',
                ...$workDataPermissions,
                ...$dailyWorkPermissions,
                'dashboard.read',
            ],

            'viewer' => [
                ...$readMasterData($masterDataPermissions),
                ...$viewerWorkOrderPermissions,
                ...$readMasterData($workPlanningPermissions),
                ...$readMasterData($workDataPermissions),
                ...$readMasterData($dailyWorkPermissions),
                'dashboard.read', 'reports.read',
            ],

            // A brand-new SSO account with no roles and no linked karyawan
            // gets `guest` at first login (LoginProvisioningService). Zero
            // permissions: the super admin grants real roles afterwards.
            'guest' => [],
        ];
    }
}
