# Hak Akses v2: Role & Permission CRUD (rebuild)

**Status:** Backend done (slices 1–5, TDD green). Frontend + nav pending (slices 6–8).
**Origin:** Grilling sessions 2026-10-13 (rounds 1–3, all settled) + ADR `docs/adr/0004-hak-akses-surface.md` (v2)
**Predecessor:** v1 discarded (git stash `hak-akses-v1 pre-reset archive`) — gate vocabulary mismatch, half-done model swap, missing frontend.

## Settled decisions

- Global roles, `teams => false`; `/settings/roles` + `/settings/permissions` outside `{tenant}` prefix; models never `BelongsToTenant`.
- Vocabulary: `resource.action`, closed verbs `read|create|update|delete`; hak-akses perms = `role.read/create/update/delete` + `permission.read/create/update/delete`.
- **Consistency test**: every permission literal in PHP (`Gate::authorize`, `can(`) and TSX (`can(`) must exist in the seeded catalogue AND match the verb set.
- `config/permission.models.role => App\Models\Role` (is_system bool); Permission model = Spatie stock; `App\Models\Role` imported everywhere.
- 9 system roles: `super_admin, admin_tenant, general_manager, deputy_general_manager, hod, team_leader, karyawan, field_staff, viewer` (`is_system` = true, server-side no delete/rename; field worker holds karyawan + field_staff).
- `super_admin`: zero DB permissions; bypass = `User::isSuperAdmin()` (flag OR role) via `Gate::before`; shared-props ability flags via `can()`, never `getAllPermissions()`; fixes HEAD's `hasRole('super-admin')` hyphen bug.
- Seeder: cache forget before+after; permissions `firstOrCreate` (resurrect caveat OK — delete is blocked while attached); role default sync ONLY on first creation; new-permission grants = explicit per-release step; `is_system` one-way marking; fail loudly on empty `auth.super_admin_email`; keep superadmin user bootstrap.
- Permission delete blocked server-side while attached to ≥1 role.
- Non-system roles with assigned users cannot be deleted (server-side).
- Role↔permission sync endpoint validates `exists:permissions,name` (422, never Spatie exception).
- Out of scope: user↔role assignment UI (**immediate next phase** — Karyawan/Akun side), page-access config, sidebar permission-filtering metadata.

## Slices

1. Model + config + migration — `App\Models\Role` (is_system cast + @property), config swap, migration add `is_system` — ✅ DONE
2. Seeder v2 — catalogue minus `rbac.manage`/`tenant.manage`, hak-akses `.read` perms, 9 roles idempotent, zero-perm super_admin, fail-loud email, single-source verb map (`BASE_VERBS`/`RESOURCE_VERBS`/`allowedVerbs()`) — ✅ DONE
3. Gate layer — `User::isSuperAdmin()`, `Gate::before`, HandleInertiaRequests hyphen fix + `can('role.read')` shared flag, two-way consistency test + forward-looking allowlist — ✅ DONE
4. RoleController — index/store/update/destroy/syncPermissions + FormRequests + system-role guards — ✅ DONE (placeholder page file exists)
5. PermissionController — index/store(batch)/update/destroy + attached-role guard + FormRequests — ✅ DONE (placeholder page file exists)
6. Frontend Peran page — table + create/edit dialogs + grouped tri-state Sheet + badges (Sistem, pengguna, Bypass semua gate) — ⏳ PENDING
7. Frontend Izin page — table + batch dialog (4 fixed verbs) + group filter — ⏳ PENDING
8. Nav entry — "Hak Akses" → roles, gated by `role.read` shared flag; "Kelola Izin" link — ⏳ PENDING

## References

- `D:\Laravel Project\opti-asset` — RoleController/PermissionController/FormRequests/pages/tests (PHPUnit there; here Pest).
- Spatie Laravel Permission v8 docs (spatie.be/docs/laravel-permission/v8).
- ADR 0004 (v2) for full rationale.
