# 01: Fondasi — migration, enum, holidays, kalkulator deadline, fix scope

**What to build:** Prasyarat slice 1 yang tidak menyentuh endpoint: migration kolom review/assign,
case enum `HodApproved`, `config/holidays.php` + kalkulator business-day, dan perbaikan scope
cross-tenant di `show()`/`attachment()`.

**Blocked by:** — (slice 1 = issue 03 wms-mvp)

**Status:** done 2026-10-17 (commit `6e47cf3`)

- [x] Migration `add_review_and_assign_fields_to_work_orders`: `hod_action`, `scheduled_date`,
      `assigned_at`, `hod_notes` (kolom deadline/eskalasi sudah ada sejak 3 Okt — jangan di-duplicate)
- [x] `WorkOrderStatus::HodApproved = 'hod_approved'` + label "Disetujui HOD"
- [x] `config/holidays.php` (fixed `Y-m-d` + recurring `MM-DD`) + `DeadlineCalculator`:
      urgent 3 / normal 6 hari kerja dari `assigned_at`; 7 test (weekend, libur fixed/recurring,
      assign di hari libur/weekend)
- [x] `show()`/`attachment()`: buang `withoutGlobalScope('tenant')`; test regresi: WO tenant lain
      → 404 meski UUID diketahui (sebelumnya 200 — terbukti merah dulu)
- [x] Test: enum + label; WorkOrderTest 23/23 hijau; pint bersih
- [ ] Catatan: sesi paralel mengerjakan SyncDepartments di working tree yang sama — WIP-nya
      tidak diikutkan commit (`6e47cf3` hanya file ticket ini)
