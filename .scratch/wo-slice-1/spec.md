# Spec — WO Slice 1: Review HOD + Assign + Deadline (issue 03 wms-mvp)

Sumber keputusan: grill 2026-10-17 (settled Q1–Q14). Referensi implementasi: `opti-work2`
(`hodApprove()`/`assignEmployees()`, migration `add_workflow_fields_to_work_orders`).
Issue asal: `.scratch/wms-mvp/issues/03-work-order-review-assign-deadline.md`.

## Scope (persis 4 checkbox issue 03)

1. Keputusan HOD: eksekusi langsung / jadwalkan sesuai aturan kategori (Accident dipaksa langsung).
2. Assign 1+ karyawan pelaksana + notifikasi ke assignee.
3. Deadline otomatis: urgent 3 / normal 6 hari kerja, dari **tanggal assign**, libur dari `config/holidays.php`.
4. Semua perubahan status tercatat di histori (siapa, kapan, aksi).

**Di luar scope:** submit/verify (issue 04), pengiriman eskalasi (issue 05), extend jadwal (issue 06),
work_planning/schedule_wd (tinggal di issue 06), CRUD Akun.

## Keputusan settled

- **Migration:** kolom deadline+eskalasi SUDAH ada (migration 3 Okt). Migration baru hanya:
  `hod_action` (string nullable: `execute_immediately|schedule`), `scheduled_date` (date nullable),
  `assigned_at` (timestamp nullable), `hod_notes` (text nullable). Penamaan mengikuti referensi opti-work2.
- **Dua endpoint**, keduanya di belakang grup `{tenant}`:
  - `POST {tenant}/work-orders/{workOrder}/review` — gated `work-order.review` (Gate::authorize di controller,
    pola `akun.update`). Payload: `action` (required: execute_immediately|schedule), `scheduled_date`
    (required_if schedule, after_or_equal today), `hod_notes` (nullable). Guard: kategori Accident +
    action ≠ execute_immediately → validation error. Transisi: `waiting_hod` → `hod_approved` | `scheduled`.
  - `POST {tenant}/work-orders/{workOrder}/assign` — gated `work-order.assign`. Payload: `employees`
    (required, array, min 1, id karyawan di department target WO), `assignment_notes` (nullable).
    Efek: status → `assigned`, `assigned_at = now()` (sekali saja), deadline dihitung & disimpan,
    notifikasi ke assignee, entri histori. Boleh dipanggil ulang untuk revisi penugasan.
- **Status:** tambah `WorkOrderStatus::HodApproved` (`hod_approved`, label "Disetujui HOD") — state antara
  eksekusi-langsung-sebelum-assign (mengikuti referensi opti-work2 `hod_approved`).
- **Deadline:** `config/holidays.php` + kalkulator business-day (service). Urgent 3 / Normal 6 hari kerja
  dari `assigned_at`; berlaku untuk kedua jalur eksekusi. Accident = urgent (3 hari).
- **Histori:** tabel append-only `work_order_events`: `work_order_id` FK, `user_id` FK, `action` (string),
  `from_status`/`to_status` (string nullable), `meta` (json nullable), timestamps. Entri slice 1:
  `reviewed`, `assigned` (+ `created` untuk WO baru mulai sekarang — tidak backfill).
- **Notifikasi:** `WorkOrderAssigned` (pola `WorkOrderCreated`: via `database` + `broadcast`, type
  `work_order.assigned`, payload id/nomor/title/message). Penerima: `Employee::user` (akun link) dari
  assignee; karyawan tanpa akun link → assign tetap sah, tercatat di meta event + log (gap opti-work2 ditutup).
- **Fix scope bocor (ditemukan saat review):** `show()`/`attachment()` memakai
  `withoutGlobalScope('tenant')` tanpa verifikasi tenant_id → hapus, kembalikan ke query ber-scope,
  test regresi cross-tenant. Slice 1 juga meng-gate `index`/`show` dengan `work-order.read`?
  TIDAK — hanya review+assign yang di-gate di slice ini; gating create/read menyusul saat
  permukaannya disentuh (keputusan per-entry).
- **UI:** panel review/assign di `work-orders/show`, pola existing (AppLayout + shadcn, dialog
  self-contained ala SyncButton/Ubah Peran), hidden tanpa verb (`can['work-order.review'/'assign']` —
  flag baru via Gate, mengikuti ADR-0004 rule 7), Accident menyembunyikan opsi jadwal
  (`WorkOrderCategory::allowsScheduling()`).

## Verifikasi per seam

`vendor/bin/pint --dirty --format agent` · test terdampak per commit · full suite di akhir ·
`npm run types:check` + `vp check` + `npm run build` untuk seam frontend.

## Kriteria selesai slice

- [x] 4 checkbox issue 03 terpenuhi + test-nya hijau
- [x] Full suite: 313 tests / 305 passed / 8 skipped / 0 failed — MENGECEKUIKAN WIP sesi
      paralel (SyncEmployeesTest, EmployeeBanTest, DebugSyncTest, SyncDepartmentsTest:
      6 failure merah milik red-green mereka yang sedang berjalan, bukan slice ini)
- [x] Build + types green
- [x] Allowlist `work-order.review` + `work-order.assign` sudah dikeluarkan (honesty test)
- [x] TODO.md + changelog vault di-update

## Temuan bonus selama slice (root-cause fixes)

- `config/multitenancy.php` `queueable_to_job` tertukar → semua broadcast/mail yang
  di-queue crash di probe tenant-aware; diperbaiki di commit ticket 02 (`ea6779d`)
- Cross-tenant read di `show()`/`attachment()` (withoutGlobalScope) → 404 kini, commit `6e47cf3`
