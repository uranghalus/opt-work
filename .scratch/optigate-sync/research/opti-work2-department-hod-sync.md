# opti-work2 — Department + HOD Sync from Optigate Portal API

Findings for replicating the feature in opti-works. All citations are `path:LINE` relative to the opti-work2 root.

---

## 1. Entry points

### 1.1 Artisan command
- Single command class: `app/Console/Commands/SyncDepartments.php:1-70`.
- PHP attribute `#[Signature('app:sync-departments')]` at `app/Console/Commands/SyncDepartments.php:12` **and** property `protected $signature = 'sync:departments'` at `app/Console/Commands/SyncDepartments.php:21`. In this Laravel version the attribute wins: the constructor calls `configureFromAttributes()` (`vendor/laravel/framework/src/Illuminate/Console/Command.php:97-99`), which overwrites `$this->signature` when a `Signature` attribute exists (`vendor/laravel/framework/src/Illuminate/Console/Command.php:143-152`). So the effective registered name is **`app:sync-departments`**; the `sync:departments` property is dead code.
- Description: "Auto-sync departments data from Optigate Portal API to local database" (`app/Console/Commands/SyncDepartments.php:24`).
- The controller triggers the command by its attribute name: `Artisan::call('app:sync-departments')` (`app/Http/Controllers/MasterData/DepartmentController.php:57`) — consistent with the attribute winning.

### 1.2 Scheduler
- `routes/console.php:10`: `Schedule::command('app:sync-departments')->everyMinute();`
- No `withoutOverlapping()`, `onOneServer()`, or `runInBackground()` modifiers (`routes/console.php:10`).

### 1.3 HTTP endpoint
- `routes/web.php:33-38`: group prefix `departments`, name `departments.`, group middleware `permission:department.read`.
  - `POST /departments/sync` → `DepartmentController@sync`, route name `departments.sync`, middleware `permission:department.update` (`routes/web.php:37`).
- Controller `app/Http/Controllers/MasterData/DepartmentController.php:54-65`: `sync()` calls `Artisan::call('app:sync-departments')`, redirects to `departments.index` with toast `{type: 'success', message: 'Sinkronisasi departemen berhasil dilakukan.'}`; on `\Throwable` redirects with error toast containing the exception message (`DepartmentController.php:61-64`).

### 1.4 UI button
- Inertia page `resources/js/pages/Departments/Index.tsx`:
  - Header button "Sync dari Optigate" calls `handleSync` → `router.post(sync.url(), {})` (`Index.tsx:82`, `Index.tsx:126-137`).
  - Empty-state button "Sync Sekarang" same handler (`Index.tsx:259-271`).
  - `handleSync` definition with syncing state + success/error toasts: `Index.tsx:81-93`.
- Wayfinder-generated route helper: `resources/js/routes/departments/index.ts:183-203` exports `sync` pointing at `POST /departments/sync` (`index.ts:194`).

---

## 2. API integration

### 2.1 Config keys & env vars
- `config/services.php` (`optigate_portal` block, near end of file):
  - `'url' => env('WEB_PORTAL_URL')`
  - `'token' => env('WEB_PORTAL_TOKEN')` (`config/services.php`, `optigate_portal` array — two entries only).
- Actual `.env` values (do NOT copy token into repo docs): `.env:80` `WEB_PORTAL_URL='https://gate.appdutamall.com'`, `.env:81` `WEB_PORTAL_TOKEN='<JWT ...>'`.
- `.env.example` contains **no** `WEB_PORTAL_URL` / `WEB_PORTAL_TOKEN` entries (grep found no match) — replication should add them there.

### 2.2 Request
- URL: `config('services.optigate_portal.url').'/api/departments'` (`app/Console/Commands/SyncDepartments.php:28`).
- Auth: `Http::withToken($token)` → `Authorization: Bearer <token>` header (`app/Console/Commands/SyncDepartments.php:34`).
- Timeout: 10 s (`app/Console/Commands/SyncDepartments.php:35`).
- Method: plain `GET` — **no pagination params, no `?with=hod`, no query params at all** (`app/Console/Commands/SyncDepartments.php:34-36`). The client assumes the API returns everything in one response.

### 2.3 Response shape
- Payload parsed as `$response->json('data') ?? $response->json()` (`app/Console/Commands/SyncDepartments.php:39`) — accepts either `{"data": [...]}` or a bare top-level array.
- Department item fields consumed (`app/Console/Commands/SyncDepartments.php:45-51`):
  - `id` (required, used as match key)
  - `name` (required — missing key would throw, no `?? null`)
  - `code` (optional → `null`)
  - `hod_user_id` (optional → `null`)
  - `manager_user_id` (optional → `null`)
- The test mock confirms the shape: `tests/Feature/DepartmentSyncTest.php:75-90` returns `{"data": [{"id": "01j...", "name": "IT Department", "code": "ITD"}, ...]}`. HOD arrives as a **flat scalar `hod_user_id` on the department item**, not a nested object and not via a `with=hod` include.

---

## 3. Department sync behavior

- Matching key: API `id` ↔ local `id_department` (`app/Console/Commands/SyncDepartments.php:45`), i.e. `Department::updateOrCreate(['id_department' => $dept['id']], [...])`. API id IS the local UUID primary key (`database/migrations/2026_06_26_012154_add_department_table.php:13` — `$table->uuid('id_department')->primary()`).
- Fields written (`app/Console/Commands/SyncDepartments.php:47-50`):
  - `nama_department` ← `name`
  - `kode_department` ← `code ?? null`
  - `hod_user_id` ← `hod_user_id ?? null`
  - `manager_user_id` ← `manager_user_id ?? null`
- Create/update only — **no deactivate/soft-delete**: departments removed from the API are never touched locally; nothing deletes or disables stale rows (no delete logic in `SyncDepartments.php:42-54`).
- Per-tenant scoping:
  - `Department` uses `HasUuids, TenantAware` (`app/Models/Department.php:12`), table `tb_department`, PK `id_department`, string/UUID (`app/Models/Department.php:14-21`), fillable includes `hod_user_id`, `manager_user_id`, `tenant_id` (`app/Models/Department.php:23-30`).
  - `TenantAware` adds global `TenantScope` + auto-fills `tenant_id` on create from `TenantService::getCurrentTenantId()` (`app/Models/Traits/TenantAware.php:12-21`).
  - `TenantService::getCurrentTenantId()` returns null for super_admin (or CLI with no auth user → `auth()->user()` is null → null), else the user's `tenant_id` (`app/Services/TenantService.php:5-13`).
  - `TenantScope` only adds a `where tenant_id` filter when the resolved id is non-null (`app/Models/Scopes/TenantScope.php:11-18`).
  - **Consequence for CLI sync:** when run from scheduler/artisan there is no authenticated user → tenant scope inactive and `tenant_id` written as `null` on first insert. Rows are effectively global/nullable-tenant; the migration adds `tenant_id` to `tb_department` among others (`database/migrations/2026_08_28_000001_add_tenant_id_to_tables.php:11`).
  - Note: because the match key `id_department` is the PK, `updateOrCreate` under an active tenant scope would still find rows of other tenants only if the scope allows; in CLI it does (no scope). If replicated behind a tenant-scoped context, use `withoutTenant()`/`withoutGlobalScope` for the lookup or the per-tenant row set could duplicate.
- HOD/manager columns added by migration `database/migrations/2026_06_30_082503_add_hod_manager_to_tb_department_table.php:11-13` — `hod_user_id` and `manager_user_id` are `string(36)`, nullable, added after `nama_department`.

---

## 4. HOD behavior

- Arrival: `hod_user_id` is a flat field on each department item of the API payload, stored verbatim (string) into `tb_department.hod_user_id` (`app/Console/Commands/SyncDepartments.php:49`); missing in payload → `null`.
- Semantics: `hod_user_id` holds an **Employee id** (`tb_employee.id_employee`), not a `users.id`. Evidence:
  - Tests build departments with `'hod_user_id' => $hod->id_employee` where `$hod` is an `Employee` (`tests/Feature/WorkOrderWahaNotificationTest.php:52-57`).
  - WAHA webhook compares `$department->hod_user_id !== $hod->id_employee` (`app/Http/Controllers/WhatsAppWebhookController.php:135-137`).
- Portal-HOD → local-User mapping chain (exact join used in 3 places):
  1. `Department->hod_user_id` = employee id
  2. `Employee::find($hodUserId)` → `Employee` row
  3. `User::where('email', $employee->email)->first()` → local `User`
  - Canonical helper: `EscalationRecipientResolver::userForEmployeeId()` — `app/Services/EscalationRecipientResolver.php:113-125` (Employee::find → email empty check → `User::where('email', $employee->email)->first()`).
  - Same chain inline in `ExtendRequestController::notifyHod()` — `app/Http/Controllers/WorkManagament/ExtendRequestController.php:245-257` (`$dept?->hod_user_id` → `Employee::find` → `User::where('email', ...)->first()`).
- Missing/unmapped HOD behavior:
  - If `hod_user_id` null, employee not found, or employee has no email → `userForEmployeeId` returns `null` (`EscalationRecipientResolver.php:115-122`).
  - Escalation then falls back: `hod_user_id` → `manager_user_id` → all Users with role `hod` having `department = id_department` (`EscalationRecipientResolver.php:55-77`, roles query at `:69-71`) → admin_tenant users + `Log::critical` (`EscalationRecipientResolver.php:79-110`).
  - Work-order creation notification: no HOD → skips notify, logs reason distinguishing "departemen tidak ditemukan" / "HOD belum di-set" / "HOD tidak memiliki nomor telepon" (`app/Http/Controllers/WorkManagament/WorkOrderController.php:244-267`).
- Old HOD cleared? **No explicit clearing.** If the API omits `hod_user_id` (or sends `null`) on a later sync, `updateOrCreate` writes `'hod_user_id' => null` and thereby nulls the previous value (`SyncDepartments.php:49`). But if the department disappears from the payload entirely, its old HOD stays. So clearing happens only on overwrite-by-absence, never via dedicated logic.
- Downstream consumers of the synced HOD (for awareness in opti-works): WhatsApp HOD review authorization (`WhatsAppWebhookController.php:135-140`), HOD notification on WO creation (`WorkOrderController.php:244-249`), extend-request routing/notifications (`ExtendRequestController.php:245-257`), H+5 escalation chain (`EscalationRecipientResolver.php:55-77`), HOD review pages (`WorkOrderController.php:413-448`).

---

## 5. Employee sync coupling

- Sibling commands:
  - `app/Console/Commands/SyncEmployees.php`: `app:sync-employees` attribute (`:16`), `$signature = 'sync:employees'` (`:23`); hits `/api/positions` first then `/api/users` (`SyncEmployees.php:33`, `:29`); writes `Position` (match `id_position`; fields `nama_position`, `id_department` ← `department_id`, `id_division` ← `division_id`; `SyncEmployees.php:43-50`) then `Employee` (match `id_employee`; fields `nama_employee`, `nik_employee`, `email`, `number` ← `whatsapp_number`, `photo_url`, `id_department` ← `department`, `id_division` ← `division`, `id_position` ← `position`, `last_login_ip`; `SyncEmployees.php:71-87`).
  - `app/Console/Commands/SyncDivisions.php`: `app:sync-divisions` (`SyncDivisions.php:15,21`); hits `/api/divisions`, writes `Division` match `id_division`, fields `nama_division`, `kode_division`, `id_department` ← `department_id` (`SyncDivisions.php:44-51`).
- Dependency direction: **department sync does NOT depend on employees; employees/positions reference department ids.** HOD mapping requires `Employee` rows to exist (mapping chain needs `Employee::find(hod_user_id)` with an `email`), so for the HOD-to-User join to work, employees must have been synced (order: departments → employees, or at least employees before anything resolves HOD users). Scheduler only registers departments (`routes/console.php:10`); employees/divisions are not scheduled — presumably run manually.
- Employee payload uses **named keys `department`/`division`/`position`** (not `_id`) mapping to the synced UUIDs (`SyncEmployees.php:82-84`).

---

## 6. Config / container / queue / events

- Config: only the `services.optigate_portal` array (`config/services.php`, keys `url`, `token`). No dedicated `config/optigate.php`.
- No service-container bindings: sync logic lives entirely in the command class; no repository/service class for it (only `app/Console/Commands/SyncDepartments.php`).
- No queue/event side effects: sync commands are synchronous; no `ShouldQueue` jobs or events for sync (the only `ShouldQueue` classes in `app/Notifications/` are WO notifications). `Artisan::call` runs in-process during the HTTP request (`DepartmentController.php:57`).

---

## 7. Tests

- `tests/Feature/DepartmentSyncTest.php` (the dedicated suite):
  - Setup: config override `services.optigate_portal.url = 'https://optigate.test'`, super-admin user factory (`DepartmentSyncTest.php:18-24`).
  - `test_guests_cannot_access_departments_index` — guest GET redirects to SAML (`:26-31`).
  - `test_guests_cannot_trigger_departments_sync` — guest POST `departments.sync` redirects to SAML (`:33-38`).
  - `test_authenticated_users_can_view_departments_index` — Inertia `Departments/Index`, 3 paginated rows + filters (`:40-53`).
  - `test_server_side_search_filters_departments` — search on `kode_department`/`nama_department` (`:55-70`).
  - `test_sync_pulls_departments_from_optigate_api` — `Http::fake('*/api/departments')` returning `{"data":[{id,name,code}×2]}` (`:72-90`); asserts POST redirects with success toast (`:97-101`), DB count `tb_department = 2` (`:103`), and row contents `id_department`/`nama_department`/`kode_department` (`:104-108`). Note: the mock has **no `hod_user_id`**, asserting the `?? null` default path.
- Related suites exercising HOD semantics (not the sync itself):
  - `tests/Feature/DeadlineEscalationTest.php` — H+3 TL routing (`:99-124`), cross-department TL exclusion (`:126-147`), H+5 fallback to department role `hod` users (`:149-171`), `hod_user_id` preferred over role-based HODs with `Employee::create` + department `hod_user_id` (`:173-194`).
  - `tests/Feature/ExtendRequestRoutingTest.php` — `hod_user_id` presence is NOT proof of a Team Leader; routing goes straight to HOD approval (`:73-92`), `departmentHasTeamLeader()` behavior (`:94-115`).
  - `tests/Feature/WorkOrderWahaNotificationTest.php` — department with HOD (`hod_user_id = $hod->id_employee`) sends WA notification; department with `hod_user_id = null` logs failure (`:51-102`).
  - `tests/Feature/DivisionSyncTest.php` — division payload shape incl. `department_id` FK (`:80-110`).

---

## 8. Gotchas (all verified in code)

1. **Command-name duplication trap:** the `#[Signature('app:sync-departments')]` attribute overrides the `$signature = 'sync:departments'` property (`SyncDepartments.php:12,21` + `vendor/.../Command.php:143-152`). Scheduler and controller use `app:sync-departments` (`routes/console.php:10`, `DepartmentController.php:57`). In opti-works, register exactly one name.
2. **No `with=hod`, no pagination:** HOD comes as flat `hod_user_id` on each department item; client sends no query params and assumes one full response (`SyncDepartments.php:34-39`). If the real portal paginates beyond page 1, this client silently misses rows.
3. **No transactions, no retries:** single `try/catch (\Throwable)` around the whole HTTP+write block (`SyncDepartments.php:33-65`); grep confirms no `DB::transaction`/`retry` anywhere in `app/Console/Commands/`. A mid-loop failure leaves earlier `updateOrCreate`s committed (no rollback).
4. **No deactivate/delete:** stale departments (absent from API) persist forever; `kode_department` has a `unique` constraint from the base migration (`2026_06_26_012154_add_department_table.php:15`) — if API `code` collides across two non-null codes the second `updateOrCreate` throws.
5. **`name` is not null-safe:** `$dept['name']` without `?? null` (`SyncDepartments.php:47`) — an item missing `name` (or non-array item) throws and aborts the rest of the loop.
6. **401/5xx handling:** non-2xx → `$this->error` + `Log::error` with the raw response body (`SyncDepartments.php:58-63`); connection exception/timeout → `Log::error` (`:64-67`). HTTP failure never bubbles to the controller — the UI always shows "success" toast because `Artisan::call` succeeds even when the API call failed (`DepartmentController.php:56-60`). Test only covers the 200 path.
7. **Tenant scoping vs CLI:** no authenticated user in artisan context → `TenantService::getCurrentTenantId()` null (`TenantService.php:5-13`) → TenantScope inactive (`TenantScope.php:11-18`) → synced rows get `tenant_id = null` on insert (`TenantAware.php:14-20`). Replicating in opti-works (Spatie single-DB tenancy) needs an explicit decision: either set a tenant context before syncing or mark master-data rows tenant-nullable/global.
8. **HOD id semantics:** `hod_user_id` = `tb_employee.id_employee`; local User resolution is via email match (`Employee.email` → `User.email`) (`EscalationRecipientResolver.php:113-125`). If opti-works keys users differently, this chain is the integration point to adapt.
9. **Scheduler cadence:** `everyMinute()` with no `withoutOverlapping()` (`routes/console.php:10`) — a slow portal (>60 s) could overlap runs; `updateOrCreate` makes this idempotent-ish but two concurrent runs can double-process the same rows.
10. **Employees must exist before HOD resolution is meaningful** (mapping needs Employee rows with emails — see §5); department sync itself is independent of employees, but downstream HOD consumers are not.

---

## Replication checklist (for opti-works)

- Command `app:sync-departments` (drop the duplicate property/attribute duality), POST `/departments/sync` route behind `department.update` permission, Inertia button `router.post(sync.url())` with Wayfinder route.
- `config/services.php` → `optigate_portal.url/token` ← `WEB_PORTAL_URL`/`WEB_PORTAL_TOKEN`; add both to `.env.example` (missing in opti-work2).
- `GET {url}/api/departments`, Bearer token, 10 s timeout, parse `json('data') ?? json()`, `updateOrCreate` on API id; fields `name→nama_department`, `code`, `hod_user_id`, `manager_user_id` (all `?? null` except `name`).
- Decide explicitly: stale-row policy (opti-work2 has none) and tenant attribution (opti-work2 leaves `tenant_id = null` in CLI).

---

## Parity outcome (2026-10-20, implemented in opti-works)

State at comparison time: opti-works ALREADY carried this feature (commit `b0be63f`, 2026-10-17) in a form that exceeds the opti-work2 reference on most axes — per-company endpoint `GET /api/companies/{companyId}/departments` with pagination (`paginate`/`per_page`/`page`, MAX_PAGES guard), retries (connection + 5xx, 3 attempts growing backoff), `DB::transaction` upsert, deactivate-on-disappearance (`is_active = false`), idempotent key `(tenant_id, optigate_department_id)`, landlord-side per-tenant loop, sync button + flash, and HOD via the portal `hod` relation (`with=hod`) bridged `employees.optigate_user_id → users.employee_id` + a second `resolveHod` pass writing `hod_employee_id`.

The ONLY behavior present in opti-work2 but missing in opti-works was the **manager**: opti-work2 writes `manager_user_id` from the portal (`SyncDepartments.php:50`), and opti-works consumes `departments.manager_user_id` downstream (`app/Services/HodNotificationResolver.php:23-24` fallback chain, `app/Console/Commands/CheckDeadlineEscalation.php:190-194`, `app/Http/Controllers/WorkOrderController.php:30-32` HOD/manager WO scoping, departments/show.tsx "Deputy / Manager" row) but its sync never filled it. Portal API doc (`optigate-complete-api-docs.md` §3.1, `with` param) lists `manager` as a department relation shaped like `hod`: `{"id": "<user uuid>", "name", "email"}`.

Implemented (all inside `app/Console/Commands/SyncDepartments.php` + tests — no changes elsewhere):
1. Request now sends `with=hod,manager` (was `with=hod`).
2. `upsertDepartments` also maps `manager_user_id` from `dept['manager']['id']` through the same portal-UUID→users.id bridge (stays correct when the employee is synced but unlinked: null, not dangling).
3. `resolveHod` collects manager UUIDs into the same lookup batch and mirrors the HOD semantics.
4. Shared map builder extracted to `portalUserMap()` (built once per tenant in `handle()`, passed to both passes — previously rebuilt inside `upsertDepartments`).
5. Tests: `tests/Feature/SyncDepartmentsTest.php` +2 (`links manager_user_id via the portal manager relation`, `clears manager_user_id when the portal no longer lists a manager`) — suite 16/16.

No new migration needed: `departments.manager_user_id` exists since `2026_10_02_000000_create_organization_tables.php:39`.
