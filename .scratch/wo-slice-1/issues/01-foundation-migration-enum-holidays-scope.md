# 01: Fondasi — migration, enum, holidays, kalkulator deadline, fix scope

**What to build:** Prasyarat slice 1 yang tidak menyentuh endpoint: migration kolom review/assign,
case enum `HodApproved`, `config/holidays.php` + kalkulator business-day, dan perbaikan scope
cross-tenant di `show()`/`attachment()`.

**Blocked by:** — (slice 1 = issue 03 wms-mvp)

**Status:** ready-for-agent

- [ ] Migration `add_review_and_assign_fields_to_work_orders`: `hod_action`, `scheduled_date`,
      `assigned_at`, `hod_notes` (kolom deadline/eskalasi sudah ada sejak 3 Okt — jangan di-duplicate)
- [ ] `WorkOrderStatus::HodApproved = 'hod_approved'` + label "Disetujui HOD"
- [ ] `config/holidays.php` (array tanggal libur) + service kalkulator business-day:
      urgent 3 / normal 6 hari kerja dari `assigned_at`; unit test tanggal lintas libur + weekend
- [ ] `show()`/`attachment()`: buang `withoutGlobalScope('tenant')`, query ber-scope; test regresi:
      WO tenant lain → 404 meski UUID diketahui
- [ ] Test: transisi status enum valid, label enum tampil
