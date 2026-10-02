# 05: Eskalasi otomatis berjenjang H+3/5/6

**What to build:** Command harian (business-day calculator + config/holidays.php) mengirim notifikasi Team Leader (H+3) → HOD (H+5) → DGM/GM (H+6), sekali per level, dengan rantai fallback HOD (`hod_user_id` → `manager_user_id` → semua user ber-role `hod` di department → Admin Tenant + log kritikal) — perbaikan atas gap opti-work2 (fallback tidak ada, `manager_user_id` tidak pernah dipakai).

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] H+3 → Team Leader (jika department punya Team Leader), H+5 → HOD, H+6 → DGM/GM
- [ ] Sekali per level (sent_at markers)
- [ ] Rantai fallback HOD lengkap + log kritikal
- [ ] Business-day calculator + kalender libur configurable
