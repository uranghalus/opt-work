# TODO - Spatie Multitenancy v4 Migration Progress

## Next session — 2026-10-14 (RBAC v2: hak akses slices 1–5)

### Done today

- [x] RBAC v2 rebuild slices 1–5 green: `App\Models\Role` + is_system migration + config swap; seeder v2 (45 permissions, 9 system roles, idempotent, zero-perm super_admin, fail-loud on empty `auth.super_admin_email`); `User::isSuperAdmin()` + Gate::before + HandleInertiaRequests fix (hyphen→underscore, shared `can` flag, raw permission list removed); RoleController + PermissionController CRUD with server-side guards. Full suite: 264 tests, 256 passed, 8 skipped, 0 failed.
- [x] Two-way permission consistency test: used ⊆ seeded, seeded ⊆ per-resource verb map (CRUD everywhere; review/assign/submit/verify on work-order only), unused ⊆ forward-looking allowlist, stale allowlist entries fail.
- [x] Server-side guards shipped and tested: system roles no delete/rename, super_admin no permission sync, permission delete blocked while attached to a role, non-system roles with users cannot be deleted. Unique (name, guard_name) constraint confirmed present in the Spatie base migration.
- [x] migrate:fresh --seed + second-run idempotency verified: customized hod permissions untouched, is_system only on the 9 canonical roles, super_admin 0 DB permissions, counts stable.
- [x] Docs: ADR 0004 (v2) rewritten, CONTEXT.md/PRODUCT.md aligned to the 9-role list, issue file updated.

### Continue tomorrow

- [ ] Finish slices 6–8: frontend Peran page (table + dialogs + grouped tri-state Sheet), Izin page (table + batch dialog + group filter), nav "Hak Akses" entry gated by the `can('role.read')` shared flag — backend ships with placeholder pages only (S/M per page)
- [ ] User↔role assignment on the Karyawan/Akun side — without it, created roles cannot be used; decide: field workers hold BOTH karyawan and field_staff (M)
- [ ] Per-release "grant default permissions" step (migration or command) — seeder only syncs defaults on first role creation, so new catalogue permissions never reach existing roles (S)
- [ ] Forward-looking allowlist upkeep — remove work-order.review/assign/submit/verify entries as WO gating lands; a stale entry fails the honesty test (S)
- [ ] Start the core WO flow: creation, category decision (Normal / Urgent by Accident / Urgent Request by Owner), HOD review → assign — the hak-akses permissions are in place to gate it (L)
- [ ] Resolve PRD Open Questions blocking the WO flow: Team Leader modeling, Super Admin vs Direksi permissions, deadline ranges, extension count limits (M)

### Deferred (do not start yet)

- [ ] Page-access configuration (agreed: later phase, after CRUD)
- [ ] Sidebar metadata permission-filtering (agreed: out of scope; single shared flag only)
- [ ] Tenant (penyewa) CRUD (v2)
- [ ] External notifications (WA/email gateway)
- [ ] Advanced analytics/reporting

### Known tradeoffs

- [ ] Seeder re-creates permissions deleted via the UI (safe: delete is blocked while attached, so nothing re-attaches silently)
- [ ] Seeder does not push new default permissions to existing roles — handled by the explicit per-release grant step above

---

## Optigate sync — DONE 2026-10-10 (Phase 4 completion)

**Full suite: 143 tests, 135 passed, 8 skipped, 0 failures.** Sync tests: 9/9 with `Http::fake`.

`app:sync-tenants` rewritten from pre-migration legacy to spec:
- Matches on **`optigate_company_id`** (was: API id written as tenant id — would have failed on NOT NULL `code`)
- Sets `code` (lowercased slug) + `name` + `is_active`; landlord-side (no `Tenant::current()` requirement — the old version could never run from the scheduler)
- **Pagination** loop (`paginate=true&per_page=100`, `meta.total` driven, MAX_PAGES guard)
- **Deactivate on disappearance**: `is_active=false` + `deactivated_at=now()` (never delete); **reactivates** when the company reappears; tenants without `optigate_company_id` are untouched
- **Retries**: 3 attempts, growing backoff, on connection errors + 5xx only; 401/403 fail fast with logging
- **Scheduled**: daily 01:00 (`bootstrap/app.php` `withSchedule`)
- **Feature test**: `tests/Feature/SyncTenantsTest.php` — create, update, deactivate, reactivate, idempotency, pagination, 401 fail-fast, 5xx retry (all `Http::fake`)
- Removed dead `config/services_portal.php` (duplicated `services.optigate_portal`, nothing read it)
- Documented `WEB_PORTAL_URL` / `WEB_PORTAL_TOKEN` / `WEB_PORTAL_VERIFY_SSL` in `.env.example`

Follow-up (not started): `.scratch/optigate-sync/issues/01-align-department-employee-syncs.md`

## Completed
- ✅ Replaced manual tenant CRUD with Optigate API sync approach
- ✅ Tenant model uses ULID primary key, `optigate_company_id` (unique), `code` (URL slug)
- ✅ Migration: Restructured tenants table with ULID id, optigate_company_id, code
- ✅ Updated TenantFinder to find by `code` from route parameter
- ✅ Updated TenantAccess service for ULID compatibility
- ✅ Updated TenantSwitchController to use code for routing
- ✅ Updated EnsureTenantAccess middleware for code-based tenant access
- ✅ Fixed all models using BelongsToTenant trait for ULID compatibility
- ✅ Updated controller route redirects to use `tenant()->code` instead of UUID
- ✅ Fixed WorkOrderService to use tenant code for nomor_wo generation
- ✅ Added explicit model binding for workOrder parameter in routes
- ✅ Fixed WorkOrder show/attachment endpoints (removed implicit model binding conflicts)
- ✅ Updated TenantFactory to match new structure (optigate_company_id, code as slug)
- ✅ Updated tests to use new tenant structure (DashboardTest, MultitenancyTest)
- ✅ Fixed QueueTenancyBootstrapper tests for Spatie v4 behavior
- ✅ 2026-10-10: Rewrote `app:sync-tenants` per spec (see "Optigate sync" below)

## DONE — 2026-10-10: Queue Tenant Awareness Fix + Full Suite Green

**Full suite: 134 tests, 126 passed, 8 skipped (intentional 2FA), 0 failures.**

### Root cause of the "queue bug"
The bug was a phantom: `ProbeTenantContext` crashed mid-`handle()` on two v3-era leftovers,
so every query capture below the crash point never ran and `visibleWorkOrders` stayed at its
reset default `[]`:
1. `tenancy()` helper — removed in Spatie v4 (v3-only) → "Call to undefined function"
2. Undeclared static property `$visibleWorkOrdersWithoutScope` → fatal in PHP 8.2+

Tenancy itself worked end to end the whole time: container tenant restored at `JobProcessing`,
scoped query returned exactly `['WO-HQ-1']`. No app-code change, no migration.

### Also fixed (surfaced by the full-suite run, all pre-existing)
- `SamlController::acs()` had a committed `dd($samlUser)` — killed every SAML login AND
  segfaulted the test process (PHP crash dumping the LightSaml object graph). Removed.
- `SamlTest` asserted `route('dashboard')` — stale: dashboard now requires `{tenant}`.
  Fresh SAML users have no tenant → committed behavior sends them to `admin.dashboard`
  (tenant picker). Assertion updated to match.
- `AuthenticationTest` / `DashboardTest` / `NotificationTest`: same stale-route and stale
  tenant-creation fallout from the Spatie migration. Repaired to committed behavior.

### Follow-ups (decisions needed, NOT started)
- `config('fortify.home')` = `/dashboard` points at a dead route — where should password-login
  users land? Tied to the tenant-less-user landing gap below.
- Tenant-less non-admin users land on `admin.dashboard` → 403 (EnsurePlatformTenantAccess).
  Open: `.scratch/superadmin-login/issues/04b-handle-users-without-tenant.md`
- `config/multitenancy.php` lists `\Tests\Support\ProbeTenantContext` in `tenant_aware_jobs` —
  harmless (interface + default-true already cover it) but smells; consider removing.
- Eloquent quirk learned while diagnosing: calling `applyScopes()` then `toSql()` double-applies
  closure global scopes (toSql is a passthru → toBase() → applyScopes again). Production queries
  are unaffected; avoid that chain when debugging SQL.

### Reference
- Scratch issue: `.scratch/tenancy-reconfig/issues/06-enable-queue-tenancy-bootstrapper.md`
- Vault plan: `Planning/2026-10-10-queue-tenant-awareness-fix` (status: done)

## Files Modified
- app/Models/Tenant.php
- database/migrations/2026_10_09_121502_restructure_tenants_table.php
- app/TenantFinder.php
- app/Services/TenantAccess.php
- app/Http/Controllers/TenantSwitchController.php
- app/Http/Controllers/EnsureTenantAccess.php
- app/Http/Controllers/WorkOrderController.php
- app/Services/WorkOrderService.php
- app/Http/Controllers/SamlController.php  ← 2026-10-10: removed committed dd() in acs()
- routes/web.php
- database/factories/TenantFactory.php
- config/multitenancy.php
- tests/Feature/DashboardTest.php  ← 2026-10-10: stale dashboard route + tenant-less user fixes
- tests/Feature/MultitenancyTest.php
- tests/Feature/QueueTenancyBootstrapperTest.php  ← 2026-10-10: rewritten (see DONE section)
- tests/Feature/AuthenticationTest.php  ← 2026-10-10: stale dashboard route fix
- tests/Feature/Auth/SamlTest.php  ← 2026-10-10: stale redirect assertion fix
- tests/Feature/NotificationTest.php  ← 2026-10-10: tenants table required-field fix
- tests/Support/ProbeTenantContext.php  ← 2026-10-10: removed v3-era helpers, declared statics
- tests/Pest.php (createTestTenant, initTenant helpers)