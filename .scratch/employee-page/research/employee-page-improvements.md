---
created: 2026-10-20
tags: [research, employee, master-data, organization, rbac, sync]
status: draft
---

# Employee Page Improvements — Research Report

> **Scope:** read-only research for improving the Employee page, especially department ↔ division integration and management of detailed employee data, account links, and roles.
>
> **Status:** research/draft. No application code was changed. This file is the only write.
>
> **Evidence legend:**
> - **[CODE]** behavior verified in this repository.
> - **[DATA]** verified against the local MySQL snapshot on 2026-10-20 (counts/schema only; no personal data).
> - **[PORTAL]** intended behavior documented in `optigate-complete-api-docs.md`; this is the portal contract, not proof that the live portal currently returns it.
> - **[REF]** conceptual reference from `D:\Laravel Project\opti-work` (the older implementation), not this repository.
> - **[REC]** recommendation or decision still needed.

---

## 1. Executive Summary

1. **The Employee page is currently a synced directory with two local actions (ban and role assignment), not an employee-data management surface.** There is no employee create/update/delete route; identity and organization fields arrive only from the portal sync. See `routes/web.php:146-155` and `app/Console/Commands/SyncEmployees.php:104-127`.
2. **The local organization model is inverted relative to the portal.** This repo stores `departments.division_id` (`database/migrations/2026_10_02_000000_create_organization_tables.php:40`) and `Division::departments()` is `hasMany` (`app/Models/Division.php:36-39`). The portal stores `divisions.department_id`, nullable for direct divisions (`optigate-complete-api-docs.md:1132-1137`, `:605-611`). One department can have many divisions, so the current local model cannot represent the portal hierarchy without data loss.
3. **The sync pipeline currently cannot populate the organization path.** Divisions discard the portal's `department_id`/`is_direct` (`app/Console/Commands/SyncDivisions.php:201-212`); departments never write `division_id` (`app/Console/Commands/SyncDepartments.php:243-252`); positions are never called by the orchestrator (`app/Console/Commands/SyncTenantData.php:32-38`); and the orchestrator passes a tenant **code** to the departments/employees commands, which expect a tenant **ULID** (`SyncTenantData.php:19-20,33,37` vs `SyncEmployees.php:41`). In the local snapshot this is visible: **125 employees, 11 departments, 11 divisions, 0 positions, 0 employees with any department/division/position link**.
4. **Two visible Employee-page bugs are confirmed by code.** The index query omits `banned_at`, so `is_banned` is always `false` (`app/Queries/EmployeeQuery.php:28-40`; `app/Http/Resources/EmployeeResource.php:25`). The show controller does not load `user`, so `employee.has_account` is undefined and the UI always renders “Akun Aktif” (`app/Http/Controllers/MasterData/EmployeeController.php:58`; `resources/js/pages/employees/show.tsx:293-298`).
5. **Employee PII is broadly exposed.** Employee routes are protected only by `auth` + `tenant.access`, not by `employee.read` (`routes/web.php:130,146-155`). The show action serializes the raw `Employee` model, including `last_login_ip` and `optigate_user_id`, rather than an explicit resource (`EmployeeController.php:74-77`; `app/Models/Employee.php:27-29,33`).
6. **Account linking and role assignment are partial.** There is no link/unlink UI or route; `users.employee_id` has no unique index; only the platform account auto-links (`app/Services/LoginProvisioningService.php:65-73`); SAML creates unlinked accounts (`app/Http/Controllers/SamlController.php:252-271`). Role assignment mixes a hardcoded frontend list (`show.tsx:76-85`) with a server allowlist that includes `guest` but excludes custom roles (`app/Http/Requests/Employee/SyncAkunRolesRequest.php:31-34`).
7. **The prior decision in issue 11 says division mapping is “NOT needed”**, which directly conflicts with this research request (`.scratch/optigate-sync/issues/11-employee-division-mapping.md:7-13`). This must be explicitly reopened or confirmed before schema work.
8. **Recommended direction:** decide the portal-aligned model first (`divisions.department_id`, nullable for direct divisions), then rebuild sync around it, then fix the page. A slice plan is in §7. The highest-value quick wins are the two page bugs, the `employee.read` gate, explicit resources, and the sync-orchestrator fixes; the structural fix is the department/division direction.

---

## 2. Method and Baseline

- Read the employee index/show pages, `EmployeeController`, `Employee`/`User`/`Department`/`Division`/`Position` models, `EmployeeQuery`, `EmployeeResource`, `EmployeeIndexRequest`, `SyncAkunRolesRequest`, all relevant routes, migrations, sync commands, seeders, provisioning, and tests.
- Read portal API sections for companies, departments, divisions, positions, users, and hierarchical chaining.
- Queried the local database read-only for counts and schema facts.
- Ran the targeted employee/sync/RBAC test set:

```text
php artisan test --compact
  tests/Feature/EmployeeIndexTest.php
  tests/Feature/EmployeeBanTest.php
  tests/Feature/SyncEmployeesTest.php
  tests/Feature/SyncDivisionsTest.php
  tests/Feature/SyncDepartmentsTest.php
  tests/Feature/SyncTenantDataTest.php
  tests/Feature/Rbac/EmployeeAkunRolesTest.php
  tests/Feature/Rbac/SyncAkunRolesTest.php
  tests/Feature/Rbac/PermissionConsistencyTest.php
```

Result: **75 passed, 457 assertions** (2026-10-20). This is a targeted baseline, not the full suite.

### Local data snapshot

| Fact | Value |
| --- | ---: |
| Tenants | 3 |
| Employees (all tenants) | 125 |
| Employees in `dmb` | 125 |
| Employees in `bm` / `dmp` | 0 / 0 |
| Departments | 11 |
| Divisions | 11 |
| Positions | 0 |
| Departments with `division_id` set | 0 |
| Employees with `department_id` set | 0 |
| Employees with `division_id` set | 0 |
| Employees with `position_id` set | 0 |
| Users | 1 |
| Users linked to an employee | 0 |
| Banned employees | 0 |

**Interpretation [DATA]:** the current page is showing imported identities with no usable organization placement, and no linked accounts. This is why organization filters/columns look populated in code but are empty in practice.

---

## 3. Current State

### 3.1 Routes and authorization

All employee routes live inside the tenant-prefixed group:

- `routes/web.php:130` — group middleware `['auth', 'tenant.access']`.
- `routes/web.php:146` — `GET {tenant}/employees` → `index`.
- `routes/web.php:147` — `GET {tenant}/employees/{employee}` → `show`.
- `routes/web.php:148` — `POST {tenant}/employees/sync` → `sync`.
- `routes/web.php:151-152` — `POST {tenant}/employees/{employee}/akun/roles` → `syncAkunRoles`.
- `routes/web.php:154-155` — `POST .../ban`, `POST .../unban`.

There is **no** `employee.read`/`employee.update` middleware on any of these. `tenant.access` checks that the authenticated user's home tenant matches the route tenant, or that the user is a super admin (`app/Http/Middleware/EnsureTenantAccess.php:23-38`; `app/Services/TenantAccess.php:11-30`). It does not check employee-data permission.

`employee.read` exists in the seeded catalogue (`database/seeders/RoleAndPermissionSeeder.php:142`) and is granted to multiple roles (`:266,275,283,291`), but `tests/Feature/Rbac/PermissionConsistencyTest.php:124` explicitly allowlists it as “gated when master data moves to permissions”. In other words, the permission is intentionally still unused.

**Consequence:** any authenticated user whose home tenant is the route tenant can list and open every employee, including NIK, email, phone, photo, and account status.

### 3.2 Employee index

**What works today:**

- Tenant-scoped pagination, search, sort, and relation loading (`EmployeeController.php:24-47`; `EmployeeQuery.php:21-61`).
- Search covers NIK, name, email, phone, department code/name, position name, and division code/name (`EmployeeQuery.php:42-55`).
- Sortable columns are limited to NIK, name, email, phone, and `created_at` (`EmployeeQuery.php:10-16`).
- Default page size 10; allowed 10/25/50/100 (`app/Http/Requests/EmployeeIndexRequest.php:26-32,46-49`).
- The UI can hide/show all nine columns; defaults are NIK, name, department, position, division, phone, email, “Bergabung”, and account (`resources/js/pages/employees/index.tsx:133-143`).
- Account badge uses `has_account` and works on the index because `EmployeeQuery` eager-loads `user` (`EmployeeQuery.php:27`; `EmployeeResource.php:29`).
- Ban/unban UI is super-admin-only; the server enforces it with `authorizeBan()` (`EmployeeController.php:84-107,198-201`; `index.tsx:562`).

**Gaps:**

| Gap | Evidence | Effect |
| --- | --- | --- |
| `banned_at` is not selected, so `is_banned` is always false | `EmployeeQuery.php:28-40` omits it; `EmployeeResource.php:25-26` reads it | Banned employees are not marked; the UI shows “Blokir” instead of “Buka Blokir” (`index.tsx:625-631`). The existing test asserts `false` only (`tests/Feature/EmployeeBanTest.php:117-122`), so it does not catch this. |
| No organization/status filters | `EmployeeIndexRequest.php:26-32` only accepts search/page/per_page/sort/direction; `EmployeeQuery.php` has no `department_id`/`division_id`/`position_id`/`is_banned`/`has_account` scopes | Users can only free-text search; there is no “show only IT”, “only direct divisions”, “only unlinked accounts”, etc. |
| “Bergabung” is local first-sync time | `EmployeeQuery.php:38` selects `created_at`; `index.tsx:286,884` labels it “Bergabung” | Misleading: re-importing or first syncing an old employee makes them look newly joined. The portal does not document a hire-date field in `/api/users` (`optigate-complete-api-docs.md:1577-1589`). |
| Sort does not include organization | `EmployeeQuery.php:10-16` | Cannot sort by department/division/position/account status. |
| No bulk actions or CSV export | Page actions are only sync (`index.tsx:319-347`) | Detailed management is manual and limited. |

### 3.3 Employee show

**What works today:**

- Resolves the employee manually against the route tenant, preventing cross-tenant IDOR (`EmployeeController.php:142-151`).
- Loads department, position, and division (`:58`).
- Shows NIK, email, phone, department, position, and division cards (`show.tsx:212-251`).
- Shows a separate account/role card only when the viewer has `akun.read` (`EmployeeController.php:60-72`; `show.tsx:361-415`).
- Role editing is hidden without `akun.update` and is sealed for `super_admin` (`show.tsx:108-111,253`; `EmployeeController.php:168-170`).
- Pair invariant `field_staff` ⇄ `karyawan` is enforced server-side (`EmployeeController.php:174-186`).

**Bugs and gaps:**

| Gap | Evidence | Effect |
| --- | --- | --- |
| `user` is not eager-loaded | `EmployeeController.php:58` loads only `department, position, division` | `employee.has_account` does not exist in the raw model payload. `show.tsx:293` uses `employee.has_account !== false`, so the “Akun Aktif” badge renders for everyone, including employees with no account. The separate `akun` card can simultaneously say “Belum ada akun” (`show.tsx:378-381`). |
| Raw model is serialized | `EmployeeController.php:74-77` passes `$employee` directly; `Employee.php:27-29,33` includes `last_login_ip`, `optigate_user_id`, `tenant_id`, `banned_at`; no `$hidden` | The Inertia payload exposes internal identifiers and last-login IP even though the page does not use them. Loaded `department` also serializes internal fields such as `hod_user_id`/`manager_user_id` because the whole relation is passed. |
| No explicit `EmployeeDetailResource` | Show uses raw model, index uses `EmployeeResource` | Two different payload shapes; fixes to one do not carry to the other. |
| No organization path | Cards show department, position, division as three independent values (`show.tsx:231-250`) | Does not communicate “Department → Division → Position”, and does not distinguish a direct division (no department) from a missing value. |
| No employment/account status distinction | Status card says “Aktif”/“Tidak Aktif” based only on `banned_at` (`show.tsx:350-356`) | Portal `is_active`/`onboarded` are not stored, so “active” means “not locally banned”, not “active in the portal”. |
| No audit/history | No audit package or history table is used for ban/role changes | Cannot answer who banned/unbanned or changed roles, or when/why. |

`tests/Feature/EmployeeIndexTest.php` covers index tenant scoping, search, pagination, sort, and relation inclusion (`:36-62,64-221,223-322,324+`), but **does not cover `show`**. `EmployeeAkunRolesTest` covers the show `akun` prop only (`:39-84`), not the general employee payload or `has_account`.

### 3.4 Account linking and roles

**Linking today:**

- `users.employee_id` is a bare nullable UUID (`database/migrations/0001_01_01_000200_create_users_table.php:24`); the FK is added later (`organization_tables.php:77-79`).
- There is **no unique index** on `users.employee_id`, while `Employee::user()` is `hasOne` (`app/Models/Employee.php:102-104`; `app/Models/User.php:79`). Duplicate accounts can link to the same employee and one is chosen arbitrarily.
- The only automatic link is the platform super-admin account, matched by email across all tenants (`LoginProvisioningService.php:65-73`; `Employee::withEmailAnyTenant()` at `Employee.php:51-56`).
- SAML creates a local user by email but does not link non-platform users to an employee (`SamlController.php:252-271`).
- Unlinked, role-less users receive `guest` exactly once (`LoginProvisioningService.php:76-95`). A role-less user who is already linked is skipped (`:82`), so a linked account with no role can remain role-less.
- There is no link/unlink route or UI in `routes/web.php:146-155`.

**Role assignment today:**

- Server route is ungated; authorization is `Gate::authorize('akun.update')` in the controller (`EmployeeController.php:159-166`).
- `super_admin` is rejected as a submitted role and sealed if already held (`SyncAkunRolesRequest.php:31-46`; `EmployeeController.php:168-170`).
- The server accepts all `RoleAndPermissionSeeder::SYSTEM_ROLES` except `super_admin` (`SyncAkunRolesRequest.php:31-34`), including `guest`.
- The UI hardcodes eight roles and **omits `guest`** (`show.tsx:76-85`), while form state starts from the current roles (`:104-105`). A `guest` role therefore stays attached but is invisible; if a custom role exists, it is also invisible and the server rejects the whole submission because it is not in `SYSTEM_ROLES`.
- Roles are global: Spatie teams are disabled (`config/permission.php:151`). `syncAkunRoles` does **not** verify that the linked account's `tenant_id` matches the employee's tenant (`EmployeeController.php:163-166`).
- ADR-0005 says the point of the surface was that “created roles were unusable” (`docs/adr/0005-user-role-assignment.md:7`), but custom roles cannot be assigned here.

**Coverage:** `tests/Feature/Rbac/SyncAkunRolesTest.php` covers permission denial, guest eviction, super-admin protection, pair invariant, identity-flag preservation, unknown roles, and no-account (see test names at `:48,58,71,82,94,105,120,132,142,154,175,183,195`). It does **not** cover custom roles, cross-tenant linked accounts, or role hierarchy/escalation.

### 3.5 Organization model and master-data pages

**Local schema [CODE]:**

- `divisions` has `tenant_id`, `kode_division`, `nama_division`, `optigate_division_id`, `is_active`, timestamps — **no `department_id`** (`organization_tables.php:19-31`).
- `departments` has `division_id` (`:40`), plus HOD/manager links.
- `positions` has both `department_id` and `division_id` (`:51-52`).
- `employees` has `department_id`, `position_id`, `division_id` (`:66-68`).

**Local relations [CODE]:**

- `Department::division()` is `belongsTo(Division, 'division_id')` (`app/Models/Department.php:70-73`).
- `Division::departments()` is `hasMany(Department, 'division_id')` (`app/Models/Division.php:36-39`).
- `Position` has both relations (`app/Models/Position.php:34-44`).
- `Employee` has all three (`app/Models/Employee.php:72-88`).

**Master-data UI [CODE]:**

- Department index/show present a **single** division per department (`resources/js/pages/departments/index.tsx:26-30,164,203-204`; `departments/show.tsx:61-64`).
- Division index/show present a **count of departments** per division (`divisions/index.tsx:22,158-160`; `divisions/show.tsx:37-42`).
- This is internally consistent with the inverted schema, but inconsistent with the portal, where a department has many divisions and a division has one optional parent department.

**Database snapshot [DATA]:** 11 departments and 11 divisions exist, but **0** departments have `division_id` set, and `divisions.department_id` does not exist. So neither side of the relationship is currently populated.

**Reference implementation [REF]:** the older `opti-work` repo already uses the portal direction: `Division::department()` is `belongsTo(Department::class, 'id_department', 'id_department')` (`D:\Laravel Project\opti-work\app\Models\Division.php:31-33`), and its `SyncDivisions` writes `'id_department' => $div['department_id'] ?? null` (`D:\Laravel Project\opti-work\app\Console\Commands\SyncDivisions.php:45`). This is evidence that the portal-aligned direction was previously understood, not a recommendation to copy the old schema literally.

### 3.6 Sync pipeline

| Command | Current behavior | Gap |
| --- | --- | --- |
| `app:sync-departments` | Fetches paginated company departments with `with=hod,manager`; upserts `kode_department`, `nama_department`, HOD/manager; deactivates missing (`SyncDepartments.php:123-162,215-252,383-399`) | Never writes `division_id` (`:243-252`), so the local department→division link stays null. HOD resolution depends on employees already existing (`:285-379`), but the orchestrator runs departments **before** employees. |
| `app:sync-divisions` | Fetches paginated company divisions; upserts `kode_division`, `nama_division`, `is_active=true`; deactivates missing (`SyncDivisions.php:123-155,190-239`) | Discards `department_id` and `is_direct` (`:201-212`). The local model has nowhere to put `department_id`. |
| `app:sync-positions` | Fetches paginated company positions; maps both portal department and division IDs (`SyncPositions.php:147-200`) | **Never called by `SyncTenantData`** (`SyncTenantData.php:32-38`) and not scheduled (`bootstrap/app.php:25-32`). It also reads `division_id` from `/api/companies/{company}/positions` (`SyncPositions.php:172-193`), while the documented example for that endpoint only shows `department_id`/`is_direct` (`optigate-complete-api-docs.md:647-653`); the general `/api/positions` example includes `division_id` (`:1332-1340`). Verify the live payload before relying on it. |
| `app:sync-employees` | Fetches `/api/users?company_id=...` without pagination; upserts by `(tenant_id, optigate_user_id)`; maps department by portal ID; maps position; derives division from local position (`SyncEmployees.php:21,58-61,77-127`) | No pagination loop (the portal documents `paginate`/`per_page`, default 15 — `optigate-complete-api-docs.md:1561-1562`); no `with=position.division`; no `is_active`/`onboarded`; no deactivation of missing employees; no per-record error handling; no transaction around the whole import; no per-record sync timestamp; `nik_employee` is not unique (the `(tenant_id, optigate_user_id)` unique index does exist — `2026_10_11_000001_add_ban_and_sync_identity_to_employees_table.php:19`). It clears `department_id`/`division_id` when a mapping fails (`:116-126`). |
| `app:sync-tenant-data` | Selects tenants by `code`, then calls departments/divisions/employees (`SyncTenantData.php:19-20,29-38`) | Departments/employees receive the code but expect a ULID (`SyncEmployees.php:30,41`; `SyncDepartments.php:108-111`); `Tenant::find()` returns null, so the child either fails or skips. Divisions is called **without** `--tenant` (`SyncTenantData.php:35`), so it syncs every active company instead of the selected tenant. The orchestrator ignores exit codes and still prints success (`SyncTenantData.php:58-68`) and never calls positions. The test mocks the same code argument (`tests/Feature/SyncTenantDataTest.php:32-34`), locking in the mismatch. |

**Scheduling [CODE]:** only `app:sync-tenants` (01:00) and `app:check-deadline-escalation` (09:00) are scheduled (`bootstrap/app.php:25-32`). There is no scheduled department/division/position/employee sync. The UI exposes manual sync only for employees, departments, and divisions (`routes/web.php:144,148,159`); positions have no sync button (`routes/web.php:161`).

### 3.7 Sync write and failure behavior

- `SyncEmployees` uses `firstOrNew` + `forceFill(...)->save()` per employee (`SyncEmployees.php:99-127`). The loop is inside one `try`, but there is **no DB transaction**, so a failure after N records leaves N records committed and no rollback.
- The command logs and returns failure for an HTTP/exception error (`:57-69,135-141`), but not for individual malformed rows; a single bad row aborts the rest.
- `banned_at` and the linked account are deliberately untouched (`:95-98`). This is correct for preserving local state, but it means portal deactivation or deletion is not reflected.
- `created_at` is local first-insert time; there is no `last_synced_at`/`portal_updated_at` on the employee.
- `employees.(tenant_id, optigate_user_id)` **is unique** (`database/migrations/2026_10_11_000001_add_ban_and_sync_identity_to_employees_table.php:19`), so the idempotent key is protected. `employees.(tenant_id, nik_employee)` is only a non-unique index (`organization_tables.php:72-73`), so duplicate NIKs are still possible.

---

## 4. Portal API Contract (Intended Behavior)

### 4.1 Hierarchy

The documented hierarchy is:

```text
Company
  └── Department (optional parent for a division)
        └── Division (department_id nullable; is_direct=true when directly under GM)
              └── Position (has department_id and division_id)
                    └── User / Personel (has department_id and position_id; division via position.division)
```

Evidence:

- Division payload has `department_id` and `is_direct` (`optigate-complete-api-docs.md:1132-1137`).
- A direct division has `department_id: null` and `is_direct: true` (`:605-611`).
- Department detail can eager-load `divisions` (`:884,915-921`); `/api/departments/{department}/divisions` returns divisions with `department_id` (`:928-964`).
- Position payload has both `department_id` and `division_id` (`:1332-1340`).
- `/api/users` exposes `department_id` and `position_id` but not `division_id` in the documented response (`:1577-1589`); it supports eager-loading `position.division` (`:1560`) and filtering by `division_id` (`:1549`).

### 4.2 Endpoint implications

| Portal behavior | Evidence | Implication for this app |
| --- | --- | --- |
| `/api/divisions` returns `department_id`, `is_direct`, optional `department` relation | `optigate-complete-api-docs.md:1096-1151` | The canonical relation is division → department. Store `divisions.department_id`; direct divisions have `null`. |
| `/api/departments/{department}` can include `divisions` | `:872-924` | A department detail page can show many divisions without a separate query per division. |
| `/api/positions` returns both `department_id` and `division_id` | `:1332-1340` | A position can be department-only, division-scoped, or direct. Use it as the source for employee division when a position exists. |
| `/api/users` returns `department_id`/`position_id`, not `division_id` | `:1577-1589` | Employee division must be derived from `position.division` (or a local position mapping). Do not expect a top-level `division_id` in the user payload. |
| `/api/users` pagination is opt-in; `per_page` defaults to 15 | `:1561-1562` | The sync must either request `paginate=true` and loop, or prove the un-paginated response has no cap. Current code does neither. |
| `/api/users` supports `with=position.division` | `:1560` | This is the safest way to obtain the canonical division without a second endpoint, if the live payload shape is confirmed. |
| `/api/users` can eager-load portal `roles` | `:1560,1554` | Current sync does **not** import portal roles. Local Spatie roles are app-specific; do not auto-map them without an explicit decision (Q13). |
| `/api/companies/{company}/positions` example only shows `department_id`/`is_direct` | `:647-653` | Current `SyncPositions` reads `division_id` from this endpoint. Verify the live response; otherwise switch to `/api/positions?company_id=...`. |

### 4.3 Direct divisions and positions

- A direct division is not “missing a department”; it is intentionally directly under the GM. The UI must distinguish `department_id = null` from “not synced”/“unknown”.
- A direct position (`is_direct=true`) may similarly have no department. Do not render it as an error.
- If an employee has a position in a direct division, the employee path is `— / Direct Division / Position`, not `Unknown Department`.

### 4.4 What the portal does not provide

The documented `/api/users` list and detail payloads expose identity, contact, organization, and `is_active`/`onboarded` (`optigate-complete-api-docs.md:1577-1589,1623-1639`). They do **not** document a hire date, address, emergency contact, salary/grade, employment history, or documents. “Detailed employee data management” beyond the portal fields therefore requires a local extension and an explicit ownership decision; it cannot be achieved by syncing harder.

---

## 5. Field and Capability Matrix

| Capability | Stored locally? | Synced from portal? | Index | Show | Editable locally? | Assessment |
| --- | --- | --- | --- | --- | --- | --- |
| NIK | `employees.nik_employee` (`organization_tables.php:61`) | Yes (`SyncEmployees.php:107`) | Yes | Yes | No | Read-only mirror; PII. |
| Name | `employees.nama_employee` (`:62`) | Yes (`:108`) | Yes | Yes | No | Read-only mirror. |
| Email | `employees.email` (`:63`) | Yes (`:109`) | Yes | Yes | No | PII; used for account matching (`Employee.php:51-56`). |
| Phone/WhatsApp | `employees.number` (`:64`) | Yes (`:110`) | Yes | Yes | No | PII. |
| Photo | `employees.photo_url` (`:65`) | Yes (`:111`) | No (not a column) | Avatar/initials | No | External URL. |
| Department | `employees.department_id` (`:66`) | Yes (`:116-118`) | Yes | Yes | No | Cleared when mapping fails. |
| Division | `employees.division_id` (`:68`) | Indirect: only from local `position.division_id` (`:123-126`) | Yes | Yes | No | Null under the current sync path while positions are unsynced. |
| Position | `employees.position_id` (`:67`) | Yes (`:120-122`) | Yes | Yes | No | Null under the current sync path while positions are unsynced. |
| Department→division relation | `departments.division_id` (`:40`) | **No** (`SyncDepartments.php:243-252`) | Department page shows one division | Department show shows one division | No | Inverted vs portal; cannot represent many divisions. |
| Division→department relation | **No column** (`divisions` table `:19-31`) | **No** (`SyncDivisions.php:201-212` discards `department_id`) | Division page shows “department count” | Division show shows “Jumlah Department” | No | Root structural gap. |
| Account link | `users.employee_id` (`create_users_table.php:24`; FK `organization_tables.php:77-79`) | No (login provisioning only) | Badge via `has_account` | Broken badge (see §3.3) | No link/unlink route | No management surface; no unique constraint. |
| Roles | Spatie `model_has_roles` | No (local only) | Not shown | Yes, with `akun.read` | Yes, with `akun.update` | Hardcoded UI vs server allowlist; custom roles rejected. |
| Local ban | `employees.banned_at` (`2026_10_11_000001_add_ban_and_sync_identity_to_employees_table.php:11-19`) | No (preserved across sync) | Bug: always false | Yes | Super admin only | Does not block login; no reason/actor/audit. |
| Portal active | **Not stored** | Available (`is_active` at `optigate-complete-api-docs.md:1584`) | No | No | No | “Active” currently means “not locally banned”. |
| Portal onboarded | **Not stored** | Available (`onboarded` at `:1585`) | No | No | No | Cannot distinguish incomplete onboarding. |
| Hire date | **Not stored** | Not documented in `/api/users` response (`:1577-1589`) | Shows local `created_at` as “Bergabung” | Same | No | Misleading label. |
| Last login IP | `employees.last_login_ip` (`:69`) | Yes (`SyncEmployees.php:112`) | No | Leaked in raw payload | No | Internal/security data; should not be in the page payload. |
| Sync timestamp | Tenant-level only (`last_department_synced_at`, `last_division_synced_at`) | N/A | No | No | No | No per-employee freshness/staleness. |
| Audit trail | **No** | N/A | No | No | No | No history for ban/role/link changes. |

---

## 6. Gap and Risk Matrix

| # | Severity | Gap / risk | Evidence | Impact | Recommended fix |
| --- | --- | --- | --- | --- | --- |
| 1 | **Critical** | Organization model is inverted | `organization_tables.php:19-45`; `Division.php:36-39`; portal `optigate-complete-api-docs.md:1132-1137` | A department can have only one division locally; multi-division departments cannot be represented; all department/division pages tell the wrong story. | ADR + migrate to `divisions.department_id` (nullable), retire `departments.division_id`. |
| 2 | **Critical** | Positions are not in the orchestrated/scheduled sync | `SyncTenantData.php:32-38`; `bootstrap/app.php:25-32`; local snapshot 0 positions | Under normal operation `employees.position_id` and `employees.division_id` remain null; the employee page’s organization columns and filters are useless. | Add positions to orchestration/scheduler before employees; add a UI sync action or document CLI-only. |
| 3 | **High** | Orchestrator passes code instead of ULID, ignores failures | `SyncTenantData.php:19-20,33,37,58-68`; `SyncEmployees.php:30,41`; `SyncDepartments.php:108-111` | “Sync all” can silently do nothing or fail while reporting success; the test mocks the wrong contract (`SyncTenantDataTest.php:32-34`). | Standardize `--tenant` to accept ID or code; pass `$tenant->id`; check every exit code; aggregate errors; add positions. |
| 4 | **High** | Employee routes have no `employee.read` gate | `routes/web.php:130,146-155`; `PermissionConsistencyTest.php:124` | Any tenant user can enumerate all employee PII (NIK, email, phone, photo, account status). | Gate index/show with `employee.read`; add a separate permission for contact fields if needed; update allowlist/test. |
| 5 | **High** | Show serializes raw model | `EmployeeController.php:74-77`; `Employee.php:27-29,33`; no `$hidden` | Leaks `last_login_ip`, `optigate_user_id`, `tenant_id`, and full loaded relations to the browser. | Introduce `EmployeeDetailResource`/explicit props; never send internal/security fields. |
| 6 | **High** | Account link is unmanaged and non-unique | `create_users_table.php:24`; `Employee.php:102-104`; no link route in `routes/web.php:146-155` | Duplicate accounts per employee; one arbitrary account chosen; no way to fix a wrong/missing link; cross-tenant link possible. | Unique index on `users.employee_id`; explicit link/unlink flow; validate tenant; audit. |
| 7 | **High** | Role assignment can cross tenant boundaries | `EmployeeController.php:163-166`; `config/permission.php:151` | An `akun.update` holder could change roles on an account whose home tenant differs from the employee’s tenant; roles are global. | Enforce `$akun->tenant_id === $employee->tenant_id`; add role hierarchy/allowlist; test cross-tenant denial. |
| 8 | **High** | Ban does not block login | `EmployeeController.php:84-107` sets only `banned_at`; no auth/middleware reads it (compare `SamlController.php:252-273`) | “Diblokir”/“Tidak Aktif” is misleading; the linked account can still sign in and act. | Decide semantics; if login block: enforce at SAML/provisioning + middleware/session revocation; add reason/actor/audit. If record-only: rename UI. |
| 9 | **Medium** | Index ban flag is always false | `EmployeeQuery.php:28-40`; `EmployeeResource.php:25`; test `EmployeeBanTest.php:117-122` | Banned employees look active; unban button may not appear; test gives false confidence. | Add `banned_at` to select; add an index test that bans and asserts `is_banned=true`; use `is_banned` in filters. |
| 10 | **Medium** | Show account badge is always “Akun Aktif” | `EmployeeController.php:58`; `show.tsx:293-298` | Contradicts the account card; users cannot trust account status. | Load `user` or pass `has_account` explicitly; add a show test for both states. |
| 11 | **Medium** | Role UI/server contract mismatch | `show.tsx:76-85`; `SyncAkunRolesRequest.php:31-34`; `SyncAkunRolesTest.php:58-71` | `guest` is invisible but preserved; custom roles cannot be saved; an account with a custom role cannot be edited at all. | Load assignable roles from DB; show current roles read-only if not assignable; decide guest policy; allow custom roles subject to hierarchy. |
| 12 | **Medium** | Role escalation guard is absent | `SyncAkunRolesRequest.php` allowlist includes `admin_tenant`, `general_manager`, etc.; no actor-role comparison | A lower-privileged `akun.update` holder can grant a higher role. | Define who may grant which role; enforce server-side; add tests. |
| 13 | **Medium** | No organization/status filters or org path | `EmployeeIndexRequest.php:26-32`; `index.tsx:133-143`; `show.tsx:231-250` | With 125+ employees, finding “all IT staff in Division X without accounts” is impossible except by free text. | Add department/division/position/account/status filters; show cascading org path; add query scopes. |
| 14 | **Medium** | Sync is not paginated/transactional/deactivating | `SyncEmployees.php:58-61,90-129`; portal `optigate-complete-api-docs.md:1561-1562` | Scale risk; partial imports on failure; departed employees remain “active”; no freshness signal. | Paginate; chunk + per-record error isolation; transaction or idempotent checkpoint; store `is_active`/`onboarded`; mark missing; add `last_synced_at`. |
| 15 | **Low** | “Bergabung” uses `created_at` | `EmployeeQuery.php:38`; `index.tsx:286,884`; `show.tsx:341-344` | Misrepresents tenure. | Rename to “Disinkronkan” or add a real `joined_at` if the portal exposes one. |
| 16 | **Low** | No schedule/observability for master-data sync | `bootstrap/app.php:25-32` | Data drifts silently; no “last successful sync” per entity; no alert on failure. | Schedule the chain; store run results; surface last-run/error in the UI. |

---

## 7. Recommended Plan and Slices

The order matters: the schema decision blocks correct sync; correct sync blocks useful UI; account/role hardening should not be mixed into the schema migration.

### Slice 0 — Decision and ADR (blocking, no code)

**Goal:** settle the organization direction and the source-of-truth policy before touching migrations.

- Confirm the portal-aligned model: `divisions.department_id` nullable; a department has many divisions; a direct division has `department_id = null` (and `is_direct = true`).
- Confirm what happens to `departments.division_id`: migrate values to `divisions.department_id` where possible, then drop the column; or keep it temporarily as a deprecated compatibility column.
- Confirm local-edit policy: portal is authoritative for identity/org fields; local-only fields are ban, account link, roles, and audit. If local overrides are required, define explicit override columns and conflict rules.
- Reopen `.scratch/optigate-sync/issues/11-employee-division-mapping.md` (currently “skip”) and record the new decision.

**Acceptance:** an ADR linked from `docs/adr/` and the project vault; no schema change before sign-off.

### Slice 1 — Organization schema and master-data pages

**Goal:** make the local model represent the portal hierarchy.

- Add `divisions.department_id` (nullable, indexed, FK to departments, `nullOnDelete` or restrict).
- Backfill from the current `departments.division_id` only where the relationship is unambiguous; given the current data (0 rows) this is a no-op, but the migration must be safe on other environments.
- Update `Division`/`Department` relations: `Division::department()` belongsTo; `Department::divisions()` hasMany; remove/replace `Department::division()`.
- Update department/division index/show: department shows a list/count of divisions; division shows parent department or “Direct (GM)”.
- Update `Position`/`Employee` accessors and any queries that assume the old relation.

**Acceptance:** department detail lists multiple divisions; division detail shows the correct parent/direct state; seeder/factory tests cover both a normal and a direct division; no N+1 queries on the index pages.

### Slice 2 — Sync foundation

**Goal:** populate the new model correctly and make the chain observable.

- `SyncDivisions`: persist `department_id`; derive/validate `is_direct`; switch to the documented `/api/divisions?company_id=...` if the company endpoint does not return `department_id`.
- `SyncDepartments`: stop writing `division_id`; optionally fetch `with=divisions` only for validation, not as the source of truth.
- `SyncPositions`: confirm `division_id` is present on the endpoint used; if not, switch to `/api/positions?company_id=...`; add to the chain.
- `SyncTenantData`: pass `$tenant->id` (or make every command accept both ID and code); call departments → divisions → positions → employees; check each exit code; collect errors; return failure if any child fails; remove the stale `superadmin` role-name assumption (`SyncTenantData.php:49`) or align it with `super_admin` (`RoleAndPermissionSeeder.php:37`).
- Add scheduling for the chain (e.g. nightly), with a lock to prevent overlap.

**Acceptance:** a full sync on a fresh tenant produces departments, divisions with parent links, positions with division links, and employees with the full org path; a forced child failure makes the orchestrator return failure; tests assert the ULID contract and positions call.

### Slice 3 — Employee sync correctness

**Goal:** make employee records trustworthy.

- Keep/verify the existing unique `employees.(tenant_id, optigate_user_id)` index (`2026_10_11_000001_add_ban_and_sync_identity_to_employees_table.php:19`); add a unique `(tenant_id, nik_employee)` only if business rules allow it.
- Paginate `/api/users` with `paginate=true&per_page=100` (or use the company users endpoint) and loop until exhausted.
- Request `with=position.division` (after confirming the live payload) and/or rely on local positions; validate that employee department matches position department and log mismatches.
- Add fields: `portal_is_active`, `portal_onboarded`, `last_synced_at` (and optionally `joined_at` if a source exists). Keep `banned_at` separate.
- Deactivate employees missing from the portal (`portal_is_active=false` or `portal_missing_at`) instead of deleting; preserve history.
- Chunk the import; isolate per-record errors; wrap each chunk in a transaction; report created/updated/skipped/failed counts.
- Do not clear a valid local FK on a transient mapping failure without logging; distinguish “portal says null” from “mapping unresolved”.

**Acceptance:** re-running sync is idempotent; 125+ records import completely; a single bad row does not abort the batch; a departed employee becomes inactive, not deleted; tests cover pagination, deactivation, and mapping failures.

### Slice 4 — Employee index UX

**Goal:** make the list usable for 125+ employees.

- Fix `banned_at` selection and `is_banned`; add a regression test that bans an employee and asserts the index payload.
- Add filters: department, division, position, account status (`has_account`), local status (`banned`), and portal status (`active`/`inactive`/`onboarded`).
- Add cascading department → division filter (division options depend on department; direct divisions shown without a department).
- Add an “organization path” column or compact stacked display: Department / Division / Position.
- Add sorting for department/division/position if cheap; otherwise document the limit.
- Add empty states that distinguish “no employees” from “no employees match these filters”.
- Rename “Bergabung” to “Disinkronkan” until a real hire date exists.

**Acceptance:** a user can answer “show active employees in Division X without an account” in one screen; filters survive pagination/sort; tests cover each filter and the banned badge.

### Slice 5 — Employee show UX and data minimization

**Goal:** make the detail page correct, safe, and useful.

- Load `user` (or pass an explicit `has_account`) so the account badge is truthful.
- Replace raw model serialization with `EmployeeDetailResource`: explicit fields plus `department`, `division`, `position`, `account` (only when permitted).
- Remove `last_login_ip`, `optigate_user_id`, `tenant_id`, and internal HOD/manager IDs from the page payload.
- Show a clear org path: Department → Division (or “Direct”) → Position.
- Separate “Portal status” from “Local ban”; show `is_active`/`onboarded` once stored.
- Gate contact fields (NIK/email/phone) behind the appropriate permission; at minimum gate the whole route with `employee.read`.
- Add an audit/history panel for ban/unban and role changes (or a link to the audit log).

**Acceptance:** an employee without an account shows “Belum ada akun”, not “Akun Aktif”; the payload contains no `last_login_ip`; a viewer without `employee.read` gets 403; a direct division renders distinctly.

### Slice 6 — Account linking

**Goal:** make the employee ↔ account relationship explicit and safe.

- Detect and resolve duplicate `users.employee_id` links, then add a unique index on `users.employee_id`.
- Add link/unlink actions (permission: `akun.update` or a new `akun.link`) with a user search that is tenant-scoped.
- On link, validate that the account's home tenant matches the employee's tenant; reject/warn on mismatch.
- Decide auto-link policy: keep explicit-only, or add a one-time auto-link when email matches exactly one employee in the same tenant and the account has no employee link. Never use the cross-tenant `withEmailAnyTenant()` for normal linking.
- Handle the linked-but-roleless state: either assign the baseline `karyawan` role on link or surface a clear warning; do not leave a linked account with no role.
- Audit link/unlink.

**Acceptance:** duplicate links are impossible; linking a cross-tenant account is rejected; an unlinked employee can be linked and unlinked from the UI; tests cover unique constraint, tenant mismatch, and roleless linked users.

### Slice 7 — Role UX, authorization, and audit

**Goal:** make role assignment match ADR-0005 and close escalation paths.

- Load assignable roles from the database (system + custom) instead of the hardcoded `ASSIGNABLE_ROLES`.
- Render current roles that are not assignable as read-only chips, so a custom role does not break the form.
- Decide `guest` policy: show it as removable (ADR-0006 says removal happens through role assignment), or auto-evict it when a real role is added.
- Add a server-side role hierarchy: define which actor roles may grant/revoke which target roles; at minimum restrict `admin_tenant`/`general_manager` grants to super admin or an explicit permission.
- Enforce `$akun->tenant_id === $employee->tenant_id` in `syncAkunRoles`.
- Audit every role change (actor, target, before/after).
- Add tests for custom roles, guest visibility/removal, cross-tenant denial, and escalation attempts.

**Acceptance:** a custom role can be assigned if policy allows; a non-assignable current role is preserved without breaking the form; a lower-privileged actor cannot grant a higher role; every change is auditable.

### Slice 8 — Scheduling, observability, and regression coverage

**Goal:** keep the data correct over time.

- Schedule departments → divisions → positions → employees nightly; add `withoutOverlapping()` and a reasonable timeout.
- Store a `sync_runs` record (command, tenant, started/finished, counts, status, error) or at least structured logs with a run ID.
- Surface “last successful sync” and last error on the employee/department/division pages.
- Add tests: `EmployeeIndexTest` for filters and banned; a new `EmployeeShowTest` for `has_account`, payload minimization, and authorization; `SyncEmployeesTest` for pagination/deactivation; `SyncDivisionsTest` for `department_id`; `SyncTenantDataTest` for ULID + positions + failure propagation.
- Add a migration test for the org-direction change (normal + direct division).

**Acceptance:** the full chain runs unattended; a failure is visible; the regression suite covers every bug listed in §6.

---

## 8. Open Questions and Decisions Needed

| # | Question | Why it matters | Recommended default | Blocks |
| --- | --- | --- | --- | --- |
| Q1 | **Which direction is authoritative: department → division (portal) or division → department (current local)?** | The entire schema, pages, and sync depend on it. The current local model cannot represent a department with multiple divisions. | Portal-aligned: `divisions.department_id` nullable; direct divisions have `null`; retire `departments.division_id`. | Slice 1 |
| Q2 | **Is the portal the sole source of truth for identity/org fields, or are local edits allowed?** | If local edits are allowed, every sync needs an override/conflict policy; otherwise portal overwrites them. | Portal authoritative for identity/org; local-only for ban, account link, roles, audit. | Slice 0/3 |
| Q3 | **Should account linking be explicit, auto-by-email, or both?** | Email can be shared or duplicated across tenants; auto-linking can attach the wrong person. | Explicit link/unlink first; optional same-tenant exact-match auto-link only when unique and unlinked. | Slice 6 |
| Q4 | **What should happen to a linked account with no role?** | Current guest provisioning skips linked accounts (`LoginProvisioningService.php:82`), leaving them role-less. | Assign `karyawan` on link, or show a blocking warning and require role selection. | Slice 6/7 |
| Q5 | **Can custom roles be assigned from the Employee page, and can `guest` be removed there?** | Current UI hides both; server rejects custom roles and accepts `guest`. ADR-0005 says created roles should be usable. | Load roles from DB; allow custom roles subject to hierarchy; show `guest` as removable or auto-evict on real role. | Slice 7 |
| Q6 | **Who may grant which roles?** | Any `akun.update` holder can currently grant `admin_tenant`/`general_manager`. | Define a hierarchy: super admin can grant anything except `super_admin`; `admin_tenant` can grant only roles at/below its level; enforce server-side. | Slice 7 |
| Q7 | **Does “ban” mean “block login”, “block assignment”, or both?** | Current ban touches only `banned_at`; the account can still log in. The UI implies the person is inactive. | Decide explicitly. If login block: enforce at SAML/provisioning + middleware and revoke sessions. If record-only: rename to “Nonaktif (data)” and prevent assignment. | Slice 5/7 |
| Q8 | **How should portal `is_active` and `onboarded` map to local state?** | They are different from a local ban; conflating them makes “active” meaningless. | Store `portal_is_active` and `portal_onboarded`; keep `banned_at` separate; show both. | Slice 3/5 |
| Q9 | **Is there a real hire date in the portal?** | `created_at` is local sync time; labeling it “Bergabung” is wrong. | If no source, rename to “Disinkronkan”; if a source exists, add `joined_at` and sync it. | Slice 3/4 |
| Q10 | **How are employees in direct divisions represented when they have no position?** | `/api/users` exposes department/position, not division; direct division may only be reachable via position.division. | Store division from position when present; if absent, show “Direct/Unknown” explicitly; confirm whether the portal can return a division for position-less users. | Slice 1/3 |
| Q11 | **What happens to employees who disappear from the portal?** | Deleting loses history; leaving them active is misleading. | Mark `portal_is_active=false`/`portal_missing_at`; never hard-delete. | Slice 3 |
| Q12 | **Which fields require `employee.read` vs a stronger permission?** | NIK/email/phone are PII; a single read gate may be too broad. | Gate the route with `employee.read`; consider `employee.read_contact` for NIK/email/phone, or mask by default. | Slice 4 |
| Q13 | **Are portal roles authoritative, informational, or irrelevant to local RBAC?** | The portal can return roles (`optigate-complete-api-docs.md:1554,1560`), but local Spatie roles are app-specific and more granular. Auto-mapping could grant the wrong access. | Local Spatie roles remain authoritative for app access; optionally display portal roles read-only, never auto-map. | Slice 7 |
| Q14 | **Can one person have employee records in multiple tenants, and should one account link to all of them?** | `users.employee_id` is a single column; a person working in two branches cannot link both records. | For MVP, one home-tenant employee link per account; use separate accounts or a future pivot if cross-branch staff are real. | Slice 6 |

---

## 9. Conflicts, Constraints, and Risks

1. **Issue 11 conflict.** `.scratch/optigate-sync/issues/11-employee-division-mapping.md:7-13` records “division mapping is NOT needed” and marks the acceptance as deferred. This research request reopens the same problem. Do not silently implement against the old decision; update the issue with the new decision and link the ADR.
2. **Uncommitted work in the tree.** `git status` shows modified `app/Providers/AppServiceProvider.php` and untracked `app/Console/Commands/SyncTenantData.php`, `database/seeders/PermissionsSeeder.php`, `database/seeders/RolesSeeder.php`, `tests/Feature/SyncTenantDataTest.php`. These must not be overwritten; the sync-orchestrator slice should build on them deliberately.
3. **Portal docs vs live portal.** The docs are the intended contract, not a live probe. Two payloads need verification before coding: (a) whether `/api/companies/{company}/positions` returns `division_id`; (b) whether `/api/users?with=position.division` returns the nested division shape the sync expects. If either differs, adjust the mapping rather than guessing.
4. **Roles are global, data is tenant-scoped.** `config/permission.php:151` disables teams. Any role assignment change must include an explicit tenant check; otherwise the employee page becomes a cross-tenant privilege path.
5. **`users.email` is globally unique, employees are per-tenant.** `create_users_table.php:20` makes email unique; `employees` has no unique email. Auto-linking by email across tenants is ambiguous. Prefer same-tenant matching and explicit confirmation.
6. **Data migration is cheap now but not guaranteed elsewhere.** The local snapshot has 0 department→division links and 0 employee org links, so the migration is effectively a no-op locally. Other environments may have data; write the migration with a backfill + validation step and a rollback plan.
7. **Raw model serialization is a recurring pattern risk.** The index uses a resource, the show does not. Any new field added to `Employee` (e.g. `banned_reason`, `portal_is_active`) will automatically leak from the show unless the show is converted to a resource first.
8. **Ban state survives sync by design.** `SyncEmployees.php:95-98` deliberately leaves `banned_at` alone. That is good for local policy, but it means a portal deactivation and a local ban can disagree; the UI must show both, not collapse them into one “status”.

---

## 10. Sources

### Repository code and data

- `routes/web.php:130,142-161` — tenant route group; employee/department/division/position routes; middleware.
- `app/Http/Controllers/MasterData/EmployeeController.php:24-47,55-78,84-107,113-136,142-151,159-201` — index/show/ban/unban/sync/role assignment/authorization.
- `app/Http/Controllers/MasterData/DepartmentController.php:18-33,53-63` — department index/show, single division relation.
- `app/Http/Controllers/MasterData/DivisionController.php:16-39` — division index/show, department count.
- `app/Queries/EmployeeQuery.php:10-16,21-61` — select, search, sort, eager loads.
- `app/Http/Resources/EmployeeResource.php:15-50` — index payload, `is_banned`, `has_account`.
- `app/Http/Requests/EmployeeIndexRequest.php:26-64` — index filters/pagination.
- `app/Http/Requests/Employee/SyncAkunRolesRequest.php:29-55` — role allowlist.
- `app/Models/Employee.php:27-33,39-40,51-56,62-64,72-104` — fillable/casts, email lookup, ban, relations.
- `app/Models/User.php:24-25,42,79` — `employee_id`, tenant, employee relation.
- `app/Models/Department.php:30,70-73,102-120` — division/employees/positions relations.
- `app/Models/Division.php:23,29-39` — fillable/casts, departments relation.
- `app/Models/Position.php:25-44` — department/division relations.
- `database/migrations/2026_10_02_000000_create_organization_tables.php:10-79` — divisions/departments/positions/employees schema and `users.employee_id` FK.
- `database/migrations/2026_10_11_000001_add_ban_and_sync_identity_to_employees_table.php:11-19` — `banned_at`, `optigate_user_id`, unique `(tenant_id, optigate_user_id)`.
- `database/migrations/0001_01_01_000200_create_users_table.php:20,24` — unique email; bare `employee_id`.
- `database/seeders/RoleAndPermissionSeeder.php:36-49,142,266-291` — system roles, `employee.read`, role grants.
- `config/permission.php:151` — Spatie teams disabled.
- `app/Http/Middleware/EnsureTenantAccess.php:17-39` — tenant check only.
- `app/Services/TenantAccess.php:11-30` — home-tenant/super-admin logic.
- `app/Services/LoginProvisioningService.php:40-95` — platform self-link, guest role, linked-user skip.
- `app/Http/Controllers/SamlController.php:231-273` — SAML user creation and provisioning.
- `app/Console/Commands/SyncEmployees.php:15,21,30-41,57-141` — employee fetch/mapping/upsert.
- `app/Console/Commands/SyncDivisions.php:101-115,123-155,190-239` — division fetch/upsert/deactivate.
- `app/Console/Commands/SyncDepartments.php:30-64,106-115,215-252,285-399` — department fetch/upsert/HOD/deactivate.
- `app/Console/Commands/SyncPositions.php:20-24,123-136,147-200` — position fetch/mapping.
- `app/Console/Commands/SyncTenantData.php:13-68` — orchestration, tenant code/ULID mismatch.
- `bootstrap/app.php:25-32` — scheduler.
- `resources/js/pages/employees/index.tsx:133-143,250-347,559-634,710-748,884` — columns, sync, filters, ban UI, “Bergabung”.
- `resources/js/pages/employees/show.tsx:76-85,104-105,212-251,293-298,310-415` — hardcoded roles, profile cards, account badge, role card.
- `resources/js/pages/departments/index.tsx:26-30,164,203-204`; `departments/show.tsx:61-64` — single division per department.
- `resources/js/pages/divisions/index.tsx:22,158-160`; `divisions/show.tsx:37-42` — department count per division.

### Tests

- `tests/Feature/EmployeeIndexTest.php:36-62,64-221,223-322,324+` — index scope/search/pagination/sort/relations; no show coverage.
- `tests/Feature/EmployeeBanTest.php:44-122,125+` — ban/unban, index `is_banned` assertion (only `false`).
- `tests/Feature/SyncEmployeesTest.php:47-73,76-125,128-155,158-188,191-213` — tenant scope, idempotency, department/position/division mapping, clearing department.
- `tests/Feature/SyncDivisionsTest.php:46-62,83-118,121-144,147-191` — division upsert/deactivate/timestamp; no department_id assertions.
- `tests/Feature/SyncDepartmentsTest.php` — department sync coverage.
- `tests/Feature/SyncTenantDataTest.php:22-48` — mocks tenant code and does not assert positions/failure propagation.
- `tests/Feature/Rbac/EmployeeAkunRolesTest.php:39-84` — `akun` prop gating and payload.
- `tests/Feature/Rbac/SyncAkunRolesTest.php:48-207` — role write behavior, guest eviction, super-admin seal, pair invariant, identity flags.
- `tests/Feature/Rbac/PermissionConsistencyTest.php:110-132` — forward-looking allowlist including `employee.read`.

### Portal documentation

- `optigate-complete-api-docs.md:525-574` — company departments.
- `:578-615` — company divisions; direct division example.
- `:619-657` — company positions; `department_id`/`is_direct` example.
- `:815-868` — departments list; `with=divisions`, `divisions_count`.
- `:872-924` — department detail with `divisions`.
- `:928-964` — divisions under a department.
- `:1091-1151` — divisions list; `department_id`, `is_direct`, `with=department`.
- `:1155-1204` — division detail with department.
- `:1290-1358` — positions list; `department_id`, `division_id`.
- `:1530-1592` — users list; filters, `with=position.division`, pagination, response fields.
- `:1596-1641` — user detail; same field shape.
- `:1645-1726` — hierarchical chaining; company → department → division → position → users.

### Reference implementation (conceptual only)

- `D:\Laravel Project\opti-work\app\Models\Division.php:27-33` — old `belongsTo(Department, 'id_department')`.
- `D:\Laravel Project\opti-work\app\Console\Commands\SyncDivisions.php:45` — old sync writes `id_department` from portal `department_id`.

### Prior decision

- `.scratch/optigate-sync/issues/11-employee-division-mapping.md:7-13` — “division mapping is NOT needed”; conflicts with this request and must be reopened.

---

## 11. Bottom Line

The department ↔ division problem is not a UI polish issue. It is a **schema-direction mismatch** compounded by a sync chain that never populates positions and silently ignores failures. Fix the direction and the chain first; then the Employee page can become a real management surface. The page bugs (`is_banned`, `has_account`) and the missing `employee.read`/raw-model exposure are independent, cheap, high-value fixes that should ship early — but they will not make the organization data correct.

**Recommended first three moves:**

1. Reopen issue 11 and write the ADR for `divisions.department_id` (Slice 0).
2. Fix the sync orchestrator contract and add positions to the chain (Slice 2).
3. Fix the two page bugs and gate/serialize the employee routes (Slices 4–5) while the schema migration is reviewed.
