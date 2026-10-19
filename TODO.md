# TODO - Spatie Multitenancy v4 Migration Progress

## Unit Bisnis page revamp + URL move (2026-10-19) — DONE

- [x] **Move `/settings/tenants` → `/tenants`:** routes/web.php (prefix `tenants`, names
      unchanged), controller moved `app/Http/Controllers/TenantController.php` (namespace
      `App\Http\Controllers`), Inertia components moved `resources/js/pages/tenants/`,
      tests moved `tests/Feature/TenantManagementTest.php` (URLs updated), Wayfinder
      regenerated. Sidebar/settings-nav links auto-updated via route functions.
      Verifikasi: `php artisan route:list --path=tenants`, tenant tests 7/7, full suite
      350 (342 passed, 8 skipped), pint + tsc + `npm run build` hijau.
- [x] **White/light redesign Unit Bisnis (index + show):** rebuilt both pages on the
      light Stitch tokens (bg-card, brand tints, ink scale) — no slate-950 dark theme.
      Index: filter bar (debounced search + status segmented control), card grid,
      loading overlay, empty states, pagination. Show: identity card, detail rows,
      usage bars, Optigate info note. Both wrapped by `app-sidebar-layout` via
      `.layout` metadata (breadcrumbs, title, description, actions).
      Verifikasi: impeccable detect clean, tsc + build hijau.
- [ ] Visual check in browser by user (light + dark mode toggle) — pages are token-based
      so dark mode stays readable by design.

## Sprint A — commit slicing + review fixes (2026-10-19) — DONE, commits `47014af`…`8b80440`

- [x] **Sync endpoint tests** (`tests/Feature/TenantManagementTest.php`): super admin
      `POST /tenants/sync` → success flash "Sinkronisasi selesai", branch admin → 403,
      500 portal → error flash + data untouched (suite 10/10).
- [x] **Review fixes**: `optigate_company_id: string | null` (model cast string),
      show.tsx link ke work-orders via Wayfinder `workOrderIndex({ tenant: code })`.
- [x] **Commit slicing** (sebagian besar sudah di-commit sesi paralel: `3d8c9c1`
      divisions read-only, `abed8f9`, `402bbc4`, `5c33d0d`; sisa):
      `47014af` tenants move+redesign, `22f743e` orphaned division create/edit pages,
      `bb2cf6a` deps (log-viewer, shadcn, public/vendor assets), `8b80440` stale skill file.
      `pnpm-lock.yaml` dihapus (project pakai npm). Working tree bersih.
- [x] Verifikasi akhir: full suite 369 (361 passed, 8 skipped, 0 failed), pint, tsc,
      `npm run build` hijau, push ke origin/main.



## Department/HOD sync relations (2026-10-17) — DONE, commit `b0be63f`

- [x] **Relasi department ↔ karyawan ↔ HOD lewat sync Optigate:** `SyncDepartments` fetch
      `with=hod` → portal hod UUID dipetakan ke `hod_user_id` lokal via jembatan
      `employees.optigate_user_id` → `users.employee_id` (peta dibangun sekali per tenant;
      hanya employee dengan optigate_user_id non-null + akun tertaut; HOD portal yang belum
      ter-sync dibiarkan null, HOD lama dikosongkan bila portal tak lagi mencantumkan).
      `SyncEmployees` kini mengisi `employees.department_id` dari `department_id` portal via
      `optigate_department_id` (null-safe; dept tak dikenal dibiarkan null). Migrasi
      `optigate_department_id` + unique(tenant_id, optigate_department_id) + tombol
      "Sinkronkan" + flash alert di halaman departments. Verifikasi: sync tests 16/16,
      full suite 335 (327 passed, 8 skipped, 0 failed), pint + `npm run build` + tsc hijau.
- [ ] Follow-up (opsional): jalankan `app:sync-departments` lalu `app:sync-employees` di dev
      DB untuk mengisi relasi live; map HOD portal belum tertaut sampai karyawan+akun ikut
      disync (urutan: departments → employees).

## Guest role + akun platform (2026-10-17) — spec `.scratch/guest-role/`, commits `7e5fe00` + `0a34c48`

- [x] Root cause akun superadmin: role SUDAH ada; yang hilang link `users.employee_id` (0 dari 127
      employee tertaut) → kartu "Peran Akun" menampilkan "Belum ada akun yang terhubung"
- [x] `guest` = system role ke-10, nol permission (seeder idempoten; CONTEXT.md + ADR-0006)
- [x] Auto-assign `guest` di SSO (`LoginProvisioningService`, sebelum Auth::login, dalam transaksi,
      idempoten; guard state-based fail-closed: nol role + nol link)
- [x] Akun platform self-heal saat login: role `super_admin` + flag `is_super_admin` + tautan
      karyawan by email (config `auth.super_admin_email`, tanpa email literal; pola Spatie v8)
- [x] Perintah one-off `app:grant-super-admin-role` (idempoten, additive-only, `--dry-run`)
- [x] Test: GuestProvisioningTest 7 + SuperadminAccountTest 5 + SyncAkunRolesTest evict-guest;
      suite 328/320/0 (WIP SyncEmployees sesi paralel dikecualikan); pint + tsc + vp check hijau
- [ ] **OPEN (menunggu keputusan user):** guest tetap bisa membuka halaman tenant yang belum di-gate
      (daftar Karyawan, WO index miliknya, dashboard) — hanya hak-akses/akun/review/assign yang
      di-gate hari ini. Guest lebih dulu DENIED di login (Task 2), jadi secara NET akses guest
      bertambah. Opsi: (a) terima + alasankan, dengan issue gating master data terpisah;
      (b) guest tidak dapat tenant sama sekali → mendarat di halaman "menunggu provisioning"
      (permukaan baru). Perintah tulis `app:grant-super-admin-role` juga menunggu persetujuan —
      DB dev sudah compliant (dry-run all-green).
- [ ] Nanti: gate `work-order.create/read` + master data (employee/department/division/position)
      agar "guest tidak bisa sentuh tenant data" terpenuhi harfiah (allowlist honesty test)

## Next session — 2026-10-14 (RBAC v2: hak akses slices 6–8 frontend)

### Done today

- [x] **Ticket 03 (role-assignment) — hide Peran/Izin actions without rights:** 7 new shared `can` flags (`role.create/update/delete`, `permission.read/create/update/delete`) join `role.read` in `HandleInertiaRequests`; `Auth['can']` type extended. Peran page hides create/rename/delete/sync without the matching flag and renders banks read-only without `role.update`; Izin page hides batch-create/rename/delete the same way. Server still authorizes; UI is only a mirror. `GateTest` +3 (full set, read-only viewer, super admin zero-perm); `Feature/Rbac` 58 passed; `types:check`, `vp check`, `npm run build` green. Files: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/auth.ts`, `resources/js/pages/roles/index.tsx`, `resources/js/pages/settings/permissions/index.tsx`, `tests/Feature/Rbac/GateTest.php`.
- [x] **Role assignment ticket 01 (ADR-0005):** scope dippersempit via grill — assignment pada akun lewat halaman karyawan. Katalog `akun.*` + release grant ke admin_tenant; payload `akun` di `employees.show` ber-gate `akun.read` (resolusi manual mengikuti pola ban/WorkOrderController); section "Peran Akun" read-only. Ticket 02 (sync guarded) & 03 (can flags) siap di `.scratch/role-assignment/issues/`. Verifikasi: 277 tests (269 passed, 8 skipped), pint bersih, build exit 0. WIP sesi 11-10 (drop CRUD lokal karyawan) ikut di-commit.
- [x] **Per-release grant command `app:grant-default-permissions`:** menutup gap seeder (default hanya disync saat role pertama dibuat → izin katalog baru tidak pernah sampai ke role existing). Additive-only (tidak pernah mencabut, kustomisasi Hak Akses selamat), membuat izin katalog yang hilang (firstOrCreate), provisioning 9 role kanonik + invarian `is_system`, tidak pernah memberi apa-apa ke super_admin (bypass gate), `--dry-run`, idempoten. Seeder `catalogue()`/`roleDefaults()` dipromosikan public static sebagai sumber kebenaran bersama. Verifikasi: 18/18 test (7 baru) + full suite 272 tests (264 passed, 8 skipped, 0 failed), pint bersih, dry-run live di DB dev = 0 perubahan. Commit `5071fec`.
- [x] **Halaman Peran di-revamp total + keluar dari settings (Vault Console):** URL `/settings/roles` → `/hak-akses` (route names `roles.*` tetap), controller `Settings\RoleController` → `RoleController`, requests → `App\Http\Requests\Role\*`, routes dipindah ke `routes/hak-akses.php`, page `settings/roles/index.tsx` → `pages/roles/index.tsx` (AppLayout, bukan SettingsLayout), nav: grup baru "Hak Akses" (item "Peran") di antara Data Master dan Sistem. Desain baru: dark console stratum (panel tokens, gelap di kedua theme) — rail token peran (nama + mono id + mini coverage strip 12 segmen + count izin/pengguna, urutan hierarki PRD) + stage bank izin per resource (LED tri-state Penuh/Parsial/Kosong + count, master checkbox tri-state, verb chips ber-LED), super_admin = sealed plate tanpa switch, commit bar dirty-state + sweep animation, search debounce server-side + paginasi rail. Tabel dihapus. Verifikasi: `RoleControllerTest` 19/19, `npm run build` hijau, detector impeccable 0 temuan, screenshot Chrome headless (desktop/dirty/sealed/dark/mobile) via playwright-core --no-save (user sementara sudah dihapus). Bukti: `.impeccable/review/*.png`.
- [x] RBAC v2 slices 6–8 green: frontend Peran page (table + system badges + dropdown actions + create/rename/delete dialogs + grouped tri-state permission Sheet dengan super_admin locked notice + SpotlightCard empty state + Reveal entrance), Izin page (table + group filter Select + batch create dialog resource×CRUD + rename/delete + pagination), nav "Hak Akses" di grup Sistem gated `auth.can['role.read']` (Auth type + 6 nav call sites). Komponen reactbits di-vendor: `AnimatedContent.tsx` (GSAP) + `SpotlightCard.tsx` (zero-dep, diadaptasi rounded/padding) + wrapper `reveal.tsx` (respects prefers-reduced-motion). ← *desain Peran di atas sudah digantikan Vault Console; komponen vendor tetap dipakai di Izin.*
- [x] Full suite 265 tests: 257 passed, 8 skipped (2FA), 0 failed. `npm run build` hijau (wayfinder + tsc + vp build). Impeccable mechanical detector pada 6 file UI: 0 temuan.
- [x] Root-cause fix baseline merah: `types:check` gagal 44 error di HEAD (7 file pakai `usePage()` tanpa generic `InertiaConfig['sharedPageProps']`) → semua ditambahkan generic, tsc kini EXIT=0 di seluruh codebase.
- [x] Bugfix `settings/tenants/index.tsx`: 4 tombol paginasi mengandung artifact backtick-n (JSX text nyasar) yang membuat Radix Slot crash saat paginasi Unit Bisnis — diperbaiki.

### Work plan — next session (role assignment: tickets 02–03)

1. [x] **Ticket 02 — Assign & revoke roles on the linked Akun, guarded (M):** **Done 2026-10-16 (commit `74d2a6c`):** `POST {tenant}/employees/{employee}/akun/roles` (`employees.akun.roles.sync`), route ungated + `Gate::authorize('akun.update')` in controller; `SyncAkunRolesRequest` (single `roles` error key, super_admin tak dapat diberikan); sync replaces the set in `DB::transaction`; field_staff grant auto-adds karyawan, drop-karyawan-while-keeping-field_staff ditolak dengan pesan, revoke field_staff leaves karyawan; akun holding super_admin ditolak sebelum write; flags + department FKs byte-identical (asserted); fresh role effective next request tanpa re-login (tested). UI: dialog "Ubah Peran" self-contained di header kartu Peran Akun — hidden tanpa `akun.update`, absent tanpa akun, read-only seal untuk akun super_admin, destructive alert key `roles`, close on success. Verifikasi: `SyncAkunRolesTest` 12/12.
2. [x] **Ticket 03 — Hide Peran/Izin action buttons without rights (S):** shared `can` flags (`role.*`, `permission.*`) via Gate in `HandleInertiaRequests`, pages hide buttons. **Done 2026-10-16:** 8 flags shared (`role.read/create/update/delete`, `permission.read/create/update/delete`); `Auth['can']` type extended; Peran page hides create (role.create) / rename (role.update) / delete (role.delete) / permission-sync footer + banks read-only without role.update; Izin page hides batch-create (permission.create) / rename (permission.update) / delete (permission.delete); super_admin bypasses all. Verified: `GateTest` 3 new tests (full set, role.read-only, super admin zero-perm), `Feature/Rbac` 58 passed, pint clean, `types:check` + `vp check` green, `npm run build` exit 0. Decision: admin_tenant resolved to full set not asserted (follows `role.read` precedent).
3. [x] Ticket 02 selesai → hapus entri allowlist `akun.update` ("gated when role-sync lands") dari `PermissionConsistencyTest` di commit yang sama (honesty test) — **Done 2026-10-16, commit `74d2a6c`**; `akun.create`/`akun.delete` tetap di allowlist.
4. [x] Verifikasi visual sisa slice 8 `/settings/permissions` — **Done 2026-10-17, automated:** user temp (role `viewer` + `role.read`/`permission.read` langsung; role wajib karena LoginLanding menolak user tanpa role) + screenshot headless via `.impeccable/review/capture-permissions.mjs`. Bukti: `perm-light.png`, `perm-dark.png` (tombol aksi tersembunyi tanpa permission — ticket 03 tervalidasi visual), `perm-dialog-error.png` (dialog Tambah Izin, submit kosong → field merah + pesan error). User temp + script temp dihapus. Catatan minor: pesan validasi dialog masih English (pre-existing), teks "Head Office" di tenant switcher kontras rendah di dark (pre-existing).
5. [x] Vault sync — **Done 2026-10-17:** ADR-0005 → `Decisions/user-role-assignment-akun.md`, link dari `Index.md` (Last updated diubah). Catatan route `/hak-akses` ternyata sudah ada di `Decisions/peran-page-vault-console` — tidak diduplikasi.
6. [x] **WO slice 1 selesai 2026-10-17** (issue 03 wms-mvp = review → assign + deadline + histori) —
   tiket per seam di `.scratch/wo-slice-1/` (spec + 4 issues, semua done):
   - `6e47cf3` foundation: migration hod_action/scheduled_date/assigned_at/hod_notes,
     enum `HodApproved` ("Disetujui HOD"), `config/holidays.php` + `DeadlineCalculator`
     (urgent 3 / normal 6 hari kerja dari tanggal assign; fixed Y-m-d + recurring MM-DD),
     **fix cross-tenant read** di show()/attachment() (withoutGlobalScope dibuang; test
     regresi: WO tenant lain → 404, sebelumnya 200)
   - `ea6779d` review endpoint: POST .../review gated `work-order.review` (allowlist
     dikeluarkan), Accident dipaksa eksekusi langsung, 409 pasca-review-stage,
     `work_order_events` append-only + `WorkOrderReviewed` ke requester; **root-cause fix
     config/multitenancy.php queueable_to_job tertukar** (BroadcastEvent→'mailable', dst —
     setiap broadcast/mail queue crash di probe; kini event/mailable/notification)
   - `937a474` assign endpoint: POST .../assign gated `work-order.assign` (allowlist
     dikeluarkan), `assigned_employees` json + assignment_notes, assigned_at + deadline
     hanya sekali (revisi mengganti orang tanpa geser SLA), `WorkOrderAssigned` ke akun
     link saja (tanpa akun → meta event + log warning), 409 assign sebelum review
   - `553c775` panel frontend: flag `can` work-order.review/assign, panel Keputusan
     Review (Accident tanpa opsi jadwal), panel Assign (revisi mode), kartu Keputusan
     HOD, badge hod_approved; bukti headless `.impeccable/review/wo-panel-*.png`
   - Verifikasi: tsc + build hijau; suite 313/305/0 failed (6 failure = WIP merah sesi
     paralel SyncEmployees, bukan slice ini); pint bersih; slice-1 komit bersih dari WIP
     sesi paralel
   - Sisa allowlist honesty test: work-order.create/read/update/delete/submit/verify
     (gating menyusul saat permukaannya disentuh — issue 04+)

### Bonus 2026-10-16 (commit `74d2a6c`) — role-aware login + superadmin

- [x] **Role-aware login (LoginLanding):** satu rule landing untuk semua jalur login — no-role → session diakhiri + denied `no_role`; no-tenant → `no_tenant`; platform account → admin panel; home tenant → dashboard. Fortify `LoginResponse` binding menggantikan `config('fortify.home')` yang mati (menutup open question lama di Known-issues); SAML ACS menolak login tanpa peran sebelum tenant resolution. Authorization tetap per-request (pivot tidak di-cache — tested).
- [x] **Superadmin (Task 3):** seeder memberi akun `superadmin@appdutamall.com` peran `super_admin` + flag `is_super_admin`; bypass berjalan lewat `Gate::before` + role — **perbandingan email dihapus** dari `SamlTenantResolver::canAccessAnyTenant` (email hanya memilih akun yang di-seed). Flag-only super admin tetap lolos login tanpa peran Spatie (diurutkan sebelum cek no-role).
- [x] Verifikasi: full suite 301 tests (293 passed, 8 skipped 2FA, 0 failed); pint bersih; `npm run build` hijau. Commit + push `74d2a6c`.

### References for tomorrow (read before starting)

- `docs/adr/0005-user-role-assignment.md` — keputusan slice ini (JANGAN di-re-litigate)
- `docs/adr/0004-hak-akses-surface.md` — baseline RBAC: verb map, allowlist, guards, bypass super admin
- `.scratch/role-assignment/issues/02-assign-revoke-roles-guarded.md` + `03-hide-peran-izin-action-buttons.md` — acceptance criteria per ticket
- `.claude/plan/2026-10-14-work-plan.md` — plan hasil grill (settled decisions Q1–Q10)
- `CONTEXT.md` — glossary: Akun, Role Assignment, System Role, Hak Akses
- `tests/Feature/Rbac/RoleControllerTest.php` — pola test (acting user, tenant, gate) yang ditiru; `tests/Feature/Rbac/EmployeeAkunRolesTest.php` = ticket 01
- `Sessions/2026-10/16/0033-akun-role-read-surface.md` — log sesi pengerjaan ticket 01

### Deferred

- [ ] Forward-looking allowlist upkeep — remove work-order.review/assign/submit/verify entries as WO gating lands; a stale entry fails the honesty test (S)
- [ ] Start the core WO flow: creation, category decision (Normal / Urgent by Accident / Urgent Request by Owner), HOD review → assign — the hak-akses permissions are in place to gate it (L)
- [ ] Akun CRUD surface (link/unlink login) — owns `akun.create`/`akun.delete`, currently allowlisted "gated when the Akun surface lands"

Catatan: item "Resolve PRD Open Questions" dihapus — sudah SETTLED di AGENTS.md (Team Leader = role Spatie; deadline urgent 3/normal 6; extend 3 hari/3 approved; Direksi ditunda).

### Catatan keputusan hari ini

- Reactbits: CountUp/Counter dilewati (butuh dep baru `motion` — butuh approval); dipakai AnimatedContent (GSAP, sudah ada) + SpotlightCard (zero-dep). SplitText.jsx hasil vendor sesi sebelumnya tidak disentuh.
- Heading-band actions = komponen self-contained yang memiliki dialognya sendiri (mengikuti pola SyncButton/Employees) — tidak perlu jembatan state page.
- `form.post(store.url())` / `form.patch(update.url(args))`: useForm Inertia v3 hanya menerima string URL; `router.*` menerima RouteDefinition langsung.

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