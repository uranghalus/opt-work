# Design — Work Order: 3 Keputusan Review (2026-10-23)

Sumber: review halaman work-order (routing/UX/UI). User memilih semua rekomendasi.
Dikerjakan berurutan **D2 → D1 → D3**, tiap keputusan = commit + test sendiri, suite penuh hijau di akhir.

---
created: 2026-10-23
status: draft (menunggu approval)
---

## Intent & Why

- **D2** — Bug SLA nyata: `assign()` menghitung deadline dari `now()` UTC (WorkOrderService.php:178-180). Assign Senin 07:00 WITA → anchor Minggu → deadline urgent Rabu; assign 08:00 WITA → deadline Kamis. Aturan FR-2.1 (3/6 hari kerja dari tanggal assign) bergeser 1 hari hanya karena jam. Dampak lain: label timeline `isToday`/`deadlineState` salah di 00:00-07:59 WITA; command eskalasi jalan 09:00 UTC (=17:00 WITA).
- **D1** — Pelaksana (persona Sari) tidak punya jalur daftar ke WO yang di-assign kepadanya (query index/timeline hanya requester + dept head/deputy, WorkOrderController.php:70-83, :121-127) — pintu satu-satunya notifikasi; notif terlewat = FR-1.7 submit macet.
- **D3** — `show()` hanya di-scope tenant: URL langsung membuka detail WO siapa pun. Kontras dengan index selektif; backlog "gate work-order.read (guest)" tak pernah selesai di surface ini (TODO:338).

## Approaches (dipertimbangkan → dipilih)

1. **Visibility pelaksana** — dipilih: **klausul `assigned_employees` di index + timeline lewat scope bersama**. Ditolak: halaman "WO saya" terpisah (overkill), status quo (notifikasi saja; flow tetap retak).
2. **Timezone** — dipilih: **`app.timezone` → `Asia/Makassar` + migrasi one-off geser data historis −8 jam**. Ditolak: helper "hari operasional" di UTC (dua jam eksis selamanya; melanggar "perbaiki akar masalah"). Waktu termurah = sekarang, pre-pilot.
3. **Gate detail WO** — dipilih: **gate = pihak terlibat ATAU `work-order.read`**. Ditolak: biarkan terbuka (guest tetap baca semua; backlog tidak selesai).

## Keputusan-keputusan yang di-decide (summary)

### D2 — Timezone (dikerjakan PERTAMA; menyentuh kalibrasi)

- `config/app.php` (atau `.env.example` + `phpunit.xml`) → `Asia/Makassar` sebagai default aktif; env tetap bisa menimpa.
- **Migrasi one-off geser kolom datetime −8 jam** untuk tabel data bisnis: `users`, `employees`, `work_orders`, `work_data`, `work_data_pekerja`, `extend_requests`, `app_notifications`, table `notifications` (Laravel), `audit_logs`, `audit_events`, `wa_messages`, `tenant_wa_sessions`. **Prinsip: tabel runtime/transient (`jobs`, `failed_jobs`, `sessions`, `cache`) TIDAK digeser.**
- Titik yang dicek ulang pasca-ganti: `deadlineState()`, `resolveWeekStart()`, timeline `isToday`, validasi `after_or_equal:today`, `config/holidays.php` (tanggal peg — tidak berubah semantik), `dailyAt('09:00')` eskalasi (kini 09:00 WITA — lebih sesuai jam kerja).
- Verifikasi: suite penuh; test yang pin waktu diperbaiki mengikuti domain jam baru; pint bersih.

### D1 — Visibilitas pelaksana

- Ekstrak satu **scope/Query-helper bersama** (mis. `WorkOrder::scopeVisibleFor($query, User $user)` atau service query) dipakai **index + timeline**: requester ATAU dept yang di-head/deputy ATAU `whereJsonContains('assigned_employees', user->employee_id)` (klausul assigned hanya bila user punya link employee).
- Copy heading index: "Work Order yang Anda buat, yang ditugaskan kepada Anda, dan yang ditujukan ke department Anda" (sesuai data yang benar-benar tampil).
- Test: pelaksana lintas-dept melihat WO assignnya (index + timeline), HOD/requester tidak berubah, user tanpa link employee aman (tanpa error).

### D3 — Gate detail WO

- Gate `work-order.view` di didefinisikan (app service provider / Gate): boleh bila **requester**, atau **hod_user_id/manager_user_id = user** di dept tujuan, atau **employee_id user ∈ assigned_employees**, atau **`user->can('work-order.read')`** (roleDefaults sudah me-grant read ke semua role staff; guest = nol permission → terblokir by design, ADR-0006).
- Diterapkan di **`show()` dan `attachment()`** (keduanya di-scoped sama; attachment ikut gate supaya lewat detail juga sama-sama terkunci). Endpoint review/assign/submit/verify tetap pakai permission gate masing-masing.
- 403 → surface 403 existing + tercatat ke `audit_events` (AccessDeniedAuditor — FR-6.3, by design).
- **Honesty test**: `work-order.read` keluar dari forward-looking allowlist `PermissionConsistencyTest` di commit yang sama (permission kini dikonsumsi surface `show`/`attachment`).
- Callback GM/DGM: cek roleDefaults; kalau belum memegang `work-order.read`, tambahkan via `app:grant-default-permissions` (additive-only) — diverifikasi, bukan diasumsikan.
- Link notifikasi tetap hidup: semua penerima notifikasi adalah pihak terlibat.

## Non-Goals

- ❌ Sisa temuan UI batch kecil (pagination, kolom deadline, confirm dialog, radio-card kategori, dll.) — batch terpisah setelah ini.
- ❌ Daily Work / work_planning / Extend Work Schedule.
- ❌ Refactor di luar tiga titik di atas.

## Constraints (tak diganggu)

- Tenant middleware + scope `BelongsToTenant` tidak berubah.
- UI mirror principle: visibilitas panel tetap dihitung server-side.
- Suite penuh 538 test harus hijau di akhir; pint + tsc + build untuk bagian frontend yang tersentuh (copy index).
