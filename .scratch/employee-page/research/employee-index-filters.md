---
created: 2026-10-22
tags: [research, employee, filters, search, pagination, account-link]
status: draft
---

# Employee Index Filters / Search / Pagination — Research

> **Date:** 2026-10-22
> **Scope:** read-only research for the TODO item *"Employee page filters/search: filter organisasi/akun/status; pencarian akun server-side + pagination (kandidat saat ini dibatasi 100)"* (`TODO.md:9`). Primary sources only — actual code in this repo, every claim cited as `path:line`. No application code was changed; this file is the only write.
> **Sibling report:** `.scratch/employee-page/research/employee-page-improvements.md` (2026-10-20) covers the wider employee page. Some of its findings are now stale (e.g. the `banned_at` omission bug is fixed — see §1.4).

---

## Current state

### 1. Backend index

#### 1.1 Controller — `MasterData\EmployeeController::index`

`app/Http/Controllers/MasterData/EmployeeController.php:28-53`:

- Authorized by `Gate::authorize('employee.read')` (`:30`).
- Builds query via `(new EmployeeQuery)->build($request->search(), $request->sort(), $request->direction())` (`:32-36`) — **only three inputs today; no filter params are passed**.
- Paginates with `$query->paginate($perPage)->withQueryString()->through(fn (Employee $employee) => EmployeeResource::make($employee)->resolve())` (`:38-42`). `withQueryString()` keeps `search/sort/direction/per_page` on the paginator's page URLs (`:41`).
- Renders `employees/index` with exactly two props: `employees` (paginator) and `filters` = `search`, `per_page`, `sort`, `direction` (`:44-52`). **No dropdown-option props (departments/divisions/positions) are sent today.**

#### 1.2 Query — `app/Queries/EmployeeQuery.php` (full file read)

- `SORTABLE_COLUMNS = ['nik_employee','nama_employee','email','number','created_at']` (`:10-16`).
- Eager loads `['department', 'position', 'division', 'user']` (`:27`) — this is what prevents N+1 for the four relations the table renders; `has_account` derives from the loaded `user` relation (`app/Models/Employee.php:102-104`, `app/Http/Resources/EmployeeResource.php:29`).
- Column select is explicit and **includes `banned_at`** (`:38`) — so `is_banned` is truthful on the index now (`EmployeeResource.php:25-26`).
- Search (`:43-56`): single `LIKE %term%` over `nik_employee`, `nama_employee`, `email`, `number`, plus `orWhereHas` on `department` (kode/nama), `position` (nama), `division` (kode/nama). **It does NOT search the linked akun (`users.name` / `users.email`)** — there is no `orWhereHas('user', ...)` anywhere (`:45-55`).
- Sort is validated against the allowlist and defaulted (`:58-60`).
- Note: the search term is interpolated raw — `"%{$search}%"` (`:44`) — no `addcslashes` escaping of `%`/`_`, unlike the house pattern in `RoleController` (`app/Http/Controllers/RoleController.php:43`) and `TenantController` (`app/Http/Controllers/TenantController.php:44-45`).
- **There is no 100-record cap anywhere in the index path.** The index is fully paginated.

#### 1.3 Request — `app/Http/Requests/EmployeeIndexRequest.php` (full file read)

Rules (`:26-32`): `search` (nullable string max:255), `page` (integer min:1), `per_page` (`in:10,25,50,100`), `sort` (`Rule::in(EmployeeQuery::SORTABLE_COLUMNS)`), `direction` (`in:asc,desc`). Helpers: `search()` `:38-41`, `perPage()` default **10** `:46-49`, `sort()` default `nama_employee` `:54-57`, `direction()` default `asc` `:62-65`. **No `department_id` / `division_id` / `position_id` / `has_account` / `status` params exist.**

#### 1.4 Resource — `app/Http/Resources/EmployeeResource.php`

Emits `id, nik_employee, nama_employee, email, number, photo_url, created_at, is_banned, banned_at` (`:18-26`), `has_account` guarded by `relationLoaded('user')` (`:29`), `user` (id/name/email) only when the viewer holds `akun.read` (`:30-37`), and `department` / `position` / `division` via `whenLoaded` (`:38-51`).

### 2. Frontend — `resources/js/pages/employees/index.tsx`

- **Wayfinder import pattern:** `import { index, show, sync, ban, unban } from "@/routes/employees";` (`:22`). Generated definitions live in `resources/js/routes/employees/index.ts` (`index` at `:8-11`, `index.url` at `:23-44`); the runtime helper is `resources/js/wayfinder/index.ts` (`RouteQueryOptions` `:26-29`, array params serialize as `key[]` `:53-54`).
- **Local inline types** (no shared file): `Employee` `:85-100`, `Paginator` `:102-112`, `Filters` (`search, per_page, sort, direction`) `:114-119`, `PageProps` (`employees`, `filters`, `errors?`) `:121-125`. `resources/js/types/` only re-exports `auth`/`navigation`/`ui` (`resources/js/types/index.ts:1-3`) — **the index payload is typed inline on the page, not in `types/`**.
- **Search is server-side already:** a 300 ms `setTimeout` debounce flips `searchTerm` → `appliedFilters` and resets `page: 1` (`:576-586`); a second effect issues `router.get(index({ tenant }), { search, per_page, sort, direction }, { preserveState, preserveScroll, replace: true, onStart/onFinish → navigating })` (`:589-611`), skipped on first render via `skipFirstQuery` ref (`:573`, `:590-593`).
- **Filter UI that exists today:** search `Input` (`:713-722`) with clear button (`:723-734`), a per-page `Select` (10/25/50/100, `PER_PAGE_OPTIONS` `:131`) (`:743-766`), and a column-visibility `DropdownMenu` (`:351-388`). **No organization/account/status filter controls exist** — the sticky action bar (`:704-768`) has only search + kolom + per-page.
- **Sorting:** clickable column headers call `handleSort` → `setAppliedFilters` (`:649-659`); only NIK/Nama/Bergabung are sortable (`:785`, `:803`, `:879`).
- **Pagination:** prev/next buttons use `pagination.prev_page_url` / `next_page_url` from the paginator through `router.get(...)` with `preserveScroll/preserveState` (`:1003-1044`). `hasActiveFilters` is currently `appliedFilters.search !== ""` only (`:623`); `clearFilters` resets to the four defaults (`:613-621`).
- Empty state already distinguishes "filtered" vs "unfiltered" (`:907-977`), so adding filters slots into existing copy logic.

### 3. The 100 cap — account-link candidates

The cap is **not** in the index. It lives in `EmployeeController::show`:

- `app/Http/Controllers/MasterData/EmployeeController.php:97-114` builds `$availableAccounts`, gated on `akun.read` + `akun.update` and the employee having no akun (`:99`), filtered to same `tenant_id`, `whereNull('employee_id')`, not super admin (`:100-104`), `orderBy('name')` → **`->limit(100)` (`:106`)** → `get(['id','name','email'])` (`:107`). **This is the "kandidat saat ini dibatasi 100".**
- The candidates are passed as a static page prop `available_accounts` on `employees/show` (`:116-121`); there is **no endpoint that lists users for linking** — grep of `routes/*.php` finds no user-listing route; the only akun routes are `POST/DELETE employees/{employee}/akun` and `POST .../akun/roles` (`routes/web.php:151-156`), and the write-side re-validates the same constraints inside `linkAkun` (`EmployeeController.php:273-279`).
- Frontend consumption: `TautkanAkunAction` in `resources/js/pages/employees/show.tsx:229-346` renders a plain Radix `Select` over `availableAccounts` (`:290-309`) with **no search input and no pagination**; empty-state copy at `:282-288`; submit disabled when list empty (`:327-331`). Prop wired at `show.tsx:87`, `:437`, `:625`.
- Distinct "100"s to avoid confusing: `EmployeeIndexRequest` `per_page in:10,25,50,100` (`EmployeeIndexRequest.php:29`) and `RoleController`/`PermissionController` `min($request->integer('per_page', 10), 100)` (`RoleController.php:45`, `Settings/PermissionController.php:29`) are page-size ceilings, not candidate caps.

### 4. Filter dimensions available in data

From `database/migrations/2026_10_02_000000_create_organization_tables.php`:

- `employees`: `department_id` (`:66`), `position_id` (`:67`), `division_id` (`:68`), indexed `department_id` (`:72`), `(tenant_id, nik_employee)` index (`:73`).
- `banned_at` + `optigate_user_id` added later with unique `(tenant_id, optigate_user_id)` (`database/migrations/2026_10_11_000001_add_ban_and_sync_identity_to_employees_table.php:13-19`).
- **`has_account`** is not a column — it is `users.employee_id` (`database/migrations/0001_01_01_000200_create_users_table.php:24`), FK `organization_tables.php:78`, unique since `2026_10_20_155833_add_unique_employee_id_to_users_table.php:38`. Filtering = `whereHas('user')` / `whereDoesntHave('user')` (`Employee::user()` is `hasOne`, `app/Models/Employee.php:102-104`).
- Relations for the table rows are eager-loaded already (`EmployeeQuery.php:27`) — **no N+1 today**. But **no relations/options are loaded for filter dropdowns** (§1.1); the precedent for sending dropdown options is `WorkOrderController::create`, which passes `'departments' => Department::query()->orderBy('nama_department')->get(['id','nama_department'])` (`app/Http/Controllers/WorkOrderController.php:55`).
- Sibling report's data snapshot (`employee-page-improvements.md:59-74`): ~125 employees, 11 departments, 11 divisions, 0 positions in the local DB — small volumes; LIKE + relation `whereIn` is adequate.

### 5. Test coverage today

- `tests/Feature/EmployeeIndexTest.php` (438 lines, read in full): tenant scoping `:38-64`, search by NIK/name/email/dept-code/position `:66-223`, `per_page` pagination `:225-266`, sort `:268-324`, relation inclusion `:326-358`, URL state preservation (`?search=...&page=2` → `filters.*` + `current_page`) `:360-384`, authorization matrix `:386-438`. **No filter-param tests (none exist to test).**
- `tests/Feature/EmployeeBanTest.php:99-141`: index `is_banned` false/true + `banned_at` present — proves the select fix landed.
- `tests/Feature/EmployeeAccountLinkTest.php:178-221`: `available_accounts` excludes linked/other-cabang/superadmin accounts (`->has('available_accounts', 1)` `:219`). **No test exercises the 100 limit or search/pagination of candidates.**
- `tests/Feature/EmployeeShowTest.php:28-137`: show payload minimization / akun visibility / role gating.
- Filter-test precedents on sibling pages: `tests/Feature/Rbac/PermissionControllerTest.php:48-63` (`?search=` and `?group=` assertions), `tests/Feature/Rbac/RoleControllerTest.php:50` (`?search=`).

---

## House precedent for server-side search + pagination

### Backend

| Page | Backend pattern |
|---|---|
| Roles | `RoleController::index` (`app/Http/Controllers/RoleController.php:26-56`): `$request->string('search')->trim()` `:30`, `->when($search !== '', ...->where('name','like','%'.addcslashes($search,'%_\\').'%'))` `:43`, `->paginate(min($request->integer('per_page',10),100))->withQueryString()` `:45-46`, echo `filters => [search, sort]` `:50-53`. |
| Permissions | `Settings\PermissionController::index` (`app/Http/Controllers/Settings/PermissionController.php:25-64`): search `:31` + **category filter** `group` `:32`, both applied `->when(...)` `:41-42`, paginator `:44-45`, `filters => [search, group, sort]` `:57-61`, plus a **filter-options prop** `groups` (name+count list) `:47-53`, `:62`. |
| Tenants (status tri-state) | `TenantController::index` (`app/Http/Controllers/TenantController.php:36-59`): `$status = $request->query('status','all')` `:39`, `->when(in_array($status,['active','inactive'],true), fn ($q) => $q->where('is_active', $status==='active'))` `:48-49`, `filters => [search, status]` `:57-59`. |

### Frontend

- **Roles** (`resources/js/pages/roles/index.tsx`): import `import { destroy, index, store, update } from '@/routes/roles';` `:29`; 300 ms debounce effect `:1166-1174`; URL-sync effect `router.get(index(), { search: applied }, { preserveState: true, replace: true, onStart/onFinish → navigating })` `:1176-1190`; **re-align toolbar state when server replaces props** on `roles.current_page` `:1194-1198`; paginator prev/next via `<Link href={roles.prev_page_url} preserveScroll>` `:1348-1398`.
- **Permissions** (`resources/js/pages/settings/permissions/index.tsx`): import `from '@/routes/permissions'` `:13`; debounce `:468-477`; combined URL sync `{ search: appliedSearch, group: appliedGroup }` with `skipFirstQuery` ref `:459`, `:479-496`; state re-align on `permissions.current_page` `:498-506`; **group filter `Select`** with an `all` sentinel mapped to `''` `:562-585`; `hasFilter` spans both `:508`.
- **Tenants** (`resources/js/pages/tenants/index.tsx`): debounce `:183-193`, URL sync `{ search, status }` `:195-211` (same `skipFirstQuery` + `replace: true` shape as employees), state re-align `:213-216`, and a **status segmented control** (`role="group" aria-label="Filter status"`, `aria-pressed` buttons) `:279-309` — the precedent for a tri-state status filter UI.
- **Employees index already implements the same trio** (debounce `:576-586`, URL sync `:589-611`, `replace: true` `:606`) — so URL query-param sync **is** an established repo pattern (4 pages: employees `:606`, tenants `:206`, permissions `:491`, roles `:1185` all use `replace: true`).
- Wayfinder query-param convention: `router.get(routeFn(args), { ...query }, options)` — route function supplies path params, plain object supplies query (`index.tsx:595-602`; `roles/index.tsx:1180-1188`; `permissions/index.tsx:486-495`). No page builds query strings manually.

---

## Proposed changes (no code)

### Backend

1. **`EmployeeIndexRequest`** (`app/Http/Requests/EmployeeIndexRequest.php:26-32`) — add:
   - `department_id` / `division_id` / `position_id`: `nullable, string` (UUIDs) or `array` + `.*: string` if multi-select is chosen (see Decision D1); validate `exists:departments,id` scoped to `tenant()` like `WorkOrderController::store` does (`app/Http/Controllers/WorkOrderController.php:66-69`).
   - `has_account`: `nullable, in:0,1` (or `boolean` with `1`/`0` serialization per `wayfinder/index.ts:31-41`).
   - `status`: `nullable, in:all,active,banned` mirroring tenants' tri-state (`TenantController.php:39,48-49`).
   - Accessors with defaults (`search()` pattern, `:38-41`).
2. **`EmployeeQuery::build`** (`app/Queries/EmployeeQuery.php:21-63`) — accept the new params (signature grows from 3 to ~6 args, or take an array/DTO — see Decision D2) and append `->when(...)` clauses: `whereIn('department_id', ...)` (uses index `organization_tables.php:72`), `whereNull/whereNotNull('banned_at')`, `whereHas('user')`/`whereDoesntHave('user')`. **Also add `orWhereHas('user', ...)` on `name`/`email` to the search block (`:45-55`) if "pencarian akun" is read as index-side akun search** (see Decision D3), and consider `addcslashes($search, '%_\\')` to match `RoleController.php:43`.
3. **`EmployeeController::index`** (`:32-52`) — pass filters through; echo them in `filters` so the frontend can rehydrate; add filter-option props (departments/divisions/positions as `{id, label}` lists, precedent `WorkOrderController::55`) — with ~11 departments/divisions and few positions, three small `get()` calls are cheap; optional `withCount('employees')` per option to show counts like `PermissionController.php:47-53`.
4. **Account candidates (fixing the 100 cap)** — move candidate listing out of `show()`:
   - New route in the tenant group (`routes/web.php:146-159` block), e.g. `GET {tenant}/employees/{employee}/akun/available` → returns `{ data, current_page, last_page, total }` with `search` + `page` params, reusing the exact constraint block from `EmployeeController::show` (`:100-105`) extracted into a shared private method also called by `linkAkun` (`:273-279`) so the picker and the write path can never diverge. Gate with the same `akun.read` + `akun.update` + no-existing-akun conditions (`:99`).
   - Drop `available_accounts` from the `show` props (`:120`) or keep a small first page as a fallback; Wayfinder regenerates automatically (`npm run build` runs `wayfinder:generate`, `package.json:6`).
   - The picker UI then becomes a debounced search input + list (see Frontend).

### Frontend

5. **Filter bar on `resources/js/pages/employees/index.tsx`** — extend the existing sticky bar (`:704-768`), reusing components already imported on this page: `Select`/`SelectTrigger`/`SelectContent` (`:27-33`, already used for per-page `:743-766`) for department/division/position; a tenants-style segmented control (`tenants/index.tsx:279-309`, no new dependency) or a `Select` with an `all` sentinel (`permissions/index.tsx:562-585`) for status and has-account. All state flows into the existing `appliedFilters` → URL-sync effect (`:589-611`); **extend `Filters` type (`:114-119`), `clearFilters` (`:613-621`), and `hasActiveFilters` (`:623`)**, and send the new keys in the `router.get` payload (`:597-602`).
6. **Server-driven dropdown options** — add props (e.g. `filterOptions: { departments, divisions, positions }`) to `PageProps` (`:121-125`); this follows the `WorkOrderController::create` precedent rather than deriving options from the current page (which would miss off-page values).
7. **Account picker in `show.tsx`** — replace the static `Select` (`:290-309`) with a search-as-you-type control fetching the new endpoint (debounce pattern copied from `index.tsx:576-586` or `permissions/index.tsx:468-477`), rendering a paginated result list. No `cmdk`/combobox component exists in `package.json` (`@radix-ui/react-select` only, `package.json:24`) — either hand-roll a listbox over the existing primitives or add a combobox dependency (Decision D4).
8. **URL sync** — already the house pattern (4 pages); no new mechanism needed. Follow the `skipFirstQuery` + `replace: true` + re-align-on-`current_page` trio (`permissions/index.tsx:498-506` is the most complete example; employees already has `skipFirstQuery` `:573` but lacks the re-align effect — worth adding so back/forward restores control values).

### Fixing the 100 cap — the right structure

The cap exists because candidates are an unpaginated eager prop. The right structure is **server-driven search + pagination on demand**, i.e. Decision D4 option A: a dedicated read endpoint (or an Inertia partial reload of `employees/show`) queried when the dialog opens / the user types, with the constraint block shared with `linkAkun`. Raising `limit(100)` or client-side filtering the full list would keep two copies of the business rules and grow the show payload unboundedly.

---

## Decision options

| # | Question | Options | Recommendation | Effort |
|---|---|---|---|---|
| D1 | Org filters single- or multi-select? | (a) single-select `department_id[]`… (b) single scalar per dimension | **(b) scalar first.** With 11 departments/divisions (`employee-page-improvements.md:64-65`) single-select covers real use; multi-select needs `array` validation + `whereIn` + a multi-Select UI the repo has no precedent for (Radix Select is single, `package.json:24`). | S (b) / M-L (a) |
| D2 | How to pass new filters into `EmployeeQuery`? | (a) widen `build()` positional args (b) array/DTO arg | **(a)** for parity with the current 3-arg call (`EmployeeQuery.php:21-25`); revisit if params exceed ~6. | S |
| D3 | What does "pencarian akun server-side" mean? | (a) account **link-picker** search (matches "kandidat dibatasi 100") (b) index search extended over linked `users.name/email` (c) both | **(a) is mandatory** (it's what the 100-cap parenthetical points at, `EmployeeController.php:106`); (c) cheap to add — one `orWhereHas('user', ...)` in the existing search block (`EmployeeQuery.php:45-55`) — worth doing in the same slice. Needs user confirmation since TODO wording is genuinely ambiguous. | S (a) / S (b) |
| D4 | Picker implementation | (a) new JSON/search endpoint + hand-rolled listbox (b) Inertia partial reload of `employees/show` with `only: ['available_accounts']` (c) raise the cap + client filter | **(a)**: cleanest contract, testable without rendering, reuses constraint block; (b) works but couples picker paging to full-page props; **(c) rejected** — unbounded payload + duplicate rules. | M (a) / S-M (b) |
| D5 | Search engine: LIKE vs fulltext? | (a) keep `LIKE %term%` (b) MySQL FULLTEXT index | **(a).** ~125 rows (`employee-page-improvements.md:62`), no FULLTEXT index exists in any migration (grep clean); FULLTEXT adds migration + sync considerations for zero felt gain. Escape wildcards with `addcslashes` like `RoleController.php:43`. | S (a) / M-L (b) |
| D6 | Filter dropdown options: server props vs client-derived? | (a) server-driven props (b) derive from loaded rows | **(a)** — precedent `WorkOrderController::55`; (b) silently omits values not on the current page. | S |
| D7 | Status control UI | (a) tenants-style segmented control (b) `Select` with `all` sentinel | **(a)** for status/has-account (visual precedent `tenants/index.tsx:279-309`), **(b)** for the three org dimensions (selects scale better, precedent `permissions/index.tsx:562-585`). | S each |

---

## Test plan

1. **`tests/Feature/EmployeeIndexTest.php`** — extend, following the existing `assertInertia` style (`:58-63`):
   - filter by `department_id` / `division_id` / `position_id` (each returns only matching rows; non-matching excluded),
   - `has_account=1` / `=0` using a linked `users.employee_id` (fixture pattern at `:331-345`),
   - `status=banned` / `status=active` using `banned_at` (pattern from `EmployeeBanTest.php:125-141`),
   - **filters persist across pagination/sort** (mirror "preserves URL state on pagination" `:360-384` with the new params),
   - invalid filter value → 422/ignored per chosen validation strictness,
   - (if D3b) search by linked akun email.
   - Permission behavior already covered (`:404-419`); no changes needed there.
2. **`tests/Feature/EmployeeAccountLinkTest.php`** — add for D4: candidate endpoint returns only same-cabang/unlinked/non-superadmin rows (extend fixture at `:178-214`), search by name/email narrows results, `page` paginates beyond 100 (create 101+ users, assert page size + total), requires `akun.read`+`akun.update`, 404/403 when employee already has an akun. Existing test at `:218-221` must keep passing or be updated if `available_accounts` prop is removed.
3. **Optional frontend coverage** — repo has no JS test runner (per `TODO.md` note re: defer-JS-decisions issue); verification is `npm run types:check` (`tsc --noEmit`, passes today, exit 0) + `npm run build`.
4. Run narrow: `php artisan test --compact tests/Feature/EmployeeIndexTest.php tests/Feature/EmployeeAccountLinkTest.php`, then ask user to run the full suite; `vendor/bin/pint --dirty` after PHP edits.

---

## Sources

- `TODO.md:9` — the goal item.
- `app/Http/Controllers/MasterData/EmployeeController.php:28-53` (index), `:97-114` (candidate list + `limit(100)` at `:106`), `:116-121` (show props), `:261-314` (linkAkun constraint block `:273-279`).
- `app/Queries/EmployeeQuery.php:10-16` (sortable), `:27` (eager loads), `:28-41` (select incl. `banned_at`), `:43-56` (search), `:58-60` (sort).
- `app/Http/Requests/EmployeeIndexRequest.php:26-32` (rules), `:38-65` (accessors).
- `app/Http/Resources/EmployeeResource.php:18-51`.
- `resources/js/pages/employees/index.tsx:22` (Wayfinder import), `:85-131` (types), `:559-669` (state/debounce/URL-sync/sort), `:704-768` (filter bar), `:993-1046` (pagination), `:907-977` (empty states).
- `resources/js/pages/employees/show.tsx:77` (AvailableAccount), `:229-346` (TautkanAkunAction), `:87,437,625` (prop wiring).
- `resources/js/routes/employees/index.ts:8-44` (generated Wayfinder); `resources/js/wayfinder/index.ts:26-29,53-54,75-80`.
- House precedents: `app/Http/Controllers/RoleController.php:26-56`; `app/Http/Controllers/Settings/PermissionController.php:25-64`; `app/Http/Controllers/TenantController.php:36-59`; `app/Http/Controllers/WorkOrderController.php:55`.
- `resources/js/pages/roles/index.tsx:29,1166-1198,1348-1398`; `resources/js/pages/settings/permissions/index.tsx:13,454-512,562-585`; `resources/js/pages/tenants/index.tsx:175-223,279-309`.
- Migrations: `database/migrations/2026_10_02_000000_create_organization_tables.php:58-79`; `2026_10_11_000001_add_ban_and_sync_identity_to_employees_table.php:11-20`; `2026_10_20_155833_add_unique_employee_id_to_users_table.php:38`; `0001_01_01_000200_create_users_table.php:24`.
- `app/Models/Employee.php:62-65` (isBanned), `:70-89`, `:102-104` (user hasOne).
- Tests: `tests/Feature/EmployeeIndexTest.php` (full), `tests/Feature/EmployeeBanTest.php:99-141`, `tests/Feature/EmployeeAccountLinkTest.php:165-221`, `tests/Feature/EmployeeShowTest.php:28-137`, `tests/Feature/Rbac/PermissionControllerTest.php:48-63`, `tests/Feature/Rbac/RoleControllerTest.php:50`.
- `routes/web.php:146-159` (employee routes, no user-listing route); `routes/settings.php:31-35`; `routes/hak-akses.php:10-16`.
- `package.json:6-11` (build/types scripts), `:13-29` (radix deps).
- Sibling: `.scratch/employee-page/research/employee-page-improvements.md` (data snapshot `:59-74`, gap matrix `:113-119`).
