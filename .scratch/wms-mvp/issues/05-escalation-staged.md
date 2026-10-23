# 05: Eskalasi otomatis berjenjang H+3/5/6

**What to build:** Command harian (business-day calculator + config/holidays.php) mengirim notifikasi Team Leader (H+3) → HOD (H+5) → DGM/GM (H+6), sekali per level, dengan rantai fallback HOD (`hod_user_id` → `manager_user_id` → semua user ber-role `hod` di department → Admin Tenant + log kritikal) — perbaikan atas gap opti-work2 (fallback tidak ada, `manager_user_id` tidak pernah dipakai).

**Blocked by:** 03

**Status:** done 2026-10-22 — `app/Console/Commands/CheckDeadlineEscalation.php` (marker sekali-per-level `escalation_h3/h5/h6_sent_at`) + H+6 di-resolve ke DGM/GM dulu dengan fallback rantai HOD (commit `40714c8`); `DeadlineCalculator` + `config/holidays.php` (commit `6e47cf3`); test `EscalationTest` + outbox WA payload H+3 (`WahaGatewayTest`). Reset marker saat extend approve (`ExtendRequestService`).

- [x] H+3 → Team Leader (jika department punya Team Leader), H+5 → HOD, H+6 → DGM/GM
- [x] Sekali per level (sent_at markers)
- [x] Rantai fallback HOD lengkap + log kritikal
- [x] Business-day calculator + kalender libur configurable
