# Work Order Review — Implementation Plan (D2 → D1 → D3 + Frontend)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup temuan review halaman work-order — kalibrasi SLA ke jam WITA, visibilitas pelaksana di daftar, gate detail WO, lalu redesain frontend halaman WO (tanpa tabel) sesuai DESIGN.md.

**Architecture:** D2 adalah penggeser domain jam global (config + migrasi data one-off) dikerjakan lebih dulu karena mengkalibrasi `DeadlineCalculator`/eskalasi. D1 = satu scope Eloquent bersama untuk index+timeline. D3 = Gate komposit di show/attachment. Frontend = payload index ditambah state deadline, lalu redesain halaman (skill impeccable, mode Operate, dunia DESIGN.md dipertahankan).

**Tech Stack:** Laravel 12 + Spatie v4 + Pest; Inertia v3 + React + Tailwind v4 (token DESIGN.md sudah ter-wiring sebagai utilitas `text-ink-muted`, `bg-brand-soft`, dst.).

**Spec:** `.scratch/wo-review-decisions/design.md` (disetujui user 2026-10-23).

## Global Constraints

- Setiap task PHP diakhiri `php artisan test --compact` (jumlah: hijau 0 gagal) + `vendor/bin/pint --dirty --format agent`.
- Setiap task TSX diakhiri `npx tsc --noEmit` + `npm run build`.
- Commit konvensional per task, subject ≤50 char. Jangan pernah commit `.env`.
- Copy UI Bahasa Indonesia. Warna hanya via token — tanpa raw hex di komponen.
- **D2 order-of-operations (hard):** migrasi dan penggantian config di-deploy bersamaan saat app idle (solo dev, tanpa scheduler-nya minta tunda); JANGAN ada penulisan data di antara keduanya.
- **D2 exclusion (hard):** kolom DATE-only (`deadline_date`, `scheduled_date`, `requested_schedule_date`, tanggal lain tipe DATE) TIDAK digeser; tabel runtime (sessions, cache, jobs, failed_jobs, nightwatch) TIDAK digeser.
- Jangan menyentuh: middleware tenant, scope `BelongsToTenant`, `config/holidays.php`, konfigurasi Reverb.

## Review Focus

1. **Migrasi bergeser dua kali** (config baru aktif dulu, migrasi jalan belakangan) → data −16 jam. Dilindungi oleh: satu commit berisi config + migrasi keduanya, dieksekusi `php artisan migrate` pada momen idle lalu verifikasi sampel jam.
2. **Kolom DATE ikut tergeser** → deadline bergeser 1 hari salah. Dilindungi oleh: pre-flight `information_schema` yang hanya menampung `COLUMN_TYPE REGEXP 'datetime|timestamp'`, hasilnya dijadikan daftar eksplisit di migrasi; test sqlite skip via guard driver.
3. **`whereJsonContains` beda perilaku sqlite/pdf MySQL** → test hijau dev-merah prod. Dilindungi oleh: test Feature visibilitas jalan di sqlite (phpunit default) + satu asersi MySQL-shaped di migrasi/log; pola json sudah dipakai repo (assigned_employees cast array).
4. **Gate show memandang WO tenant lain** → harusnya 404 (tenant scope) bukan 200 via klausul involvmen / 403. Dilindungi oleh test: WO tenant lain lewat URL → masih 404, bukan 403/200 (regresi yang sudah pernah diperbaiki — jangan bolong balik).
5. **Double-format tanggal frontend** (ISO +08:00 baru setelah D2) → tanggal tampil mundur 1 hari di show/timeline. Dilindungi oleh: satu asersi payload `deadline_label` + visual check screenshot.

---

### Task 1 (D2): Domain jam `Asia/Makassar` + migrasi geser data

**Files:**
- Modify: `config/app.php:68` ('timezone' => 'UTC' → 'Asia/Makassar')
- Create: `database/migrations/2026_10_23_000100_shift_bt_datetimes_to_wita.php` (via `php artisan make:migration shift_bt_datetimes_to_wita --table=work_orders`)
- Modify: `tests/Unit/DeadlineCalculatorTest.php` (tambah 2 test)
- Create: `tests/Unit/AppTimezoneTest.php`

**Interfaces:**
- Consumes: `DeadlineCalculator::calculate(WorkOrderCategory, CarbonInterface, ?array): Carbon`
- Produces: domain jam tunggal WITA untuk semua task berikutnya (tidak ada API baru).

- [ ] **Step 1 (pre-flight, dev DB):** Jalankan query enumerasi dan catat hasilnya untuk daftar migrasi:
```sql
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_TYPE REGEXP '^(datetime|timestamp)'
ORDER BY TABLE_NAME;
```
Eksklusi manual: semua kolom tabel `sessions`, `cache*`, `jobs`, `failed_jobs`, `job_batches`, nightwatch; kolom DATE-only. Hasil ekspektasi inti: `users(email_verified_at, created_at, updated_at, deleted_at)`, `tenants(created_at, updated_at, deactivated_at, ...)`, `employees/departments/divisions/positions(created_at, updated_at, deleted_at?)`, `roles/permissions(created_at, updated_at)`, `work_orders(created_at, updated_at, assigned_at, extended_at, escalation_h3_sent_at, escalation_h5_sent_at, escalation_h6_sent_at)` — **bukan** `deadline_date/scheduled_date/requested_schedule_date` (DATE), `work_order_events.created_at`, `notifications`/`app_notifications.created_at`, `audit_events + audit_logs(waktu), wa_messages(sent_at, created_at, last_error_at?), tenant_wa_sessions(last_connected_at, created_at, updated_at), extend_requests(created_at, decided_at?), work_data/work_data_pekerja(created_at, updated_at, finalized_at)`. Daftar final = hasil query, bukan ekspektasi.
- [ ] **Step 2: tulis test gagal** di `tests/Unit/AppTimezoneTest.php`:
```php
it('runs on the WITA operational clock', function () {
    expect(config('app.timezone'))->toBe('Asia/Makassar');
    expect(now()->format('P'))->toBe('+08:00');
});
```
dan di `tests/Unit/DeadlineCalculatorTest.php` (pin bug SLA):
```php
it('anchors the deadline on the local assign date, not UTC', function () {
    // Senin 07:00 WITA = Minggu 23:00 UTC — dulu menghasilkan Rabu (bug).
    $mondayMorning = Carbon::create(2026, 10, 26, 7, 0, 0, 'Asia/Makassar');
    expect(app(DeadlineCalculator::class)
        ->calculate(WorkOrderCategory::Urgent, $mondayMorning)->toDateString())
        ->toBe('2026-10-29'); // Kamis
});
```
- [ ] **Step 3:** `vendor/bin/pest tests/Unit/AppTimezoneTest.php tests/Unit/DeadlineCalculatorTest.php` → FAIL (timezone masih UTC).
- [ ] **Step 4:** Ubah `config/app.php` `'timezone' => 'Asia/Makassar'`. Buat migrasi via `php artisan make:migration shift_bt_datetimes_to_wita --no-interaction`; isi: guard `if (DB::connection()->getDriverName() === 'sqlite') return;` (test DB = timestamp fresh WITA domain, digeser tidak), lalu loop eksplisit atas daftar Step 1: `DB::statement("UPDATE {t} SET {c} = {c} - INTERVAL 8 HOUR")`. `down(): void {}` (one-off; tidak bisa dibalik aman).
- [ ] **Step 5:** Jalankan `php artisan migrate --force` di dev + verifikasi sampel (jam jejak: `work_orders.created_at` terlama tergeser −8 jam, `deadline_date` pintingi tanggal tidak bergeser).
- [ ] **Step 6:** `php artisan test --compact` full — perbaiki asersi jam yang pin UTC lama (jika ada; factory `now()` kini WITA, majoritas test tak pin jam). `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 7: Commit:**
```bash
git add config/app.php database/migrations/..._shift_bt_datetimes_to_wita.php tests/Unit/AppTimezoneTest.php tests/Unit/DeadlineCalculatorTest.php
git commit -m "fix(sla): jam operasional WITA + data historis digeser"
```

---

### Task 2 (D1): Visibilitas pelaksana (scope bersama)

**Files:**
- Modify: `app/Models/WorkOrder.php` (scope baru)
- Modify: `app/Http/Controllers/WorkOrderController.php` — `index()` (:75-87) dan `timeline()` (:117-131) memakai scope
- Create: `tests/Feature/WorkOrderVisibilityTest.php` (pakai `WorkOrder::factory()`)

**Interfaces:**
- Consumes: `WorkOrder::factory()`, relasi `users.employee_id` (link akun, sudah ada).
- Produces: `WorkOrder::scopeVisibleFor(Builder $query, User $user): void` — dipakai index + timeline; nanti dirujuk Task 3 untuk gagasan pihak terlibat.

- [ ] **Step 1: tulis test gagal** (`tests/Feature/WorkOrderVisibilityTest.php`), stempel datanya pindah WO:
```php
// data: tenant via createTestTenant/initTenant (pola Pest.php existing)
it('shows assigned WOs to the assignee in index', function () {
    // karyawan pelaksana di dept A, WO dituju dept B, assigned_employees=[employee.id]
    actingAs($assigneeUser)->get(route('work-orders.index', tenantPath()))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/index')
            ->where('workOrders.data.0.nomor_wo', $wo->nomor_wo));
});
it('keeps the timeline scoped the same way', function () { /* scheduled_date hari ini + assigned ke pelaksana lintas dept muncul */ });
it('keeps unrelated karyawan out of the list', function () { /* karyawan role karyawan tanpa keterlibatan → data kosong */ });
it('respects requester + hod visibility unchanged', function () { /* requester lihat WO-nya; HOD dept target lihat */ });
```
- [ ] **Step 2:** `php artisan test --filter=WorkOrderVisibilityTest` → FAIL (semantik lama).
- [ ] **Step 3:** Implementasi scope di `WorkOrder`:
```php
public function scopeVisibleFor(Builder $query, User $user): void
{
    $employeeId = $user->employee_id;
    $query->where(function ($q) use ($user, $employeeId): void {
        $q->where('requester_user_id', $user->getKey());
        if ($employeeId !== null) {
            $q->orWhereJsonContains('assigned_employees', $employeeId);
        }
    });
    // klausul dept (hod_user_id/manager_user_id) ditambahkan oleh pemanggil
    // (index/timeline) karena hanya relevan di sana — lihat langkah 4.
}
```
Catatan: klausul dept header tetap di controller (butuh collection id); controller menambah `->orWhereIn('target_department_id', $ids)` di sekitar scope. Bentuk final di controller:
```php
$workOrder = WorkOrder::query()
    ->where(function ($q) use ($user, $ids): void {
        $q->visibleFor($user);                 // requester + assigned
        if ($ids->isNotEmpty()) {
            $q->orWhereIn('target_department_id', $ids);
        }
    });
```
(Choice: scope memuat requester+assigned; dept belahan dipisah agar scope bisa dipakai timeline tanpa dobel klausul.)
- [ ] **Step 4:** `index()` + `timeline()` memanggil scope itu (hapus klausul lama).
- [ ] **Step 5:** `php artisan test --filter=WorkOrderVisibilityTest` → PASS; lalu `php artisan test --compact`.
- [ ] **Step 6:** pint + commit:
```bash
git commit -m "feat(wo): pelaksana melihat WO-nya di index+timeline"
```

---

### Task 3 (D3): Gate `work-order.view` di show + attachment

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (definisi gate; region `Gate::before` :91)
- Modify: `app/Http/Controllers/WorkOrderController.php` — `show()` (:412) + `attachment()` (:516)
- Modify: `tests/Feature/Rbac/PermissionConsistencyTest.php:106` — hapus entry allowlist `'work-order.read' => 'gated when the WO flow lands'`
- Create: `tests/Feature/WorkOrderDetailViewGateTest.php`

**Interfaces:**
- Consumes: `$user->can('work-order.read')` (katalog existing), `users.employee_id`, `Gate::authorize()`.
- Produces: gate `'work-order.view'` — signature `($user, WorkOrder $workOrder): bool`; tidak dipakai halaman lain.

- [ ] **Step 1: tulis test gagal** (`WorkOrderDetailViewGateTest.php`), kasus wajib:
    1. guest (role `guest`, nol permission, member tenant) → GET show → **403** + baris `audit_events` baru.
    2. requester → 200.
    3. pelaksana lintas-dept (linked, ada di `assigned_employees`) → 200.
    4. HOD dept tujuan → 200.
    5. karyawan bebas-keterlibatan role `karyawan` (punya `work-order.read`) → 200.
    6. user role kustom tanpa `work-order.read` (create role pertama kali di test, nol permission) → 403.
    7. **WO tenant lain via URL → tetap 404** (regresi yang pernah diperbaiki; scope tenant menang atas gate).
    8. attachment(): guest → 403; pelaksana → 200 (stream).
- [ ] **Step 2:** `php artisan test --filter=WorkOrderDetailViewGateTest` → FAIL (401/200 tanpa gate).
- [ ] **Step 3:** Implementasi:
```php
Gate::define('work-order.view', function ($user, $workOrder): bool {
    if ($workOrder->requester_user_id === $user->getKey()) return true;
    $dept = $workOrder->target_department_id;
    if ($dept !== null && Department::query()
        ->where('id', $dept)
        ->where(fn ($q) => $q->where('hod_user_id', $user->getKey())
            ->orWhere('manager_user_id', $user->getKey()))
        ->exists()) return true;
    $employeeId = $user->employee_id;
    if ($employeeId !== null && in_array($employeeId, $workOrder->assigned_employees ?? [], true)) return true;
    return $user->can('work-order.read');
});
```
di AppServiceProvider (dekat Gate::before). `show()`: setelah `firstOrFail()` → `Gate::authorize('work-order.view', $workOrderModel)`. `attachment()`: sama, setelah WO diload.
- [ ] **Step 4:** Cek roleDefaults `general_manager`/`deputy_general_manager` di `RoleAndPermissionSeeder`. Bila belum memegang `work-order.read`: tambahkan verb ke defaults, jalankan `php artisan app:grant-default-permissions` (additive), dan petakan test himpunan grant (pola `GrantDefaultPermissionsTest`).
- [ ] **Step 5:** Hapus baris `:106` allowlist (`work-order.read` kini dikonsumsi gate) → honesty test tetap hijau.
- [ ] **Step 6:** `php artisan test --filter=WorkOrderDetailViewGateTest` PASS → lalu full suite. Pint.
- [ ] **Step 7:** Commit: `git commit -m "feat(rbac): gate detail WO - pihak terlibat / read"`.

---

### Task 4 (F1): Redesain halaman index (impeccable)

**Files:**
- Modify: `resources/js/pages/work-orders/index.tsx` (rebuilt)
- Modify: `app/Http/Controllers/WorkOrderController.php` — payload index + `deadline_state`/`deadline_label`
- Modify: `tests/Feature/WorkOrderVisibilityTest.php` — asersi payload deadline (instruksi Task 2 file sama, tambahan isu terpisah)
- Modify types inline di file halaman (PageProps)

**Interfaces:**
- Consumes: controller `deadlineState()` (existing; `Task 1` domain jam WITA membuat label besok/hari ini benar), scope dari Task 2, pattern pagination `departments/index.tsx` (current_page/last_page prev/next), komponen `StatusBadge`/`CategoryBadge`/`DeadlineRail` existing.
- Produces: tipe `WoIndexPageProps { workOrders: { data: WoRow[]; current_page: number; last_page: number } }`, `WoRow` = kolom index existing + `deadline_state: DeadlineState` + `deadline_label: string`.

**Impeccable protocol (WAJIB sebelum & saat edit):**
- [ ] **Step 1:** Setup konteks: `"D:\Laravel Project\opti-works\.agents\skills\impeccable\scripts\impeccable.cmd" context --target resources/js/pages/work-orders/index.tsx` (Windows). Bila launcher gagal: kirim pesan terpisah "Context loading did not run; I'll read the existing project context directly" lalu baca DESIGN.md langsung.
- [ ] **Step 2:** Baca `reference/new-work.md` (redesain komposisi halaman; dunia DESIGN.md DIPERTAHANKAN — ini refinement of composition, bukan replacement world) + `reference/operate.md`; sebelum edit UI baca `reference/craft-floor.md`. Mode: **Operate**.

- [ ] **Step 3: backend payload — test dulu**
```php
it('ships deadline proximity in the index payload', function () {
    // WO assigned telat (setTestNow manipulasi deadline_date lampau)
    ...->where('workOrders.data.0.deadline_state', 'overdue')
    ...->where('workOrders.data.0.deadline_label', 'Telat H+1');
});
```
Implementasi: di `index()` map sebelum render: `...$wo->only([...]), 'deadline_state' => $state, 'deadline_label' => $label` via `deadlineState($wo)`. paginator tetap: map `->through(...)` supaya meta/links ikut.
- [ ] **Step 4: redesign frontend.** Keputusan komposisi (dari review, dunia DESIGN.md):
    - **Tanpa tabel.** Card-list: setiap WO = baris kartu 3-sinyal (rail deadline 3px kiri; kat: category badge; mono nomor; judul body-strong; subjudul dept; StatusBadge; due row `Jatuh tempo: <tanggal>` mono + label proximity). Desktop: baris lebar 56-64px (Chrome WoListRow w/o table); mobile: kartu bertumpuk ≥56px, tanpa page-level horizontal scroll.
    - **Pagination:** row navigasi prev/next (pola `departments/index.tsx`); `preserveScroll`.
    - **Empty state:** kalimat jelas + CTA "Buat Work Order" (one CTA; heading band tetap pemilik CTA utama).
    - **Kedalaman:** Depth Rule — hairline `border` + `shadow-card`; hover lift hanya baris (actionable). Focus ring token `focus-ring`.
    - Tanpa filter/search baru (YAGNI; sinyal deadline + pagination sudah menyembuhkan kebutuhan).
- [ ] **Step 5:** Verifikasi: `npx tsc --noEmit` → 0; `npm run build` → hijau; screenshot impeccable (desktop+mobile, light+dark) ke `.impeccable/review/` — satu batched pass, perbaikan batched, paling banyak satu pass lanjut.
- [ ] **Step 6:** Full suite lalu commit:
```bash
git commit -m "feat(wo): index card-list 3 sinyal + pagination"
```

---

### Task 5 (F2): Perbaikan show (defect kecil dari review)

**Files:**
- Modify: `resources/js/pages/work-orders/show.tsx` (edits terpisah di bawah)
- Modify: `resources/js/components/deadline-rail.tsx:18-23` (label fallback id)
- Modify: `app/Http/Controllers/WorkOrderController.php` — payload show + `deadline_state`/`deadline_label`
- Modify: `resources/js/pages/work-orders/show.tsx` `showAttachment` link + confirm dialog (pakai `ui/dialog.tsx` — buat komponen lokal `ConfirmDialog` kecil di `resources/js/components/confirm-dialog.tsx`)

**Interfaces:**
- Consumes: `deadlineState()` controller; `ui/dialog.tsx` (Radix Dialog existing — `alert-dialog.tsx` tidak ada di repo).
- Produces: `ConfirmDialog` props `{ title, description, confirmLabel, onConfirm, processing, danger? }` — dipakai VerifyPanel + ExtendStatusPanel.

Checklist fix (tiap item = edit kecil, seluruhnya di task ini):
- [ ] Hapus paragraf "Dimohon oleh" duplikat di body (`show.tsx:177-179`); booking band sudah menampilkan.
- [ ] Breadcrumb: `{ title: '' }` → hanya `[{ title: 'Work Order' }]`.
- [ ] Payload show + `deadline_state`/`deadline_label`; render chip proximity di deretan badge (icon + text + token; tiga-sinyal rule).
- [ ] `ReviewPanel`: radio "Eksekusi Langsung" di atas "Dijadwalkan" (default pertama); accident label tak berubah.
- [ ] `min` input tanggal jadwal → tanggal lokal, bukan `toISOString()` (UTC): hitung dari `new Date()` bagian lokal (`${y}-${m}-${d}`).
- [ ] Lampiran/hasil jadi `<a href={showAttachment.url(...)} target="_blank" rel="noopener">` pembungkus `<img>`.
- [ ] `VerifyPanel`: "Setujui & Tutup WO" diberi `ConfirmDialog` (terminal); "Setujui Extend" (`ExtendStatusPanel`) juga.
- [ ] `DeadlineRail` fallback label Inggris → `'Sesuai jadwal'`; label dari controller tetap sumber utama.

Verifikasi: `npx tsc --noEmit`, `npm run build`, full suite, screenshot show (desktop+mobile, light+dark). Commit:
```bash
git commit -m "fix(wo): pembersihan defect halaman detail"
```

---

### Task 6 (F3): Perbaikan create (band ownership + kategori radio-card + file list)

**Files:**
- Modify: `resources/js/pages/work-orders/create.tsx`
- Modify: `resources/js/components/work-order-category-choice.tsx` (baru, kecil) — RadioCard kategori

**Interfaces:**
- Consumes: `.layout` metadata pattern (`index.tsx:48-54`), ikon Lucide (mis. `CircleAlert` accident, `Zap` normal, `UserRoundCheck` owner), kategori opsi dari payload `categories`.
- Produces: `<WorkOrderCategoryChoice categories value onChange />` — hanya dipakai halaman create.

Checklist:
- [ ] Header band: `CreateWorkOrder.layout = { breadcrumbs: [{title:'Work Order'}, {title:'Buat'}], title: 'Buat Work Order', description: '...' }`; hapus `<Heading>` lokal dan wrapper `mx-auto max-w-xl` (kolom konten shell yang mengatur lebar, laksanakan left-aligned sesuai DESIGN.md).
- [ ] Kategori → RadioCard 3 opsi: icon, label, 1 kalimat konsekuensi (accident: "HOD wajib eksekusi langsung — tidak bisa dijadwalkan"; normal: "HOD boleh langsung atau menjadwalkan"; owner: "Permintaan urgent atas nama owner"); nilai terpilih `int` match `NO_SCHEDULE_CATEGORIES` tetap menyembunyikan field jadwal.
- [ ] Upload: daftar file terpilih (nama+ukuran+`aria-label` remove), clamp 3 file client-side dengan pesan saat kelebihan; server validation tetap sumber kebenaran.
- [ ] Form dalam kartu kedalaman (hairline + shadow, radius 2xl) sesuai Depth Rule.
Verifikasi: tsc + build + screenshot create (desktop+mobile). Commit:
```bash
git commit -m "feat(wo): create band + pilihan kategori kartu + uraian file"
```

---

### Task 7 (Wrap-up)

- [ ] `php artisan test --compact` final; `vendor/bin/pint --dirty --format agent`; `npx tsc --noEmit`; `npm run build`.
- [ ] Update `TODO.md` — section baru "WO review closes (2026-10-23)": item D2/D1/D3 + frontend dengan [x] dan referensi file.
- [ ] Vault sync (AGENTS.md rule): salin keputusan → `Decisions/` (satu catatan `wo-visibility-ownership-wita.md` + update `Index.md`), session log `Sessions/2026-10/23/HHmm-wo-review-closes.md`, changelog baris.
- [ ] Commit akhir:
```bash
git commit -am "chore: TODO + vault catatan sesi WO review"
```

---

## Self-review checklist (dipakai saat eksekusi)

1. Spec coverage: D1/D2/D3 + index/timeline copy + frontend F1-F3 — semua ada task-nya. ✔
2. Step tunggal-aksi: ya (test → fail → implement → pass → commit per task). ✔
3. Tipe konsisten: `DeadlineState`, `scopeVisibleFor`, gate `work-order.view`, `ConfirmDialog` — dipakai di tasks berikut dengan nama sama. ✔
4. Review Focus 5 baris → task pengikut: (1) Task 1 Step 5 verifikasi, (2) pre-flight filter COLUMN_TYPE, (3) Task 2 test sqlite + catatan, (4) Task 3 kasus #7, (5) Task 4 asersi label + screenshot. ✔
5. Panjang plan < panjang kode deskripsi. ✔
