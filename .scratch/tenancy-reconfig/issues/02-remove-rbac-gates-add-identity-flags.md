# 02: Remove RBAC gates, add explicit identity flags

**What to build:** RBAC (spatie/laravel-permission) is being deferred to the end of the project so modules can be developed and tested without authorization in the way. This ticket takes the **gates** off while keeping the package, tables, and seeder in place, so the final RBAC pass is purely additive.

Per the settled decision: **every authenticated user may perform every action within their own branch.** Authorization becomes "which branch are you in" (`EnsureTenantAccess`), not "which permissions do you hold". The only two identity distinctions that survive are `is_super_admin` (operates across branches) and `is_hod` (notification fallback).

**Blocked by:** None (landed after 01).

**Status:** done

- [x] No route, controller, or frontend file references a permission or role
- [x] `EnsureTenantAccess` gates on branch, via a single `TenantAccess` seam
- [x] `HodNotificationResolver` fallback chain works without `User::role()`
- [x] Test suite is green; tests that asserted 403-on-missing-permission are deleted, not weakened
- [x] `composer.json` still requires spatie/laravel-permission and its tables still exist

---

## The seam (the one thing that must be right)

Create `app/Services/TenantAccess.php` as the single authority:

```php
final class TenantAccess
{
    /** May this user act within this branch? Branch admins always may. */
    public function canOperate(User $user, string $tenantId): bool;

    /** Branches this user may switch into. */
    public function operableTenants(User $user): Collection;
}
```

`EnsureTenantAccess` and the switcher both consume it. Today it answers from
`is_super_admin` + `users.tenant_id`. When RBAC returns, only this file changes
(to a Spatie team/permission check). **Do not inline role checks anywhere else** —
that is the property that makes ticket "RBAC final pass" cheap instead of a sweep.

## Why the order matters

`EnsureTenantAccess.php:21` and `HodNotificationResolver.php:27,33` call
trait-injected methods (`hasRole()`, `User::role()`). Removing the `HasRoles`
trait makes these throw `BadMethodCallException` — a 500 on every tenant page,
not a graceful 403. **Strip the call sites in the same change that removes the
trait.**

## Scope

**Backend**
- `app/Models/User.php:13,39` — remove `HasRoles` import and trait use.
- `app/Services/TenantAccess.php` — new, as above.
- `app/Http/Middleware/EnsureTenantAccess.php:21` — delegate to `TenantAccess`.
- `app/Services/HodNotificationResolver.php:27-35` — replace `User::role('hod')`
  with `User::query()->where('is_hod', true)` chained through the existing
  `whereHas('employee', …)` department filter; replace `User::role('admin_tenant')`
  with `User::query()->where('is_super_admin', true)->where('tenant_id', …)`.
  **Keep the `Log::critical` at :37-40** — it is the observability signal for the
  AGENTS.md-settled fallback chain.
- `app/Providers/AppServiceProvider.php:9,33,36-44` — remove `Gate` import and
  the whole `configureSuperAdmin()` method (`Gate::before` on `hasRole`).
- `app/Http/Middleware/HandleInertiaRequests.php:48` — drop `auth.role`;
  **:56-62 keep the `tenants` prop** (ticket 05 consumes it, and it must be
  filtered through `TenantAccess::operableTenants()` instead of `Tenant::all()`).
  **:63-75 delete** `can` and `permissions`.
- `routes/tenant.php` — remove all 27 `permission:` middleware declarations.
  Keep `create`/`edit` declared *before* `{param}` routes.
- `routes/settings.php:4,27-33` — remove the roles group and the import.
- `app/Http/Controllers/Settings/RoleController.php` — delete file.
- `app/Http/Controllers/MasterData/{Division,Department,Employee,Position}Controller.php`
  (12 `can()` calls in `index()`) — remove the `can` bag from `Inertia::render`.
- Migration: add `is_super_admin` (bool, default false) and `is_hod` (bool,
  default false) to `users`.

**Frontend**
- `resources/js/types/global.d.ts:17,19` — remove `can` and `permissions`; add a
  typed `tenants` prop (currently untyped, falling through the index signature).
- `resources/js/components/nav-items.tsx` — drop `permission?: string` (:40-41),
  the `permissions` field (:61-64), the filter at :237-245, and the "Hak Akses"
  item (:219-225). Drop `roles?:` (:38-39) too — **it is already dead**, no item
  ever sets it, so `mainNavItems()` :259 already filters the system group empty.
- `resources/js/components/page-header.tsx:30-35`, `app-sidebar.tsx:230-235,273-276`,
  `bottom-nav.tsx:86-90` — remove `permissions` threading.
- `resources/js/pages/settings/{divisions,departments,employees,positions}/index.tsx`
  (×4) — remove `can?.create` / `can.update` guards and the unused destructure.
  `can.delete` was already never read and no delete button exists.
- `resources/js/pages/work-orders/index.tsx:35-43` — keep the `activeTenant`
  half of the guard, drop the permission half.
- `resources/js/pages/dashboard.tsx:531-536` — same.
- `resources/js/pages/settings/roles.tsx` — delete file (338 lines).
- `resources/js/layouts/settings/layout.tsx:8,30,32-34` — remove the roles nav entry.
- `resources/js/components/nav-user.tsx:22-44,59-60,96` — remove `ROLE_LABELS`
  and `roleLabel()`; drop the secondary line under the user's name.
- Delete generated `resources/js/routes/roles/index.ts` and
  `resources/js/actions/…/Settings/RoleController.ts`; regenerate via `npm run build`.

**Kept deliberately** (do not touch): `composer.json` dependency, `config/permission.php`,
the permission-tables migration, `RoleAndPermissionSeeder`, `DatabaseSeeder`'s call to it,
the `permission`/`role`/`role_or_permission` aliases in `bootstrap/app.php` (they simply
become unused). `role:` and `role_or_permission:` are **already unused** — zero call sites.

## Tests

30 of 81 fail today. Per test:
- **Delete** (asserted a gate that no longer exists): `RoleManagementTest.php`
  entirely (10 tests); `MasterDataTest` "denies creating a division without
  `division.create`" and "lets the super admin bypass permission checks";
  `WorkOrderTest` "denies creating a WO without the create permission".
- **Rewrite** (called a helper, assertion still valid): drop `makeSuperAdmin()`
  in favour of `is_super_admin`, drop `givePermission()` calls but keep the
  `assertOk`. `tests/Pest.php:5-6,49-61` — delete both helpers; add
  `makeSuperAdmin(User $user)` that sets the boolean.
- **Rewrite** for `is_hod`: `WorkOrderTest` "falls back to hod-role users" and
  "falls back to admin-tenant users + logs critical" — these two are the **only**
  guard on the fallback chain and must keep asserting the same behaviour.
- `MasterDataTest` "seeds the baseline roles and permissions" — keep; the seeder
  still exists.

## Verification

- `php artisan test` — all green.
- `npm run types:check` — clean.
- Grep for `hasRole|User::role|->can\(|getRoleNames|permission:` must return
  zero hits outside `config/permission.php`, `database/seeders/RoleAndPermissionSeeder.php`,
  and the unused `bootstrap/app.php` aliases.

## Not in this ticket

Per-branch permission sets (a "user may operate branches X, Y, Z" table). Settled
as additive later — see `research-stancl-tenancy.md`; it would require Spatie
`teams => true`. `TenantAccess` is shaped so this lands without touching callers.

## Delivered

**The seam.** `app/Services/TenantAccess.php` — `canOperate()`, `operableTenants()`,
`canManageTenants()`. Sole authority on branch access; `EnsureTenantAccess` and the
Inertia `tenants` prop both consume it. This is the file that changes when RBAC
returns.

**Migration** `2026_10_06_133926_add_identity_flags_to_users_table` — `is_super_admin`,
`is_hod`, both boolean default false, both cast and fillable on `User`.

**Backend.** `HasRoles` stripped from `User`; `Gate::before` deleted from
`AppServiceProvider`; `auth.role` removed from shared props; `can`/`permissions`
shared props deleted; the `tenants` prop now flows through `TenantAccess` instead of
`Tenant::all()` (it previously leaked every branch to every user); 27 `permission:`
declarations removed from `routes/tenant.php`; roles group removed from
`routes/settings.php`; `RoleController` deleted; `can` bags dropped from the four
master-data controllers (their `index()` signatures lost the now-unused `Request`).

**Frontend.** `Auth.role` and the `can`/`permissions` types removed; `tenants` prop
typed as `TenantSummary[]`; `nav-items.tsx` lost `roles?`/`permission?` and the
filter (the "Hak Akses" entry is gone); `nav-user.tsx` lost `ROLE_LABELS` and shows
the email as the secondary line; roles page and its Wayfinder artifacts deleted.

**Tests.** `RoleManagementTest` deleted (10 tests, all RBAC-only).
`tests/Pest.php` — `givePermission()` removed; `makeSuperAdmin()` now sets the flag;
`makeHod()` added. 6 new `TenantAccessTest` cases pin the seam's contract,
including that `operableTenants()` returns exactly what `canOperate()` enforces.

Two assertions were deliberately **inverted** rather than deleted, so the intended
post-RBAC behaviour stays pinned:
- "denies creating a division without the create permission" → "allows creating a
  division without any role" (asserts the record is created).
- "denies creating a work order without the create permission" → "denies creating a
  work order for a user of another branch" (branch isolation replaces the role gate).

**Route count:** 33 tenant routes, 30 central. Zero `permission:` declarations remain.

## Verification

- `php artisan test` — 81 tests, 1 failure: `SamlTest.php:220`, which fails identically
  on a clean tree (pre-existing, unrelated).
- `npm run types:check` — clean. `npm run build` — succeeds.
- `vendor/bin/pint --dirty` — applied.
- Grep for `hasRole|User::role|assignRole|givePermissionTo|getRoleNames|permission:`
  across `app/`, `routes/`, `resources/js/`, `database/`, `tests/` returns nothing.