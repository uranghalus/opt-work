---
created: 2026-10-22
tags: [research, employee, ban, rbac, roleless, cross-tenant, tenancy]
status: draft
---

# Account/Identity Semantics — Q7 (Ban), Q4 (Roleless Linked), Q14 (Cross-Tenant Staff)

> **Date:** 2026-10-22
>
> **Scope:** deeper, code-level follow-up to `.scratch/employee-page/research/employee-page-improvements.md` §7 (slices), §8 (Q4/Q7/Q14), §9 (risks). That file established *what* is broken; this file answers *what the code actually does today* and what each decision would cost, so the owner can decide. Every claim is cited as `path:line` from primary sources only: application code in this repo, migrations, config, the installed packages under `vendor/`, ADRs, and the read-only reference repo `D:\Laravel Project\opti-work2`. No code was changed; this file is the only write.
>
> Note: the codebase has moved since the parent research (link/unlink routes, `employee.read` gate, unique `users.employee_id` now exist); findings below reflect the tree as of today.

---

## Q7 — Does "ban" (`employees.banned_at`) mean block login, block assignment, or record-only?

### Findings

**What ban does today (record write only):**

- `ban()` writes exactly one column: `$employee->forceFill(['banned_at' => now()])->save()` (`app/Http/Controllers/MasterData/EmployeeController.php:128-137`, write at `:134`); `unban()` clears it (`:142-151`, `:148`). Both are superadmin-only via `authorizeBan()` (`:350-353`).
- The docblock states the intended scope: "record-level block, superadmin only … The block survives portal syncs; the linked akun, if any, is untouched" (`app/Http/Controllers/MasterData/EmployeeController.php:124-127`). The feature session log says the same: "blokir level record … akun terkait tidak diubah" (`Sessions/2026-10/11/1236-employee-ban-akun-sync-saml-logout.md:9`).
- Glossary pins the vocabulary: "A record-level block on a Karyawan … Distinct from a unit usaha's status" and explicitly says *avoid* the word "nonaktif" — that belongs to a cabang (`CONTEXT.md:80-82`).

**Proof that ban is never checked anywhere else (grep evidence):**

- `banned_at` across all `*.php` in this repo occurs only in: `app/Queries/EmployeeQuery.php:38` (select), `app/Models/Employee.php:28,33,40,60-64`, `app/Http/Resources/EmployeeResource.php:25-26`, `app/Console/Commands/SyncEmployees.php:98` (comment), `app/Http/Controllers/MasterData/EmployeeController.php:134,148`, `database/migrations/2026_10_11_000001_add_ban_and_sync_identity_to_employees_table.php:13,27`, and tests (`tests/Feature/EmployeeBanTest.php`, `tests/Feature/EmployeeShowTest.php`).
- `isBanned()` is called only by `EmployeeResource` and tests (`app/Models/Employee.php:62-65`; `app/Http/Resources/EmployeeResource.php:25`; `tests/Feature/EmployeeBanTest.php:57,62`).
- **No occurrence** in `SamlController`, `LoginProvisioningService`, `LoginLanding`, any middleware, or the Fortify wiring. Conclusion: ban is currently **record-only** — it blocks neither login nor any request path.

**Login flows and exact insertion points:**

- **SAML path:** `acs()` → `loginUser()` (`app/Http/Controllers/SamlController.php:56-118`, `:75`, `:231-282`) → `Auth::login($user)` at `:273` (the only `Auth::login` call in `app/`, confirmed by grep). An existing deny seam sits right there: `if (! $user->isSuperAdmin() && ! $user->roles()->exists()) { return $this->landing->deny('no_role'); }` (`SamlController.php:84-86`). A ban check would slot immediately after `:75` (account already resolved) or before `:273`; denial copy for a new reason key goes into the fixed map at `routes/web.php:94-109` (`no_role` copy at `:100`). Caveat: SAML does **not** auto-link non-platform accounts (`SamlController.php:252-271`; only the platform account self-links, `app/Services/LoginProvisioningService.php:65-73`), so a link-keyed check misses unlinked accounts — full coverage needs an email match against the resolved tenant (existing pattern: case-insensitive email lookup `Employee::withEmailAnyTenant()` at `app/Models/Employee.php:51-56`; tenant is resolved after login at `SamlController.php:88-109`).
- **Password path:** Fortify registers `POST login` (`php artisan route:list --name=login` → `login.store` › `Laravel\Fortify\AuthenticatedSessionController@store`). The pipeline is `AttemptToAuthenticate` → `PrepareAuthenticatedSession` → the app's custom response (`vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:58-63,71-92`). The app's `LoginResponse::toResponse()` delegates to `LoginLanding::redirect()` (`app/Http/Responses/LoginResponse.php:24-27`), and `LoginLanding::deny()` already implements "log out the fresh session + redirect to `saml.denied`" (`app/Services/LoginLanding.php:29-36`; role denial at `:49-51`). Documented extension points: `Fortify::loginThrough()` (`vendor/laravel/fortify/src/Fortify.php:229-232`), `Fortify::authenticateUsing()` (`:251-254`), or `config('fortify.pipelines.login')` (`vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:79-83`). Note password login is deliberately *not* provisioned (`docs/adr/0006-guest-role-and-platform-account.md:24-28`), so a ban check must not be folded into `LoginProvisioningService`.
- **Active sessions:** no middleware reads `banned_at`. The web stack is `IdentifyTenant`, `HandleAppearance`, `HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets` (`bootstrap/app.php:52-57`) — the natural place for an ongoing-session guard; `EnsureTenantAccess` only checks route-tenant vs home tenant (`app/Http/Middleware/EnsureTenantAccess.php:17-39`).

**Session revocation mechanics:**

- Driver is `database`: `config/session.php:21` (`env('SESSION_DRIVER', 'database')`), `.env.example:38` (`SESSION_DRIVER=database`); tests force `array` (`phpunit.xml:31`).
- The `sessions` table exists with an indexed `user_id` (`database/migrations/0001_01_01_000200_create_users_table.php:42-49`), and the database handler stores `payload['user_id']` on write (`vendor/laravel/framework/src/Illuminate/Session/DatabaseSessionHandler.php:228`). Revoking all of one user's sessions therefore = `DELETE FROM sessions WHERE user_id = X` at ban time (`EmployeeController::ban`, `:128-137`).
- Framework is Laravel **v13.34.0** (`composer.lock:1657-1658`; constraint `laravel/framework: ^13.17` at `composer.json:16`). Its auth guard exposes only `logout()` (current session; cycles remember token — `vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php:670,676-678`), `logoutCurrentDevice()` (`:700`), and `logoutOtherDevices($password)` (`:760`) — the latter's docblock requires the `AuthenticateSession` middleware and a password (`:750-753`), and `AuthenticateSession` is not registered in `bootstrap/app.php:38-71`. **There is no built-in "revoke all sessions for user X" API**; the database-row delete above is the supported mechanism. There is also no remember-me UI (`grep 'remember'` in `resources/js/` → 0 matches), so session-row deletion alone suffices.
- Password-login revocation surface at ban time: none needed beyond the rows, because SAML accounts get a random unguessable password that makes password login impossible (`SamlController.php:258-261`).

**opti-work2 (reference) — no ban concept at all:**

- `Employee` fillable has no status/ban field — only identity/org fields (`D:\Laravel Project\opti-work2\app\Models\Employee.php:24-36`).
- `grep -i 'banned|blokir|is_banned|is_active|nonaktif'` across `D:\Laravel Project\opti-work2\app\` → **zero matches** (only unrelated `.agents/skills` docs).
- `SamlController::loginUser()` performs no gating: find-or-create by email, `Auth::login`, plus a hardcoded superadmin email check (`D:\Laravel Project\opti-work2\app\Http\Controllers\SamlController.php:148-181`, role assign at `:174-176`). There is no `no_role` denial and no employee-status check.
- Its `EnsureTenant` middleware only rejects users with a null `tenant_id` (`D:\Laravel Project\opti-work2\app\Http\Middleware\EnsureTenant.php:11-20`).
- Its `RoleController` manages only the role↔permission matrix, never user↔role (`D:\Laravel Project\opti-work2\app\Http\Controllers\Settings\RoleController.php:15-72`).
- **Conclusion: the reference repo provides no blocking precedent; there is nothing to port.**

**UI wording (and its overpromise):**

- Ban dialog: "Blokir karyawan?" — "<strong>{name}</strong> akan **langsung diblokir dari penggunaan operasional pada request berikutnya**. Blokir menempel pada karyawan dan tidak ikut terhapus saat sinkronisasi portal berjalan…" (`resources/js/pages/employees/index.tsx:526-533`). This promises enforcement on the next request — which nothing implements (grep proof above).
- Buttons/labels: `Blokir ${nama}` / `Batalkan blokir` (`index.tsx:486-489`), `BannedBadge` "Diblokir" (`index.tsx:236,429`), show page badge "Diblokir" (`resources/js/pages/employees/show.tsx:534-538`), status card "Diblokir / Tidak diblokir" + "Blokir lokal, bukan status aktif dari portal." (`show.tsx:585-591`). Ban UI is superadmin-only client-side (`index.tsx:562`) and server-side (`EmployeeController.php:350-353`).
- The glossary forbids renaming to "Nonaktif" (`CONTEXT.md:82`), so the parent research's "rename to Nonaktif (data)" fallback contradicts a settled vocabulary decision; a record-only outcome must instead reword the *dialog promise*, not the term.

**Ban does not block assignment:**

- The WO assign dialog lists every employee of the target department — no `banned_at` filter (`app/Http/Controllers/WorkOrderController.php:152-156`).
- `WorkOrderService::assign()` validates only (a) employees exist in the tenant and (b) same target department (`app/Services/WorkOrderService.php:126-144`); no ban check anywhere in the method (`:113-195`).
- `AssignWorkOrderRequest` validates `exists:employees,id` only (`app/Http/Requests/WorkOrder/AssignWorkOrderRequest.php:28-29`).
- Daily Work is not built yet in this repo (`grep 'WorkDaily|DailyWork'` in `app/` → 0 matches), so WO assignment is the only assignment surface today.

### Decision options

| # | Option | Evidence-based implications | Effort | Risk |
|---|--------|------------------------------|--------|------|
| 1 | **Record-only (status quo) + honest UI** | Matches `CONTEXT.md:80-82` glossary and the "akun tidak diubah" intent (`Sessions/2026-10/11/1236…:9`). Requires rewording the dialog at `index.tsx:526-533` (it currently promises operational blocking), because renaming to "Nonaktif" is forbidden by `CONTEXT.md:82`. Banned people can still log in and be assigned. | S | Low functionality risk; **reputational risk**: "Diblokir" badge (`show.tsx:534-538`) still reads like an access block |
| 2 | **Record-only + block assignment** | One filter in the candidate list (`WorkOrderController.php:152-156`) plus one validation in the service (`WorkOrderService.php:126-144`) and one rule in the FormRequest (`AssignWorkOrderRequest.php:28-29`). Closes the "banned karyawan gets WOs" hole without touching auth. Login still possible (disclosure needed in UI copy). | S/M | Low |
| 3 | **Block login + block assignment (full enforcement)** | Login denial: extend the existing `no_role` deny pattern at `SamlController.php:84-86` (new reason key in `routes/web.php:94-109`) and the Fortify seam (`Fortify::loginThrough` / `authenticateUsing`, `vendor/laravel/fortify/src/Fortify.php:229-254`), reusing `LoginLanding::deny` (`LoginLanding.php:29-36`). Link-keyed check misses unlinked accounts (SAML does not auto-link, `SamlController.php:252-271`) → also match same-tenant email (pattern `Employee.php:51-56`). Session revocation at ban time = delete `sessions` rows (`create_users_table.php:42-49`; `DatabaseSessionHandler.php:228`; driver `config/session.php:21`). Plus option 2's assignment guards. | M | Medium: email match is only as good as `employees.email` (nullable, not unique — `2026_10_02_000000_create_organization_tables.php:63`); several seams must stay in sync; needs regression tests on both login paths |
| 4 | Do 3 **and** add audit (actor/reason/timestamp) | Audit is already an open TODO (`TODO.md:8`); without it, an enforced ban cannot answer "who blocked this and why". | M/L | Low, but widen scope — keep out of this decision |

### Recommended option

**Option 3 (block login + block assignment), implemented as: deny at both login seams keyed on link *or* same-tenant email, delete `sessions` rows on ban, and the assignment guards of option 2.** Justification: the dialog already promises exactly this behavior ("diblokir dari penggunaan operasional pada request berikutnya", `index.tsx:528-529`), so record-only requires walking back user-facing wording while the glossary forbids softening the term itself (`CONTEXT.md:82`); the deny machinery already exists verbatim for `no_role` (`SamlController.php:84-86`, `LoginLanding.php:29-36`, `routes/web.php:100`) and revocation is a single indexed delete on the default database driver (`config/session.php:21`, `create_users_table.php:44`) — i.e., every seam is identified and small. Ban is already a deliberate superadmin-only act (`EmployeeController.php:350-353`), so enforcement does not widen any privilege. **If the owner defers enforcement this iteration, the minimum acceptable outcome is option 2 + rewording the dialog copy** — leaving the current copy while ban enforces nothing is the one outcome no evidence supports.

---

## Q4 — What happens to a linked account with no role, and would auto-assigning `karyawan` at link-time violate the superadmin-only role-grant policy?

### Findings

**The link/unlink flow (what link does and does not do):**

- `linkAkun()` authorizes `Gate::authorize('akun.update')` (`app/Http/Controllers/MasterData/EmployeeController.php:261-263`), resolves an existing candidate restricted to same tenant, `employee_id IS NULL`, not superadmin (`:273-279`), then writes **only** the FK: `$akun->forceFill(['employee_id' => $employee->id])->save()` (`:287-290`). It never creates a user and **never assigns a role** — the method ends with a log line and a success flash (`:306-313`). `LinkAkunRequest` validates only `user_id` (`app/Http/Requests/Employee/LinkAkunRequest.php:23-28`).
- The candidate query has **no role filter** (`EmployeeController.php:273-279`) — a roleless account is a valid link target.
- `unlinkAkun()` clears only `employee_id`; docblock: "The akun itself, its roles, and its sessions are left intact" (`EmployeeController.php:316-319,335`), test: "unlinks the account without deleting it or its roles" (`tests/Feature/EmployeeAccountLinkTest.php:121`).

**Provisioning and the `no_role` denial:**

- Guest is granted only when the account is (not platform) AND (not superadmin) AND **`employee_id === null`** AND has no roles: `if ($user->isSuperAdmin() || $user->employee_id !== null || $user->roles()->exists()) { return; }` (`app/Services/LoginProvisioningService.php:82-84`, assign at `:94`). So a **linked, roleless account is skipped and gets nothing** (`:82`).
- The SAML landing gate then denies it: `if (! $user->isSuperAdmin() && ! $user->roles()->exists()) return $this->landing->deny('no_role');` (`app/Http/Controllers/SamlController.php:84-86`). The password path does the same via `LoginLanding::redirect()` (`app/Services/LoginLanding.php:49-51`); `deny()` logs the session out and redirects to `saml.denied` (`:29-36`).
- User-facing copy for the denial: "Akun Anda belum memiliki peran aktif. Minta administrator menetapkan peran Anda terlebih dahulu." (`routes/web.php:100`).
- Test that pins the linked-but-roleless state: "gives no guest role to an account already linked to a karyawan" → `expect($user->fresh()->getRoleNames())->toBeEmpty()` (`tests/Feature/Auth/GuestProvisioningTest.php:101-118`).

**Exact sequences that produce a linked + roleless account today:**

1. Superadmin clears all roles: `SyncAkunRolesRequest` explicitly allows it — "empty array = revoke everything allowed" (`app/Http/Requests/Employee/SyncAkunRolesRequest.php:21-23`) — and `syncAkunRoles` touches only roles, never the link (`EmployeeController.php:249-251`).
2. `linkAkun` of an already-roleless existing user (no role filter in `EmployeeController.php:273-279`), e.g. a locally seeded/command-created account, or one whose guest grant was skipped because the `guest` role row is missing (critical-log path, `LoginProvisioningService.php:86-92`).
3. *Not* from a plain SSO first login: an unlinked roleless SSO user receives `guest` before the gate sees it (`SamlController.php:266-273`; `LoginProvisioningService.php:94`; test `GuestProvisioningTest.php:68-76`).
4. Net effect of states (i) linked + `guest`: logs in, lands, but holds zero permissions — every gate denies (test `GuestProvisioningTest.php:141-184`); (ii) linked + *no role*: denied `no_role` on every login attempt until a superadmin acts.

**What ADR-0008 prohibits, exactly:**

- Decision 1: "**Only a platform superadmin may grant or revoke roles.** The check is `User::isSuperAdmin()` (the `is_super_admin` flag or the `super_admin` role), not `akun.update`." (`docs/adr/0008-superadmin-only-role-assignment.md:11`). Enforced server-side at `EmployeeController.php:209-213`.
- Decision 2 keeps `akun.update` as the gate for **link/unlink** and candidate visibility (`docs/adr/0008…:12`).
- Consequence (the directly relevant sentence): "`admin_tenant` can still link/unlink accounts, but cannot assign roles. **A newly linked roleless account needs a superadmin before it can access the app.**" (`docs/adr/0008…:20`).
- Analysis: the ADR's *prohibition* targets human grant/revoke through the role-assignment surface; system-provisioning without a human actor has explicit precedent — the guest grant at first login (`docs/adr/0006…:16-21`) and the platform self-heal that *ensures* `super_admin` + flag (`docs/adr/0006…:32-42`; `LoginProvisioningService.php:48-74`). So an automatic grant is not *literally* forbidden — but auto-`karyawan` at link-time (a) contradicts ADR-0008's stated consequence (`:20`), (b) is executed by whoever holds `akun.update` (`EmployeeController.php:263`) — i.e., an `admin_tenant` would effectively grant app access without the superadmin, creating exactly the proxy path decision 1 closes, and (c) is not a "baseline": `karyawan` carries real permissions — `division.read, department.read, employee.read, work-order.create/read/submit, workData*, daily-work.read/update, dashboard.read` (`database/seeders/RoleAndPermissionSeeder.php:298-304`) versus `guest => []` (`:326`), and ADR-0006's rationale is precisely "Zero permissions is the point: until the Super Admin grants real roles, the account can do nothing" (`docs/adr/0006…:18-20`).
- The guest precedent's guard to compare: guest is granted "to an account that has no role **and no linked Karyawan** (`users.employee_id IS NULL`)" (`docs/adr/0006…:16-21`), state-based and fail-closed (`:28-31`), and decision 5 states "An account that is merely linked still keeps guest until roles are synced — deliberate: linking and role assignment are two separate acts" (`docs/adr/0006…:49-53`).

**opti-work2:**

- There is **no link flow at all** to auto-assign roles in: `EmployeeController` has only `index`/`show`/`sync` (`D:\Laravel Project\opti-work2\app\Http\Controllers\MasterData\EmployeeController.php:18,45,55`). The user↔employee relation is implicit, matched by email: `return $this->hasOne(Employee::class, 'email', 'email');` (`D:\Laravel Project\opti-work2\app\Models\User.php:72-75`).
- Production code assigns roles in exactly two places: seeder for a dev account (`D:\Laravel Project\opti-work2\database\seeders\RoleAndPermissionSeeder.php:71`) and the hardcoded superadmin email at SAML login (`D:\Laravel Project\opti-work2\app\Http\Controllers\SamlController.php:174-176`). **No auto-assign at link time — and no `no_role` gate either**; roleless users simply fall through to per-controller `hasAnyRole` checks (e.g. `D:\Laravel Project\opti-work2\app\Http\Controllers\WorkManagament\WorkDailyController.php:37,127`). Role CRUD never touches users (`D:\Laravel Project\opti-work2\app\Http\Controllers\Settings\RoleController.php:15-72`).
- **Conclusion:** the reference leaves accounts roleless and denies-by-permission, not by-landing; it offers no precedent for link-time grants either way.

### Decision options

| # | Option | Evidence-based implications | Effort | Risk |
|---|--------|------------------------------|--------|------|
| A | **Status quo + surface a blocking warning** on the employee show `akun` card when the linked account has zero roles (payload already exposes `roles` under `akun.read`, `EmployeeController.php:74-82`) | No ADR change; the denial copy already tells the user what to do (`routes/web.php:100`); matches ADR-0008's accepted consequence verbatim (`docs/adr/0008…:20`). Downside: the account is a dead end until a superadmin signs in — and the "linked" badge looks healthy while login is impossible. | S | Low. UX-only; superadmin bottleneck accepted by policy |
| B | **Auto-assign `karyawan` at link-time** (what Q4 literally asks) | One line in the link transaction (`EmployeeController.php:287-290`). But: grants substantive permissions (`RoleAndPermissionSeeder.php:298-304`) triggered by an `akun.update` actor (`:263`) — a proxy grant path that ADR-0008 decision 1 was written to eliminate (`docs/adr/0008…:11`); contradicts the stated consequence (`:20`); reverses ADR-0006's fail-closed posture (`:18-20`). Requires an ADR-0008 amendment to be legitimate. | S/M | **High** — privilege escalation by policy, not by bug |
| C | **Auto-assign `guest` at link-time** for a roleless target (extend the ADR-0006 provisioning guard to cover linked-but-roleless accounts, or grant inside `linkAkun`) | System-provisioning precedent exists and is transactional/idempotent (`docs/adr/0006…:16-31`; `LoginProvisioningService.php:38-46`); keeps zero-permission fail-closed behavior (`docs/adr/0006…:18-20`); linked + `guest` is already a designed state — decision 5 says linked accounts keep guest until roles are synced (`docs/adr/0006…:49-53`); login lands instead of dead-ending at `no_role`, while real roles still require the superadmin → consistent with ADR-0008 (`:20`). Requires a one-paragraph amendment of ADR-0006 decision 1's "no linked Karyawan" parenthetical + a note in ADR-0008. | S | Low/Medium — ADR bookkeeping + tests (`GuestProvisioningTest.php:101-118` currently asserts the empty state) |

### Recommended option

**Option C — grant the zero-permission `guest` system role when a roleless account is linked (or when a linked roleless account logs in), keeping real-role grants superadmin-only.** Auto-`karyawan` (B) is not a baseline grant: it hands out `work-order.create`, `employee.read`, work-data and daily-work verbs (`RoleAndPermissionSeeder.php:298-304`) to anyone an `akun.update` holder chooses (`EmployeeController.php:263,273-279`), which is the exact loophole ADR-0008 closed (`docs/adr/0008…:11`) and directly contradicts its accepted consequence (`:20`). C instead reuses the settled pattern — seeder-owned, zero-permission, transactional, fail-closed (`docs/adr/0006…:16-31`) — removes the confusing `no_role` dead end for linked accounts, and still forces a superadmin to grant anything real. It needs an explicit ADR amendment (precedent: ADR-0006 itself amended the login seam). If the owner wants **zero** ADR change this iteration, fall back to A, but with the warning made blocking-visible on the employee page — a linked badge plus an impossible login is the current silent failure (`EmployeeController.php:74-82` payload already supports it).

---

## Q14 — Cross-tenant staff: one home tenant per account, or a pivot `employee_user`?

### Findings

**Schema:**

- `users.employee_id`: bare nullable `foreignUuid` at users creation (`database/migrations/0001_01_01_000200_create_users_table.php:24`), FK `nullOnDelete` added later (`database/migrations/2026_10_02_000000_create_organization_tables.php:77-79`), and **unique** since 2026-10-20: `users_employee_id_unique` (`database/migrations/2026_10_20_155833_add_unique_employee_id_to_users_table.php:37-39`; fails loudly on duplicates `:19-35`; test `tests/Feature/EmployeeAccountLinkTest.php:165-176`). Relations: `Employee::user()` is `hasOne` (`app/Models/Employee.php:102-105`), `User::employee()` is `belongsTo` (`app/Models/User.php:91-93`).
- `users.email` is **globally unique** (`0001_01_01_000200_create_users_table.php:20`). `employees.email` is nullable and **not unique** (`2026_10_02_000000_create_organization_tables.php:63`; indexes at `:72-74` are `department_id`, `(tenant_id, nik_employee)` non-unique, FK). Employee uniqueness is `(tenant_id, optigate_user_id)` (`2026_10_11_000001…:19`) plus `(tenant_id, department_id)` (`2026_10_19_000003…:12`).
- Therefore: **the same person can exist as employee rows in several tenants, but only one `users` row can ever exist for that email** — and it can hold at most one `employee_id`.
- A second membership structure already exists but is inert for authorization: `user_tenants` (`0001_01_01_000200…:51-59`), `User::tenants()` (`app/Models/User.php:107-110`), consumed only as a fallback when `tenant_id` is null in `HandleInertiaRequests::getUserTenants()` (`app/Http/Middleware/HandleInertiaRequests.php:139-147`).

**Login tenant resolution:**

- The SAML assertion carries only the **company NAME** ("verified against a real assertion dump — no code or ID attribute", `app/Services/SamlTenantResolver.php:11-17`, const `:22`) matched exactly to `tenants.name` (`:59-61`); 0 / many / inactive matches deny (`:63-92`). One assertion ⇒ one company ⇒ one tenant per login.
- `acs()` then persists that tenant as the account's home tenant **on every login**: `$user->forceFill(['tenant_id' => $tenant->id])->save();` with the comment "the assertion is the source of truth at login, so persist it" (`app/Http/Controllers/SamlController.php:112-115`). The home tenant follows the portal company attribute, not the employee link.
- Access is single-home-tenant by policy: `TenantAccess::canOperate()` returns true only for superadmin or `user->tenant_id === tenant->id` (`app/Services/TenantAccess.php:11-31`), and `operableTenants()` returns just the home tenant otherwise (`:33-44`); enforced by `EnsureTenantAccess` (`app/Http/Middleware/EnsureTenantAccess.php:34-36`) and all three switch endpoints (`app/Http/Controllers/TenantSwitchController.php:58,131,170`). `TenantFinder` falls back to `user->tenant_id` (`app/TenantFinder.php:44-56`).
- **Same email in two tenants, traced:** two `employees` rows (allowed) + one `users` row (email unique) ⇒ link must pass the same-tenant check at link time (`EmployeeController.php:275`; candidate list `:101`) and at role-write time ("Akun pengguna ini bukan milik cabang karyawan tersebut.", `:223-227`) ⇒ only the employee row in the assertion's tenant can be linked; the other tenant's row is **permanently account-less** on the page (`EmployeeQuery` eager-loads `hasOne user`, `app/Queries/EmployeeQuery.php:27`; `has_account` from `EmployeeResource.php:29`).
- **Drift failure mode (no guard today):** if the portal later asserts a different company, `acs()` silently flips `tenant_id` (`SamlController.php:115`) while the link stays → the account's home tenant no longer matches the linked employee's tenant → `syncAkunRoles` 403 (`EmployeeController.php:223-227`), `tenant.access` 403 for that branch (`TenantAccess.php:30`, `EnsureTenantAccess.php:34-36`), yet the employee page keeps showing the link (no read-side invariant check anywhere — grep found `akun->tenant_id === employee->tenant_id` only in `:223-227`).

**Every consumer of `users.employee_id` / the two relations (pivot blast radius):**

- *Column writes/reads (app code):* `app/Models/User.php:42` (fillable), `:91-93` (`employee()` FK); `app/Services/LoginProvisioningService.php:65-73` (platform self-link) and `:82` (guest guard); `app/Console/Commands/GrantSuperAdminRole.php:100-101,120`; `database/seeders/RoleAndPermissionSeeder.php:118-122`; `app/Http/Controllers/MasterData/EmployeeController.php:102` (candidate filter), `:267` (already-linked check), `:273-289` (link), `:335` (unlink).
- *`Employee::user()` consumers:* `app/Queries/EmployeeQuery.php:27`; `app/Http/Resources/EmployeeResource.php:29-35` (`has_account` + akun payload); `app/Http/Controllers/MasterData/EmployeeController.php:66-68,216,267,325`; `app/Services/WorkOrderService.php:159-165` (notify recipients + `unlinked_employee_ids` warning); `app/Console/Commands/SyncDepartments.php:269-280` (`portalUserMap` bridges portal HOD/manager UUIDs → `users.id`).
- *`User::employee()` consumers:* `app/Models/User.php:81-86` (WhatsApp number routing, deliberately `withoutGlobalScope('tenant')` for console contexts); `app/Services/HodNotificationResolver.php:27-31` (`whereHas('employee', department)`); `app/Console/Commands/CheckDeadlineEscalation.php:161-163,202-204` (team-leader/recipient lookup); `app/Models/Department.php:43-51` (`hodDisplay` photo fallback).
- *Tests touching `employee_id`:* `tests/Feature/EmployeeAccountLinkTest.php` (~15 sites, `:47-211`), `tests/Feature/Rbac/SuperadminAccountTest.php:49-125`, `tests/Feature/Auth/GuestProvisioningTest.php:111-138`, `tests/Feature/EmployeeBanTest.php:108`, `tests/Feature/EmployeeIndexTest.php:345`, `tests/Feature/EmployeeShowTest.php:52,76,100,119`, `tests/Feature/Rbac/SyncAkunRolesTest.php:28,232`, `tests/Feature/Rbac/EmployeeAkunRolesTest.php:26`, `tests/Feature/WorkOrderTest.php:153`, `tests/Feature/WorkOrder/AssignWorkOrderTest.php:89,138`, `tests/Feature/WahaGatewayTest.php:67`, `tests/Feature/SyncDepartmentsTest.php:177,249,359`, `tests/Feature/DepartmentHodDisplayTest.php:48`.
- Frontend is insulated: pages consume only derived `has_account`/`akun` props (`EmployeeResource.php:29-35`; `EmployeeController.php:116-121`) — a pivot would not change JS contracts if payload shape is preserved.

**opti-work2's cross-branch model:**

- No `users.employee_id`; the link is implicit by email: `User::employee()` = `hasOne(Employee::class, 'email', 'email')` (`D:\Laravel Project\opti-work2\app\Models\User.php:72-75`), and `Employee` carries the `TenantAware` global scope (`D:\Laravel Project\opti-work2\app\Models\Employee.php:14`) filtered by the **current** tenant when one is set (`D:\Laravel Project\opti-work2\app\Models\Scopes\TenantScope.php:12-19`). Effectively: one account resolves to *the employee row of whatever branch is current* — cross-branch identity without any pivot table (and without any stored link to audit).
- `users.email` unique (`D:\Laravel Project\opti-work2\database\migrations\0001_01_01_000000_create_users_table.php:17`); `tb_employee.email` nullable, non-unique (`D:\Laravel Project\opti-work2\database\migrations\2026_06_28_010615_create_employees_table.php:18`); single home column `users.tenant_id` (`D:\Laravel Project\opti-work2\database\migrations\2026_08_28_000001_add_tenant_id_to_tables.php:11-21`). Access check only requires a non-null tenant (`D:\Laravel Project\opti-work2\app\Http\Middleware\EnsureTenant.php:15`) — much weaker than this repo's same-tenant equality.

**Tenancy side of the tables:**

- `users` is a **global (landlord-side) model**: it does not use `BelongsToTenant`; docblock: "Global model — not scoped to a tenant: authentication must survive cabang switching. `tenant_id` records the user's home cabang." (`app/Models/User.php:35-40`, class `:44-47`). `Employee` *does* use the trait (`app/Models/Employee.php:5,37`). Consequence for option (b): an `employee_user` pivot would also be a global table (no automatic tenant scope), pointing at tenant-owned `employees` rows — every read would need explicit tenant scoping, following the existing precedents (`User.php:83` console-safe bypass; `wa_messages` intentionally scope-free for console reads, `docs/adr/0009-whatsapp-gateway.md:17`).
- Roles remain global regardless of either option: Spatie teams disabled (`config/permission.php:151`).

### Decision options

| # | Option | Evidence-based implications | Effort | Risk |
|---|--------|------------------------------|--------|------|
| a | **One home tenant per account (status quo) + document + guard** | Matches every access gate today: `TenantAccess.php:30`, `EnsureTenantAccess.php:34-36`, assertion-driven home tenant `SamlController.php:112-115`, same-tenant link `EmployeeController.php:275,223-227`. Add: (i) documented invariant (one account ↔ one tenant ↔ one karyawan), (ii) read-side warning when `akun.tenant_id !== employee.tenant_id` (the drift case `acs():115` can create silently), (iii) surface clearly that the second branch's employee row intentionally has no account. All local data is single-tenant anyway (parent research §2: 125 employees, all in `dmb`). | S | Low. Gap remains: staff genuinely working two branches must use separate accounts per branch — impossible for the same email (`create_users_table.php:20`) — i.e., their second-branch employee row stays account-less by design |
| b | **Pivot `employee_user`** | Sketch (no code): new **global** table `(user_id, employee_id, tenant_id)` with `UNIQUE(employee_id)` (mirror of today's `users_employee_id_unique`, `2026_10_20…:38`) and `UNIQUE(user_id, tenant_id)`; backfill from `users.employee_id`, then drop the column/unique. Rewrite the 6 column sites, 5 `Employee::user()` sites, 5 `User::employee()` sites listed above, plus ~15 test files. Login flow: keep assertion resolution (`SamlTenantResolver.php:47-95`) but resolve the per-tenant link after it (`SamlController.php:88-115`) instead of trusting a single FK; `acs():115`'s tenant overwrite must be reconciled with the chosen home-tenant rule. Access control must change too: `TenantAccess::canOperate/operableTenants` (`:11-44`) and `HandleInertiaRequests::getUserTenants` (`:139-147`) would need membership semantics (`user_tenants` is the existing precedent for membership, `User.php:107-110`). Roles stay global (`config/permission.php:151`) — no Spatie change. | **L** | Medium/High: touches authentication, authorization, notifications, HOD bridging (`SyncDepartments.php:269-280`), and the ban/link surfaces; global pivot over tenant data needs disciplined scoping; still capped by "one company per assertion" (`SamlTenantResolver.php:14-16`) |
| c | **a now, b on trigger** | Status quo guard + a written trigger condition ("cross-branch staff confirmed as real + second-branch employee rows must have accounts"). | S | Low |

### Recommended option

**Option (a/c): keep one home tenant per account, document the invariant, and add a drift guard — defer the pivot.** Three pieces of evidence make the pivot a *broader* project than a schema swap: (1) `TenantAccess` grants non-superadmins exactly one tenant (`app/Services/TenantAccess.php:30,39-43`) and `operableTenants()` would return the same single row regardless of links — so a pivot **alone would not let anyone work a second branch**; the access model must change with it. (2) Login can only ever pick one tenant per session because the assertion carries exactly one company name (`app/Services/SamlTenantResolver.php:11-17,59-61`) and `acs()` overwrites the home tenant every login (`SamlController.php:112-115`) — multi-membership starts at the IdP, not in our schema. (3) The blast radius is real but currently motivated by zero data: 6 column sites + 5 `Employee::user()` + 5 `User::employee()` consumers + ~15 test files, while the local snapshot has employees in only one tenant (parent research §2). Meanwhile the genuinely harmful behavior today is the silent drift at `SamlController.php:115` (link survives a home-tenant flip, breaking `EmployeeController.php:223-227` and `TenantAccess.php:30` with no warning) — cheap to guard under option (a). Reopen the pivot as an access-model decision (pivot + `TenantAccess` memberships + login lookup) when cross-branch staff are confirmed real.

---

## Sources

### opti-works — application code
- `app/Http/Controllers/SamlController.php:56-118,231-282` — `acs()`, `loginUser()`, `no_role` gate, tenant persist.
- `app/Http/Controllers/MasterData/EmployeeController.php:61-122,124-151,157-182,188-197,199-254,256-345,347-353` — show/ban/unban/roles/link/unlink/authorizeBan.
- `app/Http/Controllers/WorkOrderController.php:143-171` — assign dialog candidate list.
- `app/Http/Responses/LoginResponse.php:16-27` — password-login landing.
- `app/Providers/FortifyServiceProvider.php:29-78` — Fortify views/rate limiter.
- `app/Services/LoginProvisioningService.php:32-105` — platform self-link, guest guard.
- `app/Services/LoginLanding.php:10-62` — role-aware landing, `deny()`.
- `app/Services/SamlTenantResolver.php:11-121` — company-name resolution, denial reasons.
- `app/Services/TenantAccess.php:11-49` — home-tenant access policy.
- `app/Services/WorkOrderService.php:104-195` — assign validation (no ban check).
- `app/Services/HodNotificationResolver.php:20-46` — `whereHas('employee')`.
- `app/Http/Middleware/EnsureTenantAccess.php:17-39`; `app/Http/Middleware/HandleInertiaRequests.php:100-158` — tenant gate, `getUserTenants`.
- `app/Http/Requests/Employee/LinkAkunRequest.php:7-41`; `app/Http/Requests/Employee/SyncAkunRolesRequest.php:9-61`; `app/Http/Requests/WorkOrder/AssignWorkOrderRequest.php:28-29`.
- `app/Http/Resources/EmployeeResource.php:25-35` — `is_banned`, `has_account`.
- `app/Queries/EmployeeQuery.php:21-63` — select incl. `banned_at:38`, eager load `user:27`.
- `app/Models/User.php:35-110` — global model, `employee()`, `tenants()`, WA routing.
- `app/Models/Employee.php:33-105` — fillable, `withEmailAnyTenant`, `isBanned`, `user()`.
- `app/Models/Department.php:43-64` — `hodDisplay` uses `hod->employee`.
- `app/Console/Commands/SyncDepartments.php:262-280` — `portalUserMap` via `users.employee_id`.
- `app/Console/Commands/GrantSuperAdminRole.php:100-120` — repair-command self-link.
- `app/Console/Commands/CheckDeadlineEscalation.php:161-163,202-204` — recipient lookup via `employee`.
- `app/TenantFinder.php:12-59` — tenant from path/session/home.
- `bootstrap/app.php:18-76` — middleware stack, scheduler, guest redirect.
- `routes/web.php:16-36,80-130,146-159` — home, SAML routes + denial copy, employee routes.
- `config/session.php:21,89`; `config/fortify.php:16-143`; `config/permission.php:151`.
- `CONTEXT.md:55-104` — glossary (Akun, Role Assignment, Blokir, Cabang).
- `TODO.md:5-8` — Q7/Q4/Q14 statement + audit item.
- `Sessions/2026-10/11/1236-employee-ban-akun-sync-saml-logout.md:8-13` — ban feature intent.

### opti-works — migrations, seeders, config
- `database/migrations/0001_01_01_000000_create_framework_tables.php:14-59` — cache/queue (no sessions here).
- `database/migrations/0001_01_01_000200_create_users_table.php:17-59` — email unique, `employee_id`, `sessions`, `user_tenants`.
- `database/migrations/2026_10_02_000000_create_organization_tables.php:19-79` — org tables, employees indexes, FK.
- `database/migrations/2026_10_11_000001_add_ban_and_sync_identity_to_employees_table.php:11-20` — `banned_at`, unique `(tenant_id, optigate_user_id)`.
- `database/migrations/2026_10_19_000003_add_composite_index_to_employees_table.php:11-13`.
- `database/migrations/2026_10_20_155833_add_unique_employee_id_to_users_table.php:17-40` — unique `employee_id`.
- `database/seeders/RoleAndPermissionSeeder.php:43-49,116-122,298-327` — system roles, superadmin link, `karyawan`/`guest` grants.
- `.env.example:38`; `phpunit.xml:31`; `composer.json:15-24`; `composer.lock:1657-1664`.

### opti-works — ADRs, tests, frontend
- `docs/adr/0001-tenant-scoping.md:1-12`; `docs/adr/0005-user-role-assignment.md:7-23`; `docs/adr/0006-guest-role-and-platform-account.md:7-81`; `docs/adr/0008-superadmin-only-role-assignment.md:7-31`; `docs/adr/0009-whatsapp-gateway.md:17`.
- `tests/Feature/Auth/GuestProvisioningTest.php:68-201`; `tests/Feature/Auth/RoleAwareLoginTest.php:20-99`.
- `tests/Feature/EmployeeAccountLinkTest.php:38-222`; `tests/Feature/EmployeeBanTest.php:44-177`; `tests/Feature/EmployeeShowTest.php:25-119`.
- `resources/js/pages/employees/index.tsx:236,424-555,562,626`; `resources/js/pages/employees/show.tsx:534-591`.

### Installed packages (vendor/)
- `vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php:670-779` — `logout`, `logoutCurrentDevice`, `logoutOtherDevices` (+ AuthenticateSession requirement).
- `vendor/laravel/framework/src/Illuminate/Session/DatabaseSessionHandler.php:228,287,299` — `user_id` in payload, destroy/gc.
- `vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:58-92` — login pipeline.
- `vendor/laravel/fortify/src/Fortify.php:229-254` — `loginThrough` / `authenticateThrough` / `authenticateUsing` hooks.

### Reference repo `D:\Laravel Project\opti-work2` (read-only)
- `app/Models/User.php:37-84` — email-based implicit `employee()`.
- `app/Models/Employee.php:12-68` — no ban/status fields; `TenantAware`.
- `app/Models/Traits/TenantAware.php:12-38`; `app/Models/Scopes/TenantScope.php:10-19` — per-current-tenant resolution.
- `app/Http/Controllers/SamlController.php:140-189` — login with only hardcoded superadmin role.
- `app/Http/Controllers/MasterData/EmployeeController.php:18-55` — index/show/sync only.
- `app/Http/Controllers/Settings/RoleController.php:15-72` — role↔permission matrix only.
- `app/Http/Middleware/EnsureTenant.php:9-20` — non-null tenant check only.
- `app/Http/Controllers/WorkManagament/WorkDailyController.php:37,127` — per-controller `hasAnyRole`.
- `database/seeders/RoleAndPermissionSeeder.php:71`; `database/migrations/0001_01_01_000000_create_users_table.php:17`; `database/migrations/2026_06_28_010615_create_employees_table.php:14-25`; `database/migrations/2026_08_28_000001_add_tenant_id_to_tables.php:9-24`.
