# Design — Daily Work FR-5.x (2026-10-23)

Sumber: PRD §6.5 (FR-5.1–5.4) · issue `.scratch/wms-mvp/issues/07-daily-work-templates.md` · OQ-4 settled (template per karyawan, opsi A).
User menyetujui pendekatan di percakapan 2026-10-23. Nol kode sebelumnya — desain baru, bukan port.
**Temuan referensi:** `tb_work_daily` di repo lama `opti-work` = log harian per eksekusi WO (konsep berbeda) — nama tabel baru sengaja dijauhkan.

---
created: 2026-10-23
status: draft (menunggu approval doc)
---

## Intent & Why

Daily Work adalah modul MVP terakhir yang nol kode. Persona: **Sari** (karyawan) harus punya daftar tugas rutin harian yang sama tiap hari tanpa HOD mengulang input, plus tugas tambahan yang muncul dengan notifikasi; **Budi** (HOD) mengelola template rutin per karyawan dan menugaskan tugas tambahan tertanggal. Tanpa ini, rutinitas harian dikelola via chat (tanpa audit trail) — masalah yang sama yang memunculkan WMS.

- FR-5.1 — template tugas rutin per karyawan, dikelola HOD (OQ-4: opsi A).
- FR-5.2 — HOD menambah tugas tambahan untuk karyawan tertentu pada tanggal tertentu → notifikasi.
- FR-5.3 — karyawan melihat gabungan template + tugas tambahan sebagai satu daftar harian.
- FR-5.4 — tiap item punya status (belum/proses/selesai) dan lokasi pekerjaan.

## Approaches (dipertimbangkan → dipilih)

**Bagaimana item template (recurring) mendapat status + lokasi per hari:**

1. **A. Materialize-on-view — DIPILIH.** Saat daftar tanggal D dibuka (endpoint index), item template aktif yang cocok dengan hari itu di-upsert ke `daily_works` (idempoten, guard unik `(template_id, work_date)`). Satu tabel untuk status/lokasi/riwayat, satu endpoint update, tanpa cron.
2. Ditolak **compute-on-read** (template dihitung saat baca + tabel log progres terpisah): dua titik update status = dua code path untuk UI yang sama; query "riwayat per karyawan" jadi union.
3. Ditolak **cron materialize tengah malam**: butuh scheduler + edge case (template dibuat siang hari, karyawan baru, hari libur); kegagalan job senyap.

## Keputusan (summary)

### Skema — 2 tabel baru (migrasi + model + factory)

- `daily_work_templates` — `id` (uuid), `tenant_id` (BelongsToTenant), `employee_id` FK, `title`, `description` nullable, `weekdays` json (int 0–6, `1..5` default Sen–Jum), `is_active` bool default true, `created_by` FK users nullable, timestamps. Dikelola HOD.
- `daily_works` — `id` (uuid), `tenant_id`, `employee_id` FK, `work_date` (date), `title`, `description` nullable, `location` nullable, `status` enum `pending|in_progress|done` (default pending), `done_at` nullable, `source` enum `template|adhoc`, `template_id` FK nullable (lineage; `nullOnDelete` supaya hapus template tidak menghapus riwayat), `created_by` FK users nullable, timestamps. Index `(tenant_id, employee_id, work_date)` + unique `(template_id, work_date)` (partial-guard: template_id NOT NULL rows).

### Materialize-on-view (inti opsi A)

- `DailyWorkService::materializeFor(Employee $employee, Carbon $date): void` — di dalam `DB::transaction`, `upsert`/`firstOrCreate` per template aktif yang `weekdays` mengandung hari `date` (bandingkan dengan **tanggal lokal WITA** via `Carbon::parse($date)` — app.timezone sudah `Asia/Makassar`). Upsert HANYA mengisi kolom turunan template (title/description); kolom progres (status/location/done_at) TIDAK pernah ditimpa.
- Dipanggil dari: endpoint index (karyawan sendiri) dan endpoint team-show HOD. Tidak dipanggil dari store adhoc (bukan penggabung).
- Hapus template → instance terlanjur-materialize tetap ada sebagai riwayat (source=template, template_id null-on-delete); `is_active=false` → berhenti muncul untuk tanggal baru saja.

### Izin (pola RBAC existing — katalog + roleDefaults + honesty test)

- `daily-work.read` — lihat daftar harian sendiri (semua role staff; guest nol izin → terblokir).
- `daily-work.update` — karyawan mengubah status/lokasi **item miliknya sendiri** (gate + assert `employee_id` milik user).
- `daily-work.manage` — HOD: CRUD template + tugas tambahan (karyawan di department-nya). TL tidak di-MVP.
- `work-order.read` tetap allowlist — keluar dari forward-looking allowlist HANYA izin daily-work yang mulai dikonsumsi (honesty test `PermissionConsistencyTest` di commit yang sama).
- `app:grant-default-permissions` dijalankan (additive-only, idempoten).

### Endpoint & rute (grup tenant existing, Wayfinder regenerate)

- `GET /daily-work?date=&search=&status=&page=` — daftar harian karyawan (materialize dulu, lalu render `daily_works` tanggal itu; filter server-side + paginasi).
- `PATCH /daily-work/{dailyWork}` — update status/lokasi item sendiri (`daily-work.update`).
- `GET /daily-work/templates?employee=&search=&is_active=&page=` (HOD) — daftar template per karyawan (filter server-side + paginasi).
- `POST|PATCH|DELETE /daily-work/templates{/{template}}` — CRUD template (HOD, karyawan dalam department-nya).
- `POST /daily-work/adhoc` — tugas tambahan (HOD → karyawan + tanggal + judul + lokasi?) → notifikasi.
- `GET /daily-work/team?employee=&date=&search=&status=&page=` (HOD) — daftar harian karyawan bawahan (materialize juga; pratinjau yang sama yang dilihat karyawan; filter + paginasi).

### UI (DESIGN.md, token-based, tanpa tabel — komponen shadcn)

**Prinsip dari user: tidak boleh tampilan tabel; pakai komponen shadcn; pencarian + filter + paginasi WAJIB ada di fitur ini.**

- `resources/js/pages/daily-work/index.tsx` — daftar harian karyawan: **card list** (bukan tabel) per tanggal — header tanggal + navigasi prev/next hari + "Hari ini"; **filter bar**: search judul (debounced, `Input`), filter status (segmented `ToggleGroup`: semua/belum/proses/selesai); item = `Card` dengan status action inline + input lokasi; paginasi server-side items (prev/next + nomor halaman, pola `departments/index.tsx`); `EmptyState` + CTA; `Skeleton` saat loading.
- `resources/js/pages/daily-work/templates.tsx` — HOD: **card grid** template per karyawan (bukan tabel) — combobox karyawan (pola async combobox employee search, debounce+AbortController), **search judul** + **filter status aktif** (`Select`/`ToggleGroup`), paginasi server-side; tambah/edit via `Dialog` + `Input`/`Textarea`/`ToggleGroup` weekdays picker; section **Tugas Tambahan**: form `Dialog` (karyawan + tanggal + judul + lokasi) + daftar adhoc (card, filter tanggal/status, paginasi).
- Backend mendukung: semua endpoint list menerima `search`, `status`/`is_active`, `page` (+ `per_page` default existing); filter server-side (pola `EmployeeQuery`), payload + meta paginasi.
- Semua komponen token-based (tanpa raw hex), tombol/touch target sesuai DESIGN.md, reduced-motion respected.

### Notifikasi (pola ADR-0009 + preseden extend "event lain menyusul bersama fiturnya")

- `DailyWorkAssigned` notification ke karyawan saat adhoc dibuat: database + broadcast + `toWhatsApp()` (channel Waha existing — no-op bila WAHA_URL kosong). Route link ke `/daily-work?date=…`.
- Perubahan template (tambah/edit) TIDAK menotifikasi (bukan penugasan baru); tugas tambahan saja.

## Non-Goals

- ❌ Papan harian department untuk HOD (kanban/matriks) — cukup team-show per karyawan.
- ❌ Progres persentase, foto per item, prioritas/level item (field di repo lama — bukan FR-5.4).
- ❌ Recoming selain weekday (tanggal spesifik berulang bulanan) — kalau dibutuhkan, tambah kolom tanpa ubah arsitektur.
- ❌ Ekspor/reporting Daily Work (Non-Goals PRD reporting).
- ❌ Template per jabatan/department (OQ-4 settled: per karyawan).

## Constraints (tak diganggu)

- Tenant middleware + `BelongsToTenant` scope; semua query tenant-scoped.
- `app.timezone` = `Asia/Makassar` (D2) — semua "hari ini" via `Carbon` default.
- Satu transaksi per mutasi; notifikasi dispatch after-commit (pola channel existing).
- Suite penuh hijau di akhir (baseline 582 test: 574 pass, 8 skip) + pint + tsc + `npm run build`.
- Dikerjakan di branch `features/department-syncronize`, commit konvensional per task.

## Verifikasi (acceptance test sketch)

- `DailyWorkMaterializeTest` — template Sen–Jum muncul di Senin, tidak di Sabtu; `is_active=false` berhenti; upsert tidak menimpa status; hapus template tidak menghapus instance riwayat; lintas-tenant tidak bocor.
- `DailyWorkUpdateTest` — karyawan ubah status/lokasi item sendiri; item milik orang lain 403/404; transisi done mengisi `done_at`.
- `DailyWorkTemplateTest` — CRUD HOD; HOD department lain 403; validasi weekdays.
- `DailyWorkAdhocTest` — adhoc tertanggal → notifikasi (database/broadcast/WA outbox) ke karyawan; karyawan non-bawahan 403.
- `DailyWorkListFilterTest` — search judul + filter status + paginasi (meta/total) di index & team & templates; kombinasi filter; empty state.
- Rute + izin: `PermissionConsistencyTest` (izin baru terdaftar, allowlist tak bertambah), guest 403.
