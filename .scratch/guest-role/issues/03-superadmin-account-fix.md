# 03: Akun super admin — diagnosis, seeder idempoten, perintah one-off

**What to build:** Root cause (Phase 2): role sudah ada, link
`users.employee_id` tidak pernah dibuat. Fix: seeder menautkan akun
platform ke employee ber-email sama + perintah one-off untuk DB existing.

**Status:** done 2026-10-17 (fix menunggu review user: `--dry-run` ditampilkan, versi tulis dijalankan setelah persetujuan)

- [x] Seeder: link employee by email bila `employee_id` null
      (`withoutGlobalScope('tenant')` — `db:seed` tanpa tenant current)
- [x] Perintah `app:grant-super-admin-role` (idempoten, additive-only,
      `--dry-run`, tanpa delete, `tenant_id` tak disentuh)
- [x] `SuperadminAccountTest` (5): seed = flag+role+link; idempoten tanpa
      pivot duplikat; platform lolos `/hak-akses`; perintah memperbaiki DB
      existing; dry-run tidak menulis
- [ ] Tampilkan `--dry-run` ke user sebelum menjalankan versi tulis di dev DB