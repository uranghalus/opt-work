---
status: accepted
---

<<<<<<< HEAD
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
=======
# One home tenant per account; the `employee_user` pivot is deferred (Q14)

The same person can exist as karyawan rows in several branches (`employees.email` is nullable and not unique), but `users.email` is globally unique and `users.employee_id` carries a single unique link — so one account can ever point at one karyawan. Q14 asked whether cross-branch staff should be modelled with an `employee_user` pivot now.

## Decisions

1. **One home tenant per account — final for the MVP.** `users.tenant_id` stays the single scalar home tenant; `TenantAccess` keeps granting non-superadmins exactly that one branch.
2. **Last-assertion-wins is retained.** Every SAML login persists the asserted company as the home tenant (`SamlController::acs`); the portal company attribute, not the employee link, drives it.
3. **Drift is logged, never auto-repaired.** When a login flips the home tenant away from the linked karyawan's cabang, the login succeeds and the link survives; the drift guard (`SamlController::warnHomeTenantDrift`) records a `Log::warning` so the downstream breakage (role-write and `tenant.access` 403s) is diagnosable instead of silent. Breaking or re-pointing links is never done automatically.
4. **The pivot `employee_user` waits for v2.** Reopen it as an access-model decision (pivot + membership semantics + per-tenant login lookup) when cross-branch staff are confirmed real and second-branch employee rows must carry accounts. Blockers that make a schema-only pivot pointless today: the scalar `users.tenant_id` backs every access gate, the SAML assertion carries exactly one company name per login, roles are global (Spatie teams disabled), and `Employee`'s `BelongsToTenant` scope means any global link table would need explicit tenant discipline on every read.

## Consequences

- Staff genuinely working two branches log in with two accounts — impossible under the same email, so their second-branch karyawan row stays account-less by design (surfaced, not hidden).
- The silent home-tenant flip at login is now observable in the log; the broken states it used to create invisibly (unwritable roles, 403 on the branch) remain broken until an admin fixes the link — by design, since only the assertion's company is trusted.
- No schema, gate, or notification consumer changes with this ADR — it documents and guards the status quo.

## References

- `.scratch/employee-page/research/ban-roleless-cross-tenant.md` §Q14 (riset 2026-10-22: options table, drift trace, pivot blast radius)
- `docs/adr/0008-superadmin-only-role-assignment.md` (roles stay global regardless of tenancy)
- `docs/adr/0009-whatsapp-gateway.md` (precedent: global tables read with explicit discipline)
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
