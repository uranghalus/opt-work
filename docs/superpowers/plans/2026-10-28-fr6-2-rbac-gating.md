# FR-6.2 RBAC Gating Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gate work-order CRUD (`create`/`read`/`update`) in WorkOrderController and master-data read (`division`/`department`/`tenant` index+show) in their controllers, add matching `can` flags + `auth.ts` types + nav gating, prune the `PermissionConsistencyTest` forward-looking allowlist, and deny guest users tenant access (redirect to provisioning-waiting page).

**Architecture:** Follow the existing gating pattern used for `work-order.review/assign/submit/verify`, `work-data.*`, `daily-work.*`: `Gate::authorize('X')` at the start of each controller method → `can` flag in `HandleInertiaRequests::share()` → type in `resources/js/types/auth.ts` → nav-item gating in `nav-items.tsx`. Remove each gated permission from `rbacForwardLookingPermissions()` so the honesty test passes with a lean allowlist. Guest denial: redirect any user whose only role is `guest` (zero tenant-scoped permissions) to a provisioning-waiting page instead of a tenant dashboard.

**Tech Stack:** Laravel 12 + PHP 8.4 (Pest), Stancl tenancy single-database, Spatie RBAC, Inertia + React

**Spec:** `docs/superpowers/specs/2026-10-28-fr6-2-rbac-gating-design.md`

## Global Constraints

- PHP 8.4, Laravel 12, Pest (not PHPUnit)
- Frontend: Inertia + React + shadcn/ui; token app only (no raw hex); Wayfinder `--with-form`
- After modifying PHP: `vendor/bin/pint --dirty --format agent`
- Test runner: `vendor/bin/pest <file> --compact`; final: `php artisan test --compact`
- `tsc --noEmit` exit 0; `npm run build` success
- Commit per task, explicit `git add <files>` (no `git add .`); no `--no-verify`

## Review Focus

1. `work-order.create` scanned in code but currently forward-looking in allowlist — must be removed once gated, else honesty test rule #4 fails.
2. `work-order.read` scanned in code but currently forward-looking — same as above.
3. `work-order.update` scanned in code (`updateRequest`) but currently forward-looking — same as above.
4. `division.read`/`department.read`/`tenant.read` scanned in code — must be gated + removed from allowlist.
5. `division.create/update/delete`, `department.create/update/delete`, `tenant.create/update/delete`, `work-order.delete` are UNUSED (no routes/methods) — must REMAIN forward-looking in allowlist (honesty test rule #3).
6. Duplicate `tenant.*` entries in allowlist (lines 122–129) must be deduplicated.
7. Guest with zero permissions must not reach any tenant-scoped route.

---

### Task 1: Gate WorkOrderController CRUD methods

**Files:**
- Modify: `app/Http/Controllers/WorkOrderController.php` — `create()` (line ~294), `store()` (line ~305), `index()` (line ~41), `show()` (line ~704), `updateRequest()` (line ~499)
- Test: create `tests/Feature/Rbac/WorkOrderGateTest.php` (Pest)

**Interfaces:**
- Consumes: nothing new (existing controller methods)
- Produces: `Gate::authorize('work-order.create')` in create/store; `Gate::authorize('work-order.read')` in index/show; `Gate::authorize('work-order.update')` in updateRequest

- [ ] **Step 1: Write failing tests** — Pest tests asserting `work-order.create`/`read`/`update` are gated: a user without the permission gets 403 on create/store/show/updateRequest; a user with it gets 200.
- [ ] **Step 2: Run tests (RED)** — `vendor/bin/pest tests/Feature/Rbac/WorkOrderGateTest.php --compact` → FAIL (403 not returned / no gate).
- [ ] **Step 3: Implement gates** — add `Gate::authorize('work-order.create')` to create+store, `Gate::authorize('work-order.read')` to index+show, `Gate::authorize('work-order.update')` to updateRequest.
- [ ] **Step 4: Run tests (GREEN)** — `vendor/bin/pest tests/Feature/Rbac/WorkOrderGateTest.php --compact` → PASS.
- [ ] **Step 5: Commit** — `git add app/Http/Controllers/WorkOrderController.php tests/Feature/Rbac/WorkOrderGateTest.php` → `feat(rbac): gate work-order CRUD (create/read/update)`

### Task 2: Gate master-data controllers (division/department/tenant)

**Files:**
- Modify: `app/Http/Controllers/MasterData/DivisionController.php` — `index()` (line ~18), `show()` (line ~51)
- Modify: `app/Http/Controllers/MasterData/DepartmentController.php` — `index()` (line ~44), `show()` (line ~137)
- Modify: `app/Http/Controllers/TenantController.php` — `show()` (line ~64); `index()` (line ~36) if routed
- Test: extend `tests/Feature/Rbac/WorkOrderGateTest.php` or create `tests/Feature/Rbac/MasterDataGateTest.php`

**Interfaces:**
- Consumes: nothing new
- Produces: `Gate::authorize('division.read')` in DivisionController index+show; `Gate::authorize('department.read')` in DepartmentController index+show; `Gate::authorize('tenant.read')` in TenantController show (+ index if routed)

- [ ] **Step 1: Write failing tests** — Pest tests asserting division/department/tenant index/show return 403 without `*.read` permission and 200 with it.
- [ ] **Step 2: Run tests (RED)** → FAIL.
- [ ] **Step 3: Implement gates** — add `Gate::authorize` to each index/show method.
- [ ] **Step 4: Run tests (GREEN)** → PASS.
- [ ] **Step 5: Commit** — `git add app/Http/Controllers/MasterData/DivisionController.php app/Http/Controllers/MasterData/DepartmentController.php app/Http/Controllers/TenantController.php tests/Feature/Rbac/MasterDataGateTest.php` → `feat(rbac): gate master-data read (division/department/tenant)`

### Task 3: Add can flags + auth.ts types + nav gating

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` — add `work-order.create/read/update`, `division.read`, `department.read`, `tenant.read` can flags
- Modify: `resources/js/types/auth.ts` — extend `Auth.can` type with the new flags
- Modify: `resources/js/components/nav-items.tsx` — gate `Work Order`, `Karyawan`(employee), `Departemen`, `Divisi`, `Posisi`, `Struktur Jabatan`, `Unit Bisnis` nav items by the new flags
- Test: `tests/Feature/Rbac/GateTest.php` — add new flags to the 3 expectation arrays

**Interfaces:**
- Consumes: gated permissions from Tasks 1–2
- Produces: `auth.can['work-order.create']` etc. in shared props; `Auth.can` type extended; nav items hidden without matching permission

- [ ] **Step 1: Add can flags** — insert `work-order.create/read/update`, `division.read`, `department.read`, `tenant.read` into `HandleInertiaRequests::share()` `auth.can` block (near existing `work-order.*` flags).
- [ ] **Step 2: Extend auth.ts type** — add the same keys to `Auth.can`.
- [ ] **Step 3: Gate nav items** — wrap master-data + Work Order nav items with the new `canRead*` flags in `navGroups()`.
- [ ] **Step 4: Update GateTest** — add new flags => false (manager/viewer) and => true (super admin) to the 3 expectation arrays.
- [ ] **Step 5: Run GateTest** — `vendor/bin/pest tests/Feature/Rbac/GateTest.php --compact` → PASS.
- [ ] **Step 6: Run tsc** — `npx tsc --noEmit` → exit 0.
- [ ] **Step 7: Commit** — `git add app/Http/Middleware/HandleInertiaRequests.php resources/js/types/auth.ts resources/js/components/nav-items.tsx tests/Feature/Rbac/GateTest.php` → `feat(rbac): can flags + types + nav gating for gated permissions`

### Task 4: Prune PermissionConsistencyTest allowlist

**Files:**
- Modify: `tests/Feature/Rbac/PermissionConsistencyTest.php` — `rbacForwardLookingPermissions()`

**Interfaces:**
- Consumes: gated permissions from Tasks 1–3 (now used in code)
- Produces: lean allowlist with only genuinely forward-looking entries

- [ ] **Step 1: Remove gated entries** — delete `work-order.create`, `work-order.read`, `work-order.update`, `division.read`, `department.read`, `tenant.read` from `rbacForwardLookingPermissions()`.
- [ ] **Step 2: Deduplicate tenant entries** — remove the duplicate `tenant.create/read/update/delete` block (lines 126–129).
- [ ] **Step 3: Keep unused entries** — `work-order.delete`, `division.create/update/delete`, `department.create/update/delete`, `tenant.create/update/delete` remain (unused → forward-looking, per honesty test rule #3).
- [ ] **Step 4: Run PermissionConsistencyTest** — `vendor/bin/pest tests/Feature/Rbac/PermissionConsistencyTest.php --compact` → PASS.
- [ ] **Step 5: Commit** — `git add tests/Feature/Rbac/PermissionConsistencyTest.php` → `fix(rbac): prune forward-looking allowlist for gated permissions`

### Task 5: Deny guest tenant access (provisioning-waiting redirect)

**Files:**
- Modify: `routes/web.php` — root `/` redirect + any home-landing logic to check for guest-only role
- Create: `resources/js/pages/auth/provisioning-waiting.tsx` (or reuse an existing denied page) — provisioning-waiting page
- Test: `tests/Feature/Rbac/GuestDeniedTenantTest.php` (Pest)

**Interfaces:**
- Consumes: user roles (Spatie `guest` role)
- Produces: guest user redirected to provisioning-waiting page instead of `/{tenant}/dashboard`

- [ ] **Step 1: Write failing test** — Pest test asserting a guest-only user hitting a tenant route is redirected to provisioning page, not dashboard.
- [ ] **Step 2: Run tests (RED)** → FAIL.
- [ ] **Step 3: Implement redirect** — in the root/home redirect logic, if user's only role is `guest`, redirect to provisioning-waiting route.
- [ ] **Step 4: Run tests (GREEN)** → PASS.
- [ ] **Step 5: Commit** — `git add routes/web.php resources/js/pages/auth/provisioning-waiting.tsx tests/Feature/Rbac/GuestDeniedTenantTest.php` → `feat(rbac): deny guest tenant access → provisioning-waiting page`

### Task 6: Full verification suite + commit + push

**Files:** none

**Interfaces:**
- Consumes: all changes from Tasks 1–5
- Produces: verification results

- [ ] **Step 1: Run tsc** — `npx tsc --noEmit` → exit 0.
- [ ] **Step 2: Run pint** — `vendor/bin/pint --dirty --format agent` → clean.
- [ ] **Step 3: Run build** — `npm run build` → success.
- [ ] **Step 4: Run full test suite** — `php artisan test --compact` → all pass, only pre-existing skips.
- [ ] **Step 5: Commit remaining changes** — explicit `git add` for any uncommitted files → `chore(rbac): finalize FR-6.2 gating`.
- [ ] **Step 6: Push** — `git push origin <current-branch>`.

---

**Plan complete and saved to `docs/superpowers/plans/2026-10-28-fr6-2-rbac-gating.md`. Please review the plan. Does it capture what you want?**

I recommend **Native** execution — all tasks are small, tightly coupled to the same test suite and allowlist, and a shipped mistake is cheap to catch with the full suite + honesty test.
