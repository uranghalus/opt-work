# Daily Work FR-5.x Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modul Daily Work (FR-5.1–5.4): template rutin per karyawan (dikelola HOD) + tugas tambahan tertanggal + notifikasi, digabung jadi satu daftar harian dengan status/lokasi per item.

**Architecture:** Materialize-on-view — template recurring di-upsert idempoten menjadi baris `daily_works` saat daftar tanggal D dibuka; satu tabel menyimpan status/lokasi/riwayat. Izin `daily-work.create/read/update` SUDAH ada di `RoleAndPermissionSeeder` (cukup grant + honesty-test update).

**Tech Stack:** Laravel + Pest + Inertia/React + shadcn/ui (tanpa tabel UI) + Wayfinder + Reverb/WA (ADR-0009).

**Spec:** `.scratch/daily-work/design.md` (disetujui user 2026-10-23, termasuk amandemen: UI tanpa tabel, search+filter+paginasi wajib, komponen shadcn).

## Global Constraints

- Branch `features/department-syncronize`; commit konvensional per task (subject ≤ 50 char).
- Baseline suite: **582 test — 574 pass, 8 skip, 0 fail**; tiap task berakhir hijau + `vendor/bin/pint --dirty`.
- Timezone `Asia/Makassar` (WITA) — semua "hari ini" via `Carbon`/`CarbonImmutable` default; jangan hardcode tanggal.
- Penamaan Laravel (tanpa prefix `tb_`/`fld_`); semua model `BelongsToTenant` + `HasUuids`; FK `tenant_id` string → `tenants.id` cascadeOnDelete.
- "HOD" di sini = `hod_user_id` ATAU `manager_user_id` department (pola rantai eskalasi); `admin_tenant` + super_admin (bypass) boleh lintas department.
- Setelah gating: hapus `'daily-work.create/read/update' => 'gated when the module lands'` dari allowlist `tests/Feature/Rbac/PermissionConsistencyTest.php` di commit yang sama.
- Akhir: `php artisan app:grant-default-permissions`, `php artisan wayfinder:generate --with-form` (tanpa `--with-form` tsc meledak), `npx tsc --noEmit`, `npm run build`, suite penuh.
- Route baru di grup tenant `routes/web.php` (`middleware(['auth','tenant.access'])->prefix('{tenant}')`), gating via `Gate::authorize` di controller (pola `employees.akun.roles.sync`).

## Review Focus

1. **User tanpa link employee** membuka `/daily-work` → daftar kosong + flag `can_update=false`, BUKAN 500. (Pinned: Task 2 test `tanpa link employee aman`.)
2. **Dua request materialize bersamaan** untuk template+tanggal sama → unique `(template_id, work_date)` mencegah dobel; upsert tidak pernah menimpa `status`/`location`. (Pinned: Task 2 test `upsert tidak menimpa status`.)
3. **Query param `date` invalid** (`abc`, `2026-13-99`) → 422 validasi, bukan crash; tanggal valid mana pun render. (Pinned: Task 2 test `date invalid 422`.)
4. **Template dengan `weekdays` kosong** → validasi tolak (`min:1`), tidak pernah muncul diam-diam. (Pinned: Task 3 test `weekdays kosong ditolak`.)
5. **Karyawan mengubah item milik karyawan lain** (tenant sama) → 403. (Pinned: Task 2 test `item milik orang lain 403`.)

---

### Task 1: Foundation — migrasi + model + factory

**Files:**
- Create: `database/migrations/2026_10_23_100001_create_daily_work_tables.php`
- Create: `app/Models/DailyWorkTemplate.php`, `app/Models/DailyWork.php`
- Create: `database/factories/DailyWorkTemplateFactory.php`, `database/factories/DailyWorkFactory.php`
- Test: `tests/Feature/DailyWorkFoundationTest.php` (via `php artisan make:test --pest DailyWorkFoundationTest`)

**Interfaces (Produces):**
- `DailyWorkTemplate` — fillable: `employee_id, title, description, weekdays, is_active, created_by`; casts: `weekdays: array`, `is_active: bool`; relasi `employee(): BelongsTo`, `creator(): BelongsTo(User)`.
- `DailyWork` — fillable: `employee_id, work_date, title, description, location, status, done_at, source, template_id, created_by`; casts: `work_date: date`, `done_at: datetime`, `status: string`; relasi `employee()`, `template(): BelongsTo (nullOnDelete)`, `creator()`.
- Factory states: `DailyWorkFactory::forTemplate(DailyWorkTemplate $t, string $date)` → `source=template, template_id, work_date, title/description dari template`.

- [ ] **Step 1: Tulis test gagal** — 3 test: (a) factory buat template + daily work dengan tenant & relasi terisi; (b) cast `weekdays` array + `work_date` Carbon; (c) `DailyWorkFactory::forTemplate()` mewarisi title/description + `source=template`.
- [ ] **Step 2: Jalankan** `php artisan test --filter=DailyWorkFoundationTest` → Expected: FAIL (class tidak ada).
- [ ] **Step 3: Buat migrasi** mengikuti pola `2026_10_23_000002_create_work_data_tables.php`:
  - `daily_work_templates`: `uuid id primary`, `tenant_id`, `foreignUuid employee_id → employees nullOnDelete`, `string title`, `text description nullable`, `json weekdays`, `bool is_active default true`, `foreignId created_by nullable → users nullOnDelete`, timestamps; index `(tenant_id, employee_id)`.
  - `daily_works`: `uuid id primary`, `tenant_id`, `foreignUuid employee_id → employees nullOnDelete`, `date work_date`, `string title`, `text description nullable`, `string location nullable`, `string status default 'pending'` (pending|in_progress|done), `timestamp done_at nullable`, `string source default 'adhoc'` (template|adhoc), `foreignUuid template_id nullable → daily_work_templates nullOnDelete`, `foreignId created_by nullable → users nullOnDelete`, timestamps; index `(tenant_id, employee_id, work_date)`; **unique `(template_id, work_date)`** (NULL template_id = adhoc, MySQL mengizinkan banyak NULL).
- [ ] **Step 4: Buat model + factory** pola `WorkPlanning` (PHPDoc property + `#[Fillable]`).
- [ ] **Step 5: Jalankan** migrasi + test → Expected: PASS. `vendor/bin/pint --dirty`.
- [ ] **Step 6: Commit** `feat(daily-work): foundation migrasi + model + factory`

### Task 2: Materialize service + daftar harian karyawan + update status

**Files:**
- Create: `app/Services/DailyWorkService.php`
- Create: `app/Http/Controllers/DailyWorkController.php`
- Modify: `routes/web.php` (grup tenant): `GET daily-work` → `daily-work.index`, `PATCH daily-work/{dailyWork}` → `daily-work.update`
- Modify: `tests/Feature/Rbac/PermissionConsistencyTest.php:139-141` — hapus `daily-work.read` + `daily-work.update` dari allowlist (biarkan `daily-work.create` — Task 3)
- Test: `tests/Feature/DailyWorkMaterializeTest.php`, `tests/Feature/DailyWorkUpdateTest.php`

**Interfaces:**
- Consumes: Task 1 model/factory.
- Produces: `DailyWorkService::materializeFor(Employee $employee, CarbonImmutable $date): void` (public, dipakai Task 3 team view); `DailyWorkController::index()` payload items: `{data: [{id, work_date, title, description, location, status, done_at, source, template_id}], meta paginasi}` + flags `can_update`.

- [ ] **Step 1: Tulis test gagal (Materialize)** — Sen–Jum template muncul Senin & tidak Sabtu; `is_active=false` berhenti; **upsert tidak menimpa status** (update item jadi `done` lalu materialize ulang → tetap `done`); hapus template → instance riwayat tetap; user **tanpa link employee** → index 200 + items kosong; **date invalid → 422**; lintas-tenant tidak bocor (WO tenant lain tidak muncul).
- [ ] **Step 2: Jalankan** → Expected: FAIL (class/route tidak ada).
- [ ] **Step 3: Implementasi service** — `DB::transaction`: ambil template aktif employee, filter `in_array($date->dayOfWeek, $template->weekdays)` (`dayOfWeek`: 0=Minggu..6=Sabtu), `firstOrCreate(['template_id', 'work_date' => $date->toDateString()], [title, description, employee_id, source='template'])` — kolom progres TIDAK ada di create defaults.
- [ ] **Step 4: Implementasi controller** — `index(Request)`: validasi `date` (nullable, `date` format Y-m-d; default `CarbonImmutable::today()`), `search` (string nullable), `status` (in pending|in_progress|done nullable), `page`; resolve `Employee::find($user->employee_id)` (tenant-scoped); materialize bila employee ada; query `DailyWork::where employee_id + work_date` + filter search (whereTitle like) + status; paginate 20 lewat pola `through()` WorkOrderController; `update(DailyWork $dailyWork)`: `Gate::authorize('daily-work.update')` + assert `employee_id === auth()->user()->employee_id` (else 403), validasi `status` + `location` (nullable string max 255), `done_at = now()` saat `done` else null.
- [ ] **Step 5: Jalankan kedua test file** → Expected: PASS. `vendor/bin/pint --dirty`.
- [ ] **Step 6: Commit** `feat(daily-work): materialize + daftar harian + update status`

### Task 3: Template CRUD (HOD) + tugas tambahan + notifikasi + team view

**Files:**
- Modify: `app/Http/Controllers/DailyWorkController.php` (tambah method)
- Modify: `routes/web.php`: `GET|POST daily-work/templates` (+`PATCH|DELETE .../{template}`), `POST daily-work/adhoc`, `GET daily-work/team`
- Create: `app/Notifications/DailyWorkAssigned.php` (pola `WorkOrderAssigned`: database+broadcast+WahaWhatsAppChannel, `toWhatsApp` Bahasa Indonesia)
- Modify: `tests/Feature/Rbac/PermissionConsistencyTest.php` — hapus `daily-work.create` dari allowlist
- Test: `tests/Feature/DailyWorkTemplateTest.php`, `tests/Feature/DailyWorkAdhocTest.php`

**Interfaces:**
- Consumes: `DailyWorkService::materializeFor` (Task 2), model Task 1.
- Produces: notif `new DailyWorkAssigned(DailyWork $dailyWork)` (Task tidak lain mengonsumsi; UI link `/daily-work?date=`).

- [ ] **Step 1: Tulis test gagal (Template)** — HOD buat/edit template karyawan di department-nya (200 + row); **weekdays kosong ditolak** (422); HOD department lain → 403; `DELETE` → `is_active=false` (idempoten, row tetap); list templates per karyawan + search + paginasi.
- [ ] **Step 2: Tulis test gagal (Adhoc)** — HOD kirim adhoc (karyawan dept-nya, tanggal, judul, lokasi?) → row `source=adhoc` + `created_by`; **notifikasi** masuk ke `Notification` user ter-link + outbox `wa_messages` muncul (pola test `WahaGatewayTest`); karyawan **non-bawahan → 403**; team view menampilkan item karyawan + filter status/search + paginasi.
- [ ] **Step 3: Jalankan** → Expected: FAIL.
- [ ] **Step 4: Implementasi** — helper private `authorizeManage(Employee $e): void` (user = hod/manager department e, ATAU role `admin_tenant`, super_admin bypass); template store/update validasi `title required`, `weekdays required array min:1 max:7 int 0–6 distinct`, `description/location nullable`; destroy → `is_active=false`; adhoc store validasi `employee_id + work_date + title`, buat row + `$linkedUser?->notify(new DailyWorkAssigned(...))` (tanpa link → log warning, ikuti preseden `WorkOrderAssigned` meta); team index = materialize + query milik karyawan (employee wajib di department user).
- [ ] **Step 5: Jalankan** → Expected: PASS. `vendor/bin/pint --dirty`.
- [ ] **Step 6: Commit** `feat(daily-work): template HOD + adhoc + notifikasi + team`

### Task 4: Frontend karyawan — halaman `/daily-work` (card list + search/filter/paginasi)

**Files:**
- Create: `resources/js/pages/daily-work/index.tsx`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (flag `can['daily-work.read'/'update'/'create']` pola existing), `resources/js/types/auth.ts`
- Modify: nav sidebar (item "Daily Work", gated `daily-work.read`, pola item "Work Data")
- Test: backend sudah tercakup; verifikasi = tsc + build + suite

**Interfaces:** Consumes: payload `daily-work.index`/`update` (Task 2), route Wayfinder hasil Task 5 regen — **Wayfinder dijalankan di Task 4 sebelum tsc** (`php artisan wayfinder:generate --with-form`).

- [ ] **Step 1: Regenerate Wayfinder** → `php artisan wayfinder:generate --with-form`.
- [ ] **Step 2: Buat halaman** — card list (BUKAN tabel, komponen shadcn: `Card`, `Badge`, `Input`, `ToggleGroup` filter status, `EmptyState`, `Skeleton`): header tanggal + navigasi prev/next hari + tombol "Hari ini"; **filter bar**: search judul (debounce) + segmented status; item card: status action inline (3 tombol status) + input lokasi (save on blur/enter via `router.patch`); paginasi server-side (prev/next + nomor, pola `departments/index.tsx`); tanpa `daily-work.read` → 403 surface existing (render hook).
- [ ] **Step 3: Nav + can flags** — tambah flag + item sidebar gated.
- [ ] **Step 4: Verifikasi** — `npx tsc --noEmit` exit 0; `npm run build` hijau; `php artisan test --compact` tetap hijau.
- [ ] **Step 5: Commit** `feat(daily-work): halaman daftar harian karyawan`

### Task 5: Frontend HOD — kelola template + tugas tambahan (card grid + search/filter/paginasi)

**Files:**
- Create: `resources/js/pages/daily-work/templates.tsx`
- Modify: nav sidebar (item "Template Daily Work", gated `daily-work.create`, terlihat HOD/admin)
- Test: backend tercakup; verifikasi = tsc + build

**Interfaces:** Consumes: payload Task 3; komponen existing `Dialog`, `Select`, `Textarea`, `ToggleGroup` (weekday picker), ConfirmDialog (`components/confirm-dialog.tsx`), pola async combobox employee (`employees/akun/search` pattern).

- [ ] **Step 1: Buat halaman** — combobox karyawan (dept user); **card grid** template (search judul + filter aktif/nonaktif + paginasi); tambah/edit via `Dialog` (title, description, weekday ToggleGroup, is_active); nonaktifkan via ConfirmDialog; section **Tugas Tambahan**: dialog (karyawan, tanggal, judul, lokasi) + daftar adhoc (filter tanggal/status + paginasi).
- [ ] **Step 2: Verifikasi** — `npx tsc --noEmit` exit 0; `npm run build` hijau.
- [ ] **Step 3: Commit** `feat(daily-work): halaman template + tugas tambahan HOD`

### Task 6: Wrap-up — grant izin, suite penuh, dokumentasi

**Files:**
- Modify: `TODO.md` (section baru Daily Work → DONE), issue `.scratch/wms-mvp/issues/07-daily-work-templates.md` → done
- Vault (di luar commit): Sessions/2026-10/23/HHmm-daily-work-fr5.md + Index.md + Decisions bila ada ruling

- [ ] **Step 1:** `php artisan app:grant-default-permissions` → Expected: izin daily-work melekat (additive-only, idempoten); `php artisan migrate` di dev bila belum.
- [ ] **Step 2:** Suite penuh `php artisan test --compact` → Expected: hijau, 0 fail (baseline 582 + test baru); `vendor/bin/pint --dirty` bersih.
- [ ] **Step 3:** Tick issue 07 + TODO.md; commit `docs(daily-work): TODO + issue done`; push branch.
- [ ] **Step 4:** Catatan vault + sinkron Index.
