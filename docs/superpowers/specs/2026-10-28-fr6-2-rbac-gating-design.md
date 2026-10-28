---
created: 2026-10-28
tags: [rbac, gating, permission-consistency, guest, design-doc]
status: pending-approval
---

# Design Doc — FR-6.2: Remaining RBAC gating (work-order CRUD + master data CRUD) + guest denied tenant access

## Intent & Why

FR-6.2 backlog item: "keluarkan dari allowlist honesty test `PermissionConsistencyTest`; sekaligus menutup OPEN guest (opsi a)". The forward-looking allowlist in `rbacForwardLookingPermissions()` still holds `work-order.create/read/update/delete` and all master-data CRUD (`division.*`, `department.*`, `tenant.*`). These permissions are seeded but ungated — any authenticated user can hit those endpoints.

**Scope:** Gate `work-order.create/read/update/delete` in `WorkOrderController` and gate `division/department/tenant.*` CRUD in their controllers. Add matching `can` flags to `HandleInertiaRequests`, types to `resources/js/types/auth.ts`, nav gating in `nav-items.tsx`, then remove all gated entries from the allowlist so the honesty test fully passes.

**Guest decision (closed by user):** guest is denied tenant access entirely — a guest user lands on a "menunggu provisioning" page rather than opening any tenant page. This is a product decision, not technical.

## Goals

1. `work-order.create` / `work-order.read` / `work-order.update` / `work-order.delete` are gated behind `Gate::authorize()` in `WorkOrderController` methods.
2. `division.*` / `department.*` / `tenant.*` CRUD are gated behind `Gate::authorize()` in their respective controllers.
3. Each gated permission has a `can` flag in `HandleInertiaRequests::share()`, a type in `resources/js/types/auth.ts`, and nav gating in `nav-items.tsx`.
4. All gated permissions removed from `rbacForwardLookingPermissions()`; honesty test (`PermissionConsistencyTest`) passes with a lean allowlist.
5. Guest (role `guest`, zero permissions) is denied tenant access — redirected to a provisioning-waiting page instead of any tenant-scoped route.
6. `tsc --noEmit` exit 0, `npm run build` success, full test suite green.

## Non-Goals

- ❌ Adding new UI pages beyond the provisioning-waiting landing (reuse existing patterns).
- ❌ Changing role definitions or Seeder catalog beyond what's needed to gate.
- ❌ Daily Work / Work Data / extend gating (already done).
- ❌ Work Order *workflow* verbs (review/assign/submit/verify/extend/approve-request — already gated).

## Constraints

- PHP 8.4, Laravel 12, Pest; Stancl tenancy single-database, path-based tenant.
- Follow existing gating pattern: `Gate::authorize('X')` at controller method start → `can` flag in `HandleInertiaRequests` → `auth.ts` type → nav-item gating in `nav-items.tsx`.
- After modifying PHP: `vendor/bin/pint --dirty --format agent`.
- Test per file: `vendor/bin/pest <file> --compact`; final: `php artisan test --compact`.
- Commit per logical group; no `git add .`; no `--no-verify`.

## Approaches considered

| Approach | What it gates | Tradeoff |
|----------|--------------|----------|
| A — Full FR-6.2 (chosen) | work-order CRUD + division/department/tenant CRUD + guest denied | Largest diff, completes the whole backlog item + closes the guest decision in one change |
| B — Work-order CRUD only | work-order CRUD only | Too small; leaves master data ungated, item half-done |
| C — Master data only | division/department/tenant CRUD only | Leaves the core WO flow ungated |

Approach A picked because FR-6.2 is a single backlog item covering both work-order and master data.

## What changes

**Backend gating (controllers):**
- `app/Http/Controllers/WorkOrderController.php` — `Gate::authorize('work-order.create')` in create/store; `work-order.read` in show; `work-order.update` in update; `work-order.delete` in destroy.
- Division/Department/Tenant controllers — CRUD methods gated with `division.*` / `department.*` / `tenant.*`.

**Flags + types + nav:**
- `app/Http/Middleware/HandleInertiaRequests.php` — add `work-order.create/read/update/delete`, `division.*`, `department.*`, `tenant.*` can flags.
- `resources/js/types/auth.ts` — extend `Auth.can` type.
- `resources/js/components/nav-items.tsx` — gate nav items (`Work Order`, `Karyawan`, `Departemen`, `Divisi`, `Posisi`, `Struktur Jabatan`, `Unit Bisnis`) by the new flags.

**Allowlist:**
- `tests/Feature/Rbac/PermissionConsistencyTest.php` — `rbacForwardLookingPermissions()`: remove all gated entries; keep only genuinely forward-looking ones (akun.create/delete, dashboard.read, reports.read, work-planning.create/read/delete, work-data.create/delete). Fix duplicate `tenant.*` entries (lines 122–129 duplicated).

**Guest denied tenant access:**
- Login/home-redirect path: a user whose only role is `guest` (no tenant-scoped permission) is redirected to a provisioning-waiting page instead of `/{tenant}/dashboard`.

## Verification criteria (Definition of Done)

- `tsc --noEmit` exit 0
- `npm run build` success
- `php artisan test --compact`: all pass (only pre-existing skips)
- `PermissionConsistencyTest` allowlist contains no gated entries
- Commit created on a feature branch and pushed to origin

---

**Pending your approval.** Once approved, I'll invoke `writing-plans` and execute TDD task-by-task. Corrections welcome before approval.
