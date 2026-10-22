# Durable Audit Trail for Sensitive Account Actions

**Date:** 2026-10-22
**Scope:** Research only — replacing ad-hoc `Log::info`/`Log::warning` calls for employee account mutations (link/unlink, ban/unban, role sync) with a durable, queryable audit table. Optional-scope candidates (role/permission CRUD, tenant switch/impersonation) are inventoried but marked optional. Hard constraint honored: **no new composer dependency is proposed**; the already-installed package inventory is reported as fact only (see §8). No code was written; every claim cites `path:line`.

---

## 1. Current state — inventory of mutation/log sites

| Site | file:line | Captured today | Missing for audit |
|---|---|---|---|
| **Account link (success)** `Log::info('Employee account linked.')` | `app/Http/Controllers/MasterData/EmployeeController.php:306-311` (write at `287-290`, inside `DB::transaction`) | `employee_id`, `user_id`, `tenant_id`, `actor_id` | IP, user agent, before/after JSON (`employee_id: null → <id>`, `user.user_id: null → <id>`), action string, subject type, target email snapshot, durable/queryable storage (rotating log via `opcodesio/log-viewer`, `composer.json:21` — not a DB row), no relation to request/URL |
| **Account link (race failure)** `Log::warning('Employee account link failed.')` | `EmployeeController.php:294-299` (catch of `QueryException` at `291`) | same ids + `exception` message | IP/UA, actor — and it lives only in the log channel; nothing in DB |
| **Account unlink** `Log::info('Employee account unlinked.')` | `EmployeeController.php:337-342` (write at `335`, **outside any transaction**) | `employee_id`, `user_id`, `tenant_id`, `actor_id` | IP, UA, before/after JSON (`employee_id: <id> → null`), role snapshot at time of unlink, atomicity with the audit row |
| **Ban** — *no log at all* | `EmployeeController.php:128-137`; write `forceFill(['banned_at' => now()])->save()` at `134`; gate `350-353` (superadmin only) | **nothing** | everything: actor id/email, action, target, tenant, before/after (`banned_at: null → ts`), IP, UA |
| **Unban** — *no log at all* | `EmployeeController.php:142-151`; write at `148` | **nothing** | everything (`banned_at: ts → null`) |
| **Role sync (`syncAkunRoles`)** — *no log at all* | controller `EmployeeController.php:207-254`; **exact write site:** `DB::transaction(...)` `249-251` → `$akun->syncRoles($final)` at `250`; gate `209-213`; pair-invariant guards `238-247` | **nothing** | everything, esp. **before/after role arrays** (`$akun->getRoleNames()` before vs `$final` after), actor, IP, UA |
| Role sync validation (context) | `app/Http/Requests/Employee/SyncAkunRolesRequest.php:15-18` (`authorize()` returns `true`; authz lives in controller), rules `31-60` | n/a (validation only) | — |
| **Role CRUD** *(optional scope)* | `app/Http/Controllers/RoleController.php`: store `58-69` (create `62`, `syncPermissions` `65`), update `71-88` (`81`, `84`), destroy `90-105` (`102`), syncPermissions `107-125` (`122`); global surface docblock `18-23` | **nothing** | actor, action, before/after name+permission set; note roles are **global, not tenant-scoped** (`app/Models/Role.php:22-23`) |
| **Permission CRUD** *(optional scope)* | `app/Http/Controllers/Settings/PermissionController.php`: store `66-82` (transaction `72`), update `84-91` (`88`), destroy `93-101` (`101`) | **nothing** | same as role CRUD |
| **Tenant switch (POST)** `Log::info('Tenant switched')` | `app/Http/Controllers/TenantSwitchController.php:70-80` | user id/email, `from_tenant`, `to_tenant`, target route/path, **IP `78`, UA `79`** | DB durability, subject morph, before/after (this one is already close to an audit row) |
| **Tenant switch (signed URL)** `Log::info('Tenant switched')` | `TenantSwitchController.php:138-146` (called at `135` via `persistSwitch`) | same + `is_impersonating` `143`, IP `144`, UA `145` | DB durability |
| **Stop impersonating** `Log::info('Impersonation stopped')` | `TenantSwitchController.php:227-231` | user id/email, `returned_to_tenant` | IP, UA, DB durability |
| **Tenant made current (listener)** `Log::info('Tenant made current')` | `app/Listeners/TenantSwitchListener.php:18-28` (IP `25`, UA `26`, URL `27`) | richest context today | **volume hazard:** fires via `TenantMadeCurrentEvent` on essentially every web request where a tenant resolves — `bootstrap/app.php:52-53` appends `IdentifyTenant` to the global web group, `IdentifyTenant.php:21-25` calls `makeTenantCurrentAction`, and `TenantFinder` falls back to session `34-42` and home tenant `44-56`. This is a *request* log, not a switch log — do not audit here (see §5, Phase 3) |
| Other programmatic role writers *(later/optional)* | `app/Services/LoginProvisioningService.php:59,94`; `app/Console/Commands/GrantSuperAdminRole.php:76`; `app/Console/Commands/SyncTenantData.php:71` | `Log::critical` only for missing guest role (`LoginProvisioningService.php:87`) | system-driven, no HTTP actor — must be distinguishable from human actions |

**Conclusion of inventory:** only link/unlink log anything, and even those lack IP/UA/before-after; ban, unban, and role sync write **nothing** durable.

---

## 2. House patterns to follow

### 2.1 `work_order_events` — the append-only event table (primary house style)

- Migration `database/migrations/2026_10_17_030526_create_work_order_events_table.php`:
  - Docblock states the intent: *"Append-only history per work order … records every transition as an event row"* (`:7-11`).
  - Columns: `uuid('id')->primary()` (`:17`), `string('tenant_id')` (`:18`) + explicit string FK `cascadeOnDelete` (`:28`), `foreignUuid('work_order_id')->…cascadeOnDelete()` (`:19`), `foreignId('user_id')->nullable()->…nullOnDelete()` (`:20`), `string('action', 40)` (`:21`), `from_status`/`to_status` (`:22-23`), `json('meta')->nullable()` (`:24`), `timestamps()` (`:25`), index `['work_order_id','created_at']` (`:27`).
- Model `app/Models/WorkOrderEvent.php`:
  - Append-only contract in docblock: *"Rows are never updated or deleted (cascade only follows its work order)"* (`:14-17`).
  - Traits: `BelongsToTenant, HasFactory, HasUuids` (`:41`); `#[Fillable([...])]` attribute style (`:30-37`); `meta` array cast (`:69-74`).
  - **Static `record()` helper** — the exact write API to mirror: `WorkOrderEvent::record($workOrder, $user, $action, $from, $to, $meta)` (`:46-62`), plus `workOrder()`/`user()` relations (`:79-90`).
- Write sites — **explicit controller/service-level call, right after the mutation, inside the business transaction**:
  - `app/Services/WorkOrderService.php:59` opens `DB::transaction`, `review()` updates at `80-85`, then `WorkOrderEvent::record(...)` at `87-94`, then `notify()` at `96`.
  - `assign()` transaction opens `115`, update `148-156`, `WorkOrderEvent::record(...)` at `167-180`. A non-audit operational warning stays a plain `Log::warning` (`183-186`) — i.e. **house rule of thumb: state transitions → event row; operational/edge-condition noise → log**.
- No other event/history table exists in this repo besides `work_order_events`, `notifications` (Laravel bell history, `2026_10_03_000000_create_notifications_table.php:14-21`, no `tenant_id` column) and `wa_messages` outbox (§2.2).

### 2.2 `WaMessage` tenancy precedent (deliberately **no** `BelongsToTenant`)

- Migration `database/migrations/2026_10_21_000001_create_wa_messages_table.php:13-17` — *"Sengaja TANPA trait BelongsToTenant: baris ditulis dari dalam transaksi bisnis dan dibaca kembali oleh job di queue yang bisa berjalan tanpa konteks tenant saat ini … `tenant_id` disimpan sebagai data, sama seperti kolom tenant_id pada tabel notifications yang juga global-scope-free."*
- Model `app/Models/WaMessage.php:19-20` repeats the rule; class uses **only `HasUuids`** (`:52-54`).
- Schema: `uuid('id')->primary()` (`:24`), `tenant_id` string nullable (`:25`) with `nullOnDelete` FK (`:42`), `event` (`:28`), `payload` text (`:31`), status machinery (`:32-35`).
- Reader is a `NotTenantAware` job: `app/Jobs/SendWaMessageJob.php:41` + docblock `:36-38` ("job hanya membaca outbox by id tanpa scope"); `queues_are_tenant_aware_by_default => true` at `config/multitenancy.php:41` makes this explicit opt-out necessary.
- **Outbox write is inside the business transaction:** `app/Channels/WahaWhatsAppChannel.php:22-24` — *"Baris ditulis di dalam transaksi bisnis yang sama dengan `notify()`: rollback membatalkan pesanan kirim sebelum sempat dieksekusi"*, actual `WaMessage::create([...])` at `:77-88`, job dispatch at `:90-92` with after-commit semantics (`SendWaMessageJob.php:55` `$this->afterCommit()`).

### 2.3 What `BelongsToTenant` actually does (why it matters for audit)

`app/Models/Concerns/BelongsToTenant.php`:
- Global scope: when `Tenant::current()` is null it injects `whereRaw('1 = 0')` — **every query returns zero rows** (`:17-21`); with a tenant it filters `tenant_id` (`:23`).
- `creating` hook auto-stamps `tenant_id` from the current tenant when empty (`:26-32`).
- Route context: employee mutations run inside the `{tenant}` prefix with `tenant.access` middleware (`routes/web.php:130, 146-159`) so a tenant **is** current; but Hak Akses (role CRUD) is deliberately *outside* the tenant prefix (`routes/hak-akses.php:7-10`) and permissions likewise (`routes/settings.php:31`) — there `Tenant::current()` comes from session/home-tenant fallbacks (`app/TenantFinder.php:34-56`), i.e. it may be an **arbitrary or null** tenant.
- Used by 7 models today (`WorkOrder.php:73`, `Employee.php:37`, `WorkOrderEvent.php:41`, `Department.php:33`, `Division.php:29`, `Position.php:29`, `TenantWaSession.php:41`) — but **not** `WaMessage` and not `User`.

---

## 3. Proposed design (no code — concrete shape only)

### 3.1 Table: `audit_events`

Name follows the subject-first house convention (`work_order_events`, `wa_messages`, `tenant_wa_sessions`); `audit_events` (rather than `employee_account_events`) avoids a rename migration when Phase 2 (role/permission) and Phase 3 (tenant switch) arrive. Alternative if scope stays minimal: `employee_account_events`.

| Column | Type | Notes / precedent |
|---|---|---|
| `id` | `uuid` PK | **UUID, not ULID, not bigint** — newest precedent: `work_order_events:17`, `wa_messages:24`, `tenant_wa_sessions:23`, `notifications:15` all `$table->uuid('id')->primary()`. (ULID is only `tenants.id` — `0001_01_01_000100_create_tenants_table.php:22` + docblock `:13-17`; bigint survives only on legacy/Spatie tables: `users` `$table->id()` `0001_01_01_000200_create_users_table.php:18`, `roles` `App\Models\Role` `@property int $id` `app/Models/Role.php:10`.) Model uses `HasUuids` like `WorkOrderEvent.php:41`. |
| `tenant_id` | `string`, **nullable**, FK → `tenants.id`, `nullOnDelete` | Nullable because Phase-2 role/permission actions are global (`routes/hak-akses.php:7-10`); employee/user actions always fill it from `$employee->tenant_id`. `nullOnDelete` (like `wa_messages:42`) instead of `work_order_events`' `cascadeOnDelete` (`:28`) so audit history is not erased with the tenant — trade-off vs house: deviation is deliberate, note it in the migration docblock. |
| `actor_user_id` | `foreignId` → `users`, nullable, `nullOnDelete` | precedent `work_order_events:20` |
| `actor_email` | `string`, nullable | snapshot so the trail survives account deletion/email change; logs already capture email (`TenantSwitchListener.php:22`, `TenantSwitchController.php:72`) |
| `subject_type` | `string(50)` | e.g. `App\Models\Employee`, `App\Models\User`, `App\Models\Role`, `App\Models\Tenant` |
| `subject_id` | `string(36)`, indexed with type | **string, not `foreignUuid`/`morphs`**: subjects mix uuid PKs (`employees` `organization migration:59`) with bigint PKs (`roles`) — `['subject_type','subject_id']` composite index |
| `action` | `string(40)` | house length precedent `work_order_events:21`; values: `akun.link`, `akun.unlink`, `employee.ban`, `employee.unban`, `akun.roles.sync` (+ Phase 2/3 names) |
| `before` | `json`, nullable | `{employee_id: null}`, `{roles: [...]}`, `{banned_at: null}` |
| `after` | `json`, nullable | `{employee_id: ...}`, `{roles: [...]}`, `{banned_at: ...}` |
| `ip_address` | `string(45)`, nullable | precedent: `sessions.ip_address` `0001_01_01_000200_create_users_table.php:45`; already captured today at `TenantSwitchController.php:78,144` |
| `user_agent` | `text`, nullable | precedent: `sessions.user_agent` `..._create_users_table.php:46` |
| `context` | `json`, nullable | extras: URL/route (`TenantSwitchListener.php:27` pattern), guard notes (e.g. pair-invariant message `EmployeeController.php:239`), `target_route`, failure reason |
| `created_at` | timestamp | `timestamps()` per `work_order_events:25`; `updated_at` exists but is semantically unused (append-only). Do **not** add a `UPDATED_AT = null` divergence unless desired — house keeps plain `timestamps()`. |

Indexes: `['subject_type','subject_id']`, `['tenant_id','created_at']` (read path: one employee's history in one cabang), FK indexes on `actor_user_id`, optional `action`.

### 3.2 Model: `App\Models\AuditEvent`

- `use HasUuids;` + `#[Fillable([...])]` attribute (style: `WorkOrderEvent.php:30-37`), `casts()` for `before`/`after`/`context` (`WorkOrderEvent.php:69-74`).
- **Static `record(...)` helper** mirroring `WorkOrderEvent::record()` (`WorkOrderEvent.php:46-62`): signature along the lines of `record(string $action, ?string $tenantId, string $subjectType, string $subjectId, ?array $before, ?array $after, ?User $actor, array $context = [])` — one place that also resolves `actor_email` and `request()->ip()/userAgent()` when an HTTP request is current (same pattern `TenantSwitchController.php:78-79` uses inline today).
- Append-only contract in the class docblock (copy the wording style of `WorkOrderEvent.php:14-17` / `WaMessage.php:15-17`): rows are never updated or deleted; no `update`/`delete` helpers exist; lifecycle is `create` only.

### 3.3 `BelongsToTenant`? — **No.** Store `tenant_id` as data (recommended)

Reasons, in order of weight:

1. **Silent-empty failure mode.** With the trait, any query issued while no tenant is current returns `1 = 0` → zero rows (`BelongsToTenant.php:17-21`). For an audit trail, "invisibly empty" is the worst possible failure (landlord-wide reads, console/queue reads, tests). `WaMessage` was exempted for exactly the cross-context read problem (`wa_messages migration:13-17`).
2. **Mis-attribution on global surfaces.** The `creating` hook stamps the *current* tenant (`BelongsToTenant.php:26-32`), and on `/hak-akses` / `/settings/permissions` (outside `{tenant}` prefix) the "current" tenant is a session/home-tenant fallback, not the scope of the action (`TenantFinder.php:34-56`). A global role change would get an arbitrary or missing `tenant_id`.
3. **Landlord-wide reads matter.** Superadmin/auditor views spanning cabangs (roles are *"Global model — never scoped by tenant"*, `Role.php:22-23`) should not need `withoutGlobalScope()` gymnastics. The repo already has the explicit-filter idiom: `EmployeeController::resolveEmployeeFromRoute()` uses `withoutGlobalScope('tenant')->where('tenant_id', ...)` (`EmployeeController.php:193-196`).
4. **Cross-cabang leakage is still prevented** — by always passing `tenant_id` into `record()` from the resolved employee (`$employee->tenant_id`) and filtering reads with an explicit `where('tenant_id', ...)` in the controller, exactly how `linkAkun` already validates account scope (`EmployeeController.php:275`).

Counterpoint (documented, not adopted): the trait would auto-protect tenant isolation with zero per-query discipline; the cost is rows that vanish without a tenant context — unacceptable for audit.

### 3.4 Who writes it: **explicit controller-level `AuditEvent::record()` calls**, not model events

- House precedent is explicit: only `WorkOrderService` writes history, at two hand-picked call sites (`WorkOrderService.php:87`, `:167`) — no model observers anywhere in the codebase.
- Model events on `Employee`/`User` would mis-fire on **programmatic** writes: the Optigate portal sync rewrites employee rows routinely (e.g. ban must survive syncs — `tests/Feature/EmployeeBanTest.php:144` "keeps the ban flag when the portal sync runs"; sync writers at `app/Console/Commands/SyncEmployees.php:132`), producing either audit noise or an observer that must special-case every command. Login provisioning assigns roles with no HTTP actor (`LoginProvisioningService.php:59,94`) — auditable later, but it must be an *explicit* call, not an ambient event.
- Placement per site (all inside the same transaction as the mutation):
  - `linkAkun`: record inside the existing `DB::transaction` (`EmployeeController.php:288-290`); the `QueryException` catch path (`:291-304`) rolls back → no success row; keep `Log::warning` (`:294-299`) for the race (operational noise stays a log, per `WorkOrderService.php:183-186` style).
  - `syncAkunRoles`: capture `before` roles before `DB::transaction` (`:249`), record inside it immediately after `$akun->syncRoles($final)` (`:250`).
  - `ban`/`unban`: currently bare saves (`:134`, `:148`) — wrap save+record in one `DB::transaction` for atomicity (house pattern: transactions at `EmployeeController.php:288`, `:249`, `WorkOrderService.php:59`, `:115`, `PermissionController.php:72`).
- **After-commit / rollback semantics:** write the audit row **inside** the business transaction — same as the WA outbox row, whose docblock states rollback cancels the pending write (`WahaWhatsAppChannel.php:22-24`); there is no queue dispatch for audit, so no `afterCommit()` is needed (that hook exists only for the job, `SendWaMessageJob.php:55`). If an audit *read* is ever served from a queued job, copy the `NotTenantAware` precedent (`SendWaMessageJob.php:41`) — one more reason the table must be trait-free.
- Keep the existing `Log::info` lines during transition if desired (they cost nothing; `tests/Feature/MultitenancyTest.php:237-245` and log-spy tests such as `tests/Feature/SamlTenantResolutionTest.php:100-105` stay green), or slim them to include the new audit row id.

---

## 4. Decision options

| # | Option | Effort | Risk | Fit with constraints |
|---|---|---|---|---|
| **A** | **Plain table + explicit `AuditEvent::record()` at controller call sites, `work_order_events` shape (typed action/subject columns + `before`/`after` JSON)** — **RECOMMENDED** | Low: 1 migration, 1 model, ~6 call sites, ~8 Pest tests, optional read UI | Low: no dependency; proven house pattern; typed columns keep queries/UI simple; nullable `tenant_id` handles global actions | ✅ No new composer dependency; follows `work_order_events` + `WaMessage` precedent verbatim |
| B | Generic payload table (single `payload`/`data` JSON like `notifications.data` `..._create_notifications_table.php:18` or `wa_messages.payload` `:31`) | Low (schema smaller) | Medium: every read must parse JSON (no `where action=`, no cheap per-subject index beyond subject id); deviates from the typed `work_order_events` columns; UI filtering gets brittle | ✅ No dependency, but weaker long-term |
| C | Auditing package (spatie/laravel-activitylog-style) | Medium | **Blocked by the explicit no-new-dependency constraint**; integration with tenancy/traits unknown until installed | ⛔ Not proposed. *Presence/absence fact only:* **absent** — `composer.json` require block `:11-25` lists only `spatie/laravel-multitenancy` `:23` and `spatie/laravel-permission` `:24`; no `activitylog`/`auditor` match in `composer.lock` |

**Recommendation: Option A.** It is the literal generalization of `work_order_events` (typed `action` + JSON payload) with the `WaMessage` tenancy carve-out, costs one migration + one model, and satisfies the dependency constraint without compromise.

---

## 5. Scope phasing

**Phase 1 — minimal (recommended now):**
- `akun.link` → `EmployeeController.php:306-311` (record at `:288-290`, inside transaction)
- `akun.unlink` → `EmployeeController.php:337-342` (record with write at `:335`, wrap in transaction)
- `employee.ban` → `EmployeeController.php:134`
- `employee.unban` → `EmployeeController.php:148`
- `akun.roles.sync` → `EmployeeController.php:249-251` (before = current role names, after = `$final`)
- Read surface: `employees/show.tsx` Akun card (`resources/js/pages/employees/show.tsx:596-670`) — there is **no existing work_order_events UI** anywhere (`resources/js/pages/work-orders/show.tsx` has no history section; no controller passes an `events` prop), so the closest reusable pattern is the plain list page `resources/js/pages/notifications/index.tsx:67-120` rendered by `app/Http/Controllers/NotificationController.php:19`.

**Phase 2 — optional:** role/permission CRUD (`RoleController.php:62,81,102,122`; `Settings/PermissionController.php:72,88,101`) → rows with `tenant_id = null`; requires a global-surface read page (Hak Akses). Also flag system writers for explicit records later: `LoginProvisioningService.php:59,94`, `GrantSuperAdminRole.php:76`, `SyncTenantData.php:71`.

**Phase 3 — optional:** tenant switch/impersonation — record at the single user-driven choke point `persistSwitch()` (`TenantSwitchController.php:95-104`, called by both `switching()` `:68` and `switch()` `:135`) plus `stopImpersonating()` (`:227-231`, add IP/UA it lacks today). **Do not audit `TenantSwitchListener`** — it fires on ordinary page loads because `IdentifyTenant` is global web middleware (`bootstrap/app.php:52-53` → `IdentifyTenant.php:21-25` → `TenantFinder` session/home fallbacks `:34-56`), which would write a row per request.

---

## 6. Test conventions for this kind of table

Example file: `tests/Feature/WorkOrder/AssignWorkOrderTest.php`
- Structure: `beforeEach` seeds tenants + `RoleAndPermissionSeeder` (`:22-34`), `afterEach` → `Tenant::forgetCurrent()` (`:36-38`), fixture helper functions (`:40-81`), Pest `it(...)` cases.
- Event assertion style 1 — DB row: `$this->assertDatabaseHas('work_order_events', ['work_order_id' => ..., 'action' => 'assigned', 'from_status' => ..., 'to_status' => ...])` (`:108-113`).
- Event assertion style 2 — model query + JSON meta: `WorkOrderEvent::query()->where(...)->sole()` then `expect($event->meta['unlinked_employee_ids'])->toBe([...])` (`:151-155`).
- Authorization failure case: `->assertForbidden()` with a state assertion that **no** mutation happened (`:217-231`).
- Shared fixtures: `createTestTenant` `tests/Pest.php:41`, `initTenant` `:58`, `createUser` `:66`, `makeSuperAdmin` `:76`.
- Nearest target-specific tests to extend: `tests/Feature/EmployeeAccountLinkTest.php` (route helper `:22-28`, permission helper `:30-36`, `assertSessionHas('success')` `:44-45`, `expect($akun->fresh()->employee_id)` `:47`), `tests/Feature/EmployeeBanTest.php:44-76`, `tests/Feature/Rbac/SyncAkunRolesTest.php:49-59`.
- Note: `WorkOrderEvent` declares `use Database\Factories\WorkOrderEventFactory` (`WorkOrderEvent.php:6,40`) but **no such factory file exists** (`database/factories/` contains only WorkOrder/User/Tenant/Position/Employee/Division/Department factories) — tests therefore assert rows directly instead of `factory()`. New audit tests should follow the direct-query style, or add a real factory if `HasFactory` is declared.
- Log-spy precedent exists if a transitional log assertion is wanted: `Log::spy()` + `Log::shouldHaveReceived('warning')` (`tests/Feature/SamlTenantResolutionTest.php:100-105`).

---

## 7. opti-work2 reference repo findings

- **No audit-trail implementation exists.** None of the 40 migrations in `D:\Laravel Project\opti-work2\database\migrations\` matches `audit|activity|histor|log`; no `activity_log`/`AuditLog`/`audit_trail` symbol anywhere in `opti-work2\app`.
- What opti-work2 has instead:
  - **Last-state snapshot columns**, exactly as `work_order_events` migration observes (`2026_10_17_030526_...php:8-10`: *"opti-work2 kept only last-state snapshot columns"*): e.g. `tb_work_order.status_pekerjaan` (`opti-work2/database/migrations/2026_06_24_150000_add_workflow_fields_to_work_orders.php:35-41`) — one current value, no history.
  - **Approver FK columns instead of events**: `extend_requests.tl_approved_by` / `hod_approved_by` (`2026_08_28_100000_create_extend_requests_table.php:26-31`) and `tb_work_planning.extend_approved_by/_at` (`2026_09_22_013130_extend_tb_work_planning_table.php:18-19`) — "who approved" as columns, not rows.
  - **Notification history only**: `app_notifications` (`2026_07_08_233849_create_app_notifications_table.php:11-20`) — Laravel-style bell table, not an audit trail.
- No account link/ban audit logging found in `opti-work2\app` (no matching `syncRoles`/`employee_id` link code or link/ban `Log::info` calls) — the link/unlink feature itself is new in opti-works.

**Takeaway:** there is no reference audit implementation to port; opti-works' own `work_order_events` is the newest and only event-row precedent.

---

## 8. Already-installed packages (presence/absence only)

- `composer.json` require (`composer.json:11-25`): `inertiajs/inertia-laravel`, `laravel/chisel`, `laravel/fortify`, `laravel/framework`, `laravel/reverb`, `laravel/socialite`, `laravel/tinker`, `laravel/wayfinder`, `opcodesio/log-viewer`, `socialiteproviders/saml2`, `spatie/laravel-multitenancy`, `spatie/laravel-permission`.
- **No auditing/ activity-log package is installed** — no `spatie/laravel-activitylog` (or similar) in `composer.json`; no `activitylog`/`auditor` match in `composer.lock`.
- Report only — per the hard constraint, no installation is proposed; Option A (§4) needs no dependency.

---

## Sources

**opti-works — mutation & log sites**
- `app/Http/Controllers/MasterData/EmployeeController.php:128-151` (ban/unban, no logging; writes `:134`, `:148`), `:207-254` (syncAkunRoles; transaction `:249-251`, `syncRoles` `:250`), `:261-314` (linkAkun; transaction `:287-290`, warning `:294-299`, info `:306-311`), `:320-345` (unlinkAkun; write `:335`, info `:337-342`), `:350-353` (ban gate), `:193-196` (explicit tenant resolution idiom)
- `app/Http/Requests/Employee/SyncAkunRolesRequest.php:15-18, 31-60`
- `app/Http/Controllers/RoleController.php:58-125`, `:18-23`
- `app/Http/Controllers/Settings/PermissionController.php:66-101`
- `app/Http/Controllers/TenantSwitchController.php:70-80, 95-104, 135-146, 227-231`
- `app/Listeners/TenantSwitchListener.php:13-28`
- `app/Http/Middleware/IdentifyTenant.php:20-25`; `bootstrap/app.php:52-53`; `app/TenantFinder.php:12-58`
- `app/Services/LoginProvisioningService.php:59,94`; `app/Console/Commands/GrantSuperAdminRole.php:76`; `app/Console/Commands/SyncTenantData.php:71`
- `routes/web.php:130-159`; `routes/hak-akses.php:7-10`; `routes/settings.php:31`

**opti-works — house patterns**
- `database/migrations/2026_10_17_030526_create_work_order_events_table.php:7-29`
- `app/Models/WorkOrderEvent.php:14-17, 30-41, 46-62, 69-74, 79-90`
- `app/Services/WorkOrderService.php:59-99, 115-186`
- `database/migrations/2026_10_21_000001_create_wa_messages_table.php:7-17, 23-43`
- `app/Models/WaMessage.php:12-20, 38-54, 61-67`
- `app/Channels/WahaWhatsAppChannel.php:22-33, 77-92`; `app/Jobs/SendWaMessageJob.php:36-41, 55`
- `app/Models/Concerns/BelongsToTenant.php:12-39`; `config/multitenancy.php:27, 33-41, 53-58`
- `database/migrations/0001_01_01_000100_create_tenants_table.php:13-22`; `0001_01_01_000200_create_users_table.php:18, 42-49`; `2026_10_02_000000_create_organization_tables.php:58-75`; `2026_10_03_000000_create_notifications_table.php:14-21`; `2026_10_21_000002_create_tenant_wa_sessions_table.php:23-34`
- `app/Models/Role.php:10, 22-23`

**opti-works — UI / reads**
- `resources/js/pages/notifications/index.tsx:62-120`; `app/Http/Controllers/NotificationController.php:19`
- `resources/js/pages/employees/show.tsx:596-670`; `resources/js/pages/work-orders/show.tsx` (no history section; no controller passes `events`)

**opti-works — tests / tooling**
- `tests/Feature/WorkOrder/AssignWorkOrderTest.php:22-38, 40-81, 108-113, 151-155, 217-231`
- `tests/Feature/EmployeeAccountLinkTest.php:22-47`; `tests/Feature/EmployeeBanTest.php:44-76, 144`; `tests/Feature/Rbac/SyncAkunRolesTest.php:23-59`
- `tests/Feature/SamlTenantResolutionTest.php:100-105`; `tests/Feature/MultitenancyTest.php:237-245`
- `tests/Pest.php:41, 58, 66, 76`; `database/factories/` (no `WorkOrderEventFactory`)
- `composer.json:11-25`; `composer.lock` (no activitylog/auditor match)

**opti-work2 (read-only reference)**
- `D:\Laravel Project\opti-work2\database\migrations\` (40 files; none audit/activity/histor/log)
- `.../2026_06_24_150000_add_workflow_fields_to_work_orders.php:35-41`
- `.../2026_08_28_100000_create_extend_requests_table.php:26-31`
- `.../2026_09_22_013130_extend_tb_work_planning_table.php:18-19`
- `.../2026_07_08_233849_create_app_notifications_table.php:11-20`
