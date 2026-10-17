# 04b: Handle Users Without Tenant (Edge Case)

**What to build:** Handle superadmin with no home tenant - redirect to `/admin` with tenant list.

**Blocked by:** 04 (same controller change)

**Status:** done 2026-10-17 (closed by commit `74d2a6c` — role-aware login)

- [x] Platform accounts (super admin, flag or role) are never denied → land on `admin.dashboard` (unit bisnis picker) — `app/Services/LoginLanding.php`
- [x] Non-admin user with roles but no home tenant → session ended, denied with reason `no_tenant` (previously landed on `admin.dashboard` → 403)
- [x] User with no role at all → session ended, denied with reason `no_role`
- [x] Covered by `tests/Feature/Rbac/RoleAwareLoginTest.php` (6 tests)