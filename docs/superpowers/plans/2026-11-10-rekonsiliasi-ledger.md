# Ledger — Rekonsiliasi Branch WO (2026-11-10)

## Baseline `main` @ b90863a (worktree `opti-works-reconcile`)
- `vendor` terpasang fresh di worktree (composer install). `.env` dari `.env.example` (baris `[TEMPLATE]` dibuang) + `key:generate`. `.env.testing` disalin; `public/build` disalin dari repo asli (hindari Vite manifest missing di test Inertia).
- **Suite `main`: 655 tests · 642 passed · 5 failed · 8 skipped · 0 error.**
- 5 kegagalan baseline (BUKAN regresi; jangan dikejar):
  1. `it renders the local login screen for guests` (auth/SSO flag — kemungkinan env)
  2. `it previews a subordinate daily list and materializes templates for that date`
  3. `it lists active assignments with the server computed timer for the assignee`
  4. `it keeps assignments invisible to users who are not assignees`
  5. `it surfaces pending extend requests only to the deciding approver (R21)`
- **Floor non-regresi:** hasil akhir ≤ 5 failed dari himpunan yang sama (idealnya 0). Jika muncul kegagalan LAIN → itu regresi merge, wajib diperbaiki.

## Keputusan (Rulings)
- R0.1: Integrasi berbasis `main` (bukan branch). Alasan: `main` lebih kaya (RequestService/WorkTimeLogService/DailyWork/eskalasi) + test.
- R0.2: Worktree terpisah + `composer install` sendiri (BUKAN junction) — junction menyebabkan autoload menunjuk tree lain ("Target class [config] tidak ada"), pelajaran dari audit.
- R0.3: Raise memory `php -d memory_limit=1536M vendor/bin/pest` — `php artisan test` meneruskan 128M ke child process dan OOM.

## Progress
- [x] Fase 0.1 worktree
- [x] Fase 0.2 baseline
- [x] Fase 0.3 backup branch `backup/features-department-syncronize-20261110`
- [x] Fase 1 merge + resolusi konflik → commit `9f83afd` (51 file)
- [x] Fase 2 gate: suite **676 test / 663 lulus / 5 gagal / 0 error** = baseline `main` (0 regresi, +21 net pass dari branch).

### Catatan resolusi Fase 1 (penting)
- **Base = `main`** untuk Service/RequestService/WorkTimeLogService/DailyWork (lebih kaya + test). Branch hanya menambah tulisan `status_tiket`/`status_pekerjaan` di tiap transisi; saya sisipkan di `create()` dan `WorkOrderRequestService` **belum** dual-write → GATE controller saya ubah membaca `status` (sumber tunggal yang selalu terisi), bukan `status_tiket`. Split kolom tetap ditulis untuk display.
- Controller diambil versi **branch** (superset: edit/update/destroy + 4 page endpoint + timeline grid). Lalu:
  - hapus `Gate::authorize('work-order.create')` (create+store) & `work-order.read` (index) & `work-order.update` (updateRequest) — **bertentangan OQ-1** (setiap user = requester) + bikin test `main` 403.
  - timeline: tambah prop `events` + `range` (di samping prop grid `weekStart/.../days`) agar `WorkOrderVisibilityTest` lulus.
  - gate `canUpdate/canCancel/canEdit/canCancelDuplicate/canDecideRequest/executionStage` dialihkan `status_tiket|status_pekerjaan` → `status`.
- `routes/web.php`: assign/verify didaftarkan **dua nama** (test pakai `work-orders.assign|verify`, FE pakai `*-submit`).
- Migrasi: hapus duplikat `2026_10_28_000001` (identik dengan `main` `2026_10_24_142826`).
- Fix `Edit.tsx`→`edit.tsx` (case-sensitive; render `work-orders/edit`).
- 5 gagal sisa = **persis baseline `main`** (login-screen, daily-work preview, 2× work-time visibility, extend approver) → non-regresi, tiket terpisah.

### Ruling terbuka (dicatat, TIDAK dikerjakan di sini → P1-A/P1-B/P2)
- P1-A tetap terbuka: `priority/personnel_count/damage_type` masih gagal persist (store()+service belum menyertakan) — verifikasi ulang di baseline final.
- P1-B: semantik H+N — perlu cek `CheckDeadlineEscalation` di branch ternyata diverge; untuk merge ini saya biarkan versi branch (belum di-reconcile ke versi `main`). **TODO P1-B belum selesai** — lihat catatan Fase 4 di plan; saya belum menjalankan TDD P1-B pada sesi ini.

