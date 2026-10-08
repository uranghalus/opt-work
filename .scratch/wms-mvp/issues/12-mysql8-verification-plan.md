# 12: MySQL 8 verification plan

**What to build:** Rencana verifikasi MySQL 8 tanpa mengganggu dev lokal MariaDB 10.4 XAMPP.

**Blocked by:** tenancy-reconfig closed

**Status:** ready-for-agent

- [ ] Buat checklist kompatibilitas: json columns, generated columns, charset utf8mb4_unicode_ci
- [ ] Buat docker-compose.mysql8.yml untuk dev opt-in, tidak replace XAMPP
- [ ] Script migrasi test: `php artisan migrate:fresh --database=mysql8` di CI dry-run
- [ ] Dokumentasi keputusan: verifikasi hanya jika target produksi MySQL 8 dikonfirmasi
