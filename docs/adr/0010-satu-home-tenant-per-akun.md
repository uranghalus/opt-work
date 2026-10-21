---
status: accepted
---

# Satu home tenant per akun (batas final MVP)

Grilling 2026-10-21 (Q14): memutuskan nasib staf yang bekerja di lebih dari satu cabang. Opsi yang dipertimbangkan: (a) satu home tenant per akun — status quo didokumentasikan, (b) freeze `users.tenant_id` agar mutasi cabang butuh intervensi admin, (c) pivot `employee_user` + membership multi-tenant.

## Decisions

1. **Satu akun = satu home tenant, final untuk MVP.** Tidak ada pivot `employee_user`; relasi tetap `users.employee_id` (kolom tunggal, unique index `users_employee_id_unique`).
2. **"Last assertion wins" dipertahankan sebagai perilaku, bukan kebetulan.** Setiap login SAML menulis ulang `users.tenant_id` dari nama perusahaan pada assertion (`SamlController::acs`). Konsekuensi yang diterima: mutasi cabang cukup dengan login lewat cabang baru — akses ke cabang lama otomatis hilang; tanpa kode tambahan dan tanpa peran admin.
3. **Akun yang sama tidak bisa "terdaftar ganda" per cabang.** `users.email` unique global; orang yang bekerja di dua cabang memakai SATU akun yang home-tenant-nya berpindah mengikuti assertion terakhir, atau (untuk peran berbeda per cabang) memakai akun berbeda dengan email berbeda bila identitas portal mengizinkan.
4. **Pivot membership multi-tenant = proyek v2, bukan perbaikan kecil.** Blocker teknis yang terdokumentasi (eksplorasi 2026-10-21): skalar `users.tenant_id` yang menentukan akses (`TenantAccess::canOperate`, `operableTenants`, `EnsureTenantAccess`, `LoginLanding`), resolusi SAML single-valued, role global (`config/permission.php` teams=false) yang akan jadi privilege leak lintas cabang, `BelongsToTenant` scope di semua model, dan routing notifikasi (nomor WA, channel tenant, tabel `notifications` tanpa dimensi tenant). Semua harus dirancang ulang, bukan ditambal.

## Consequences

- Halaman karyawan/akun tidak perlu UI "akun lintas cabang" — kandidat tautan tetap disaring per home tenant (seperti sekarang).
- Skenario "staf rata-rata pindah cabang" terlayani otomatis oleh login assertion berikutnya; tidak ada data migration.
- Jika kelak muncul kebutuhan nyata staf aktif di dua cabang bersamaan, keputusan ini dibuka kembali dengan desain pivot tersendiri — jangan menambah kolom kedua secara diam-diam.

## References

- Grilling 2026-10-21 — Q14 (user menerima rekomendasi "satu home tenant").
- Eksplorasi fakta 2026-10-21: `SamlController::acs` (tulis `tenant_id`), `SamlTenantResolver` (1 assertion = 1 company), `TenantAccess`, `config/permission.php` teams=false, unique `users.email` + `users.employee_id`.
- `.scratch/employee-page/research/employee-page-improvements.md` — catatan "one home-tenant employee link per account" untuk MVP.
- [[Decisions/employee-account-link-unlink]] — batas tautan akun ↔ karyawan.
- [[Decisions/superadmin-only-role-assignment]] — Q6 grant/revoke superadmin-only.
