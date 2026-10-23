# 06: Extend WO telat + Extend Work Schedule

**What to build:** Pengajuan extend (maks 3 hari per request, maks 3 approved extends per WO, blokir extend pending duplikat per WO) dengan approval berjenjang Team Leader → HOD; extend Work Schedule oleh HOD wajib approval DGM/GM — alur terpisah; audit siapa approve/reject + timestamp + alasan.

**Blocked by:** 05

**Status:** done 2026-10-23 — FR-2.3–2.5 `extend_requests` + `ExtendRequestService` (guard telat + maks 3 hari/request + maks 3 approved + blokir pending duplikat, rantai TL→HOD, self-approval ditolak, reset marker eskalasi, `DeadlineCalculator::addBusinessDays`) + panel UI (commits `feat(work-order): extend deadline…` + `1dd908c`); FR-2.4 Extend Work Schedule via `work_plannings` — HOD mengaju → `pending_extend_approval` → DGM/GM approve (scheduled_date bergeser, extend_count++) / reject (revert), event `schedule_extend_*` (commit `7e114f6`).

- [x] Extend WO telat: maks 3 hari/request, maks 3 approved/WO, cek pending duplikat
- [x] Approval berjenjang: TL (jika ada) → HOD; tanpa TL langsung HOD
- [x] Extend Work Schedule: wajib approval DGM/GM (alur terpisah)
- [x] Audit trail approval/reject + timestamp + alasan
