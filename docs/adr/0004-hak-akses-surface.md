---
status: accepted
---

# Hak Akses surface: role & permission CRUD, rebuilt from zero

Hak Akses (role & permission CRUD) is built fresh on spatie/laravel-permission v8 after discarding the first implementation (archived in git stash `hak-akses-v1 pre-reset archive`). The first build was discarded for three structural defects: controllers authorized `role.edit`/`permission.edit` while the seeder seeded `.update` (a silent deadlock only the super-admin bypass could pass), the role model binding was half-swapped to `App\Models\Role` (Spatie's model leaked into the controller, breaking PHPStan on `is_system`), and the entire frontend half was missing. This ADR records the v2 design; the feature reference is the opti-asset implementation (`D:\Laravel Project\opti-asset`), adapted where noted.

## Decisions

1. **Global, not per-cabang.** Roles and permissions apply across all cabang (Spatie `teams => false`). Routes live outside the `{tenant}` prefix: the Peran surface at `/hak-akses` (moved from `/settings/roles` when the page became the Vault Console; route names `roles.*` unchanged) and Izin at `/settings/permissions`. Role and Permission models must NOT use `BelongsToTenant` — the tenant scope would return nothing whenever no cabang is current. Roles are managed centrally (see ADR-0002 for the tenancy basis).
2. **One vocabulary, per-resource verb map.** Permissions are named `resource.action`. Every resource carries the CRUD verbs `read`, `create`, `update`, `delete`; `work-order` additionally carries the four PRD lifecycle verbs `review`, `assign`, `submit`, `verify`. Any new workflow verb is added to this per-resource map explicitly — a deliberate, reviewed change, never a loophole. Hak-akses permissions are `role.read|create|update|delete` and `permission.read|create|update|delete` — deliberately deviating from opti-asset's `role.view` and `role.edit` so the whole catalogue shares one verb pattern. The batch-create UI therefore offers exactly the four CRUD verb checkboxes.
3. **Consistency test closes the vocabulary bug, in both directions.** One direction: every permission literal used in code (PHP `Gate::authorize`/`can()`, TSX `can()`) must exist in the seeded catalogue. The reverse direction: every seeded permission must carry a verb allowed for its resource by the per-resource verb map. Seeded-but-unused permissions require an explicit forward-looking allowlist entry with a comment (initially the four `work-order` workflow verbs — "gated when the WO flow lands"); an entry comes off the list once its gating is implemented, so unused permissions cannot accumulate silently.
4. **`App\Models\Role` everywhere.** `config/permission.php` maps `models.role` to `App\Models\Role` (extends Spatie's, adds `is_system` bool). The Permission model stays Spatie's stock. Every controller, request class, seeder, and test imports `App\Models\Role` — no Spatie model leaks.
5. **Nine canonical system roles.** `super_admin`, `admin_tenant`, `general_manager`, `deputy_general_manager`, `hod`, `team_leader`, `karyawan`, `field_staff`, `viewer` — marked `is_system` by the seeder. DGM and GM stay separate (deputy is a distinct actor in the HOD fallback and H+6 escalation); `karyawan` stays as the baseline role (Requester is a capability, not a role); `field_staff` is the execution superset of `karyawan` and a field worker holds both roles. `work-order.submit` sits on `karyawan` only — a field worker inherits it through the role pair, keeping the baseline in one place.
6. **Server-side protections.** System roles cannot be deleted or renamed. `super_admin` cannot have permissions synced (its bypass makes them decorative). Non-system roles with assigned users cannot be deleted. All enforced in FormRequests/controllers, not just the UI.
7. **Super admin = bypass, zero DB permissions.** The single source of truth is `User::isSuperAdmin()` (`is_super_admin` flag OR `hasRole('super_admin')`), and `Gate::before` delegates to it. The `super_admin` role is created with zero DB permissions — a synced catalogue would silently drift. Inertia shared props compute ability flags through `can()`/Gate, never through `getAllPermissions()` (a super admin's permission list is empty by design). This also fixes the v1-era `hasRole('super-admin')` hyphen bug in `HandleInertiaRequests`.
8. **Seeder re-run semantics (idempotent, customization-safe).**
   - `forgetCachedPermissions()` before and after.
   - Permissions: `firstOrCreate` — never renames or deletes. A permission deleted via the UI is re-created on the next seed run; harmless, because deletion is blocked while a permission is attached to any role, so nothing re-attaches silently.
   - Roles: `firstOrCreate`; the default permission set is synced only on first creation — re-runs never touch an existing role's permissions, so UI customizations survive. The unique (name, guard_name) index makes `firstOrCreate` adoption of a pre-existing role name deterministic.
   - New permissions for existing roles: never automatic. Each release that adds a catalogue permission grants it to named roles via an explicit step (migration or dedicated command), documented in the seeder header.
   - `is_system` marking is a one-way idempotent set on the nine canonical names.
   - The seeder fails loudly if `auth.super_admin_email` is empty instead of creating a user with a null email. The super admin user bootstrap (flag `is_super_admin`) is kept from the pre-RBAC baseline.
9. **Permission deletion guard.** Deleting a permission attached to at least one role is rejected server-side (the silent-detach caveat from seeder rule reuse is closed at the source).

## Consequences

- User↔role assignment has no UI in this phase; newly created roles become usable only via the next phase (assignment on the Karyawan/Akun side). This is a deliberate, scheduled follow-up, not an omission.
- Adding a permission to an existing role is a deliberate release step, never a side effect of re-seeding.
- Navigation shows "Hak Akses" only when `can('role.read')` via a single shared-prop flag — not a sidebar permission-filtering system.
- Page-access configuration remains out of scope for a later phase.

## References

- v1 archive: git stash `hak-akses-v1 pre-reset archive (superseded by v2 rebuild)`.
- opti-asset role/permission CRUD (`D:\Laravel Project\opti-asset`) — feature reference.
- Spatie Laravel Permission v8 docs — installation, extending, cache, super-admin pattern.
- CONTEXT.md — Hak Akses, System Role, Permission (Izin) glossary.
