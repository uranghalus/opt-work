# Spec — Login provisioning: role `guest` + akun super admin self-healing

Tanggal: 2026-10-17. Sumber: Phase-1 grill (Q1–Q6 terjawab user) + diagnosis Phase 2
(DB probe). Pattern super-admin mengikuti Spatie v8 "Defining a Super-Admin"
(Gate::before; role super_admin tanpa permission; return null untuk non-superadmin).

## Goal

1. Setiap akun selain akun platform yang masuk lewat SSO dan **belum punya role
   apa pun** dan **belum tertaut ke karyawan** (`users.employee_id IS NULL`)
   otomatis diberi role `guest` — nol permission, sehingga tidak ada akses
   yang bocor sampai superadmin memberi role yang sesuai.
2. Akun platform (`superadmin@appdutamall.com`, dari env `SUPER_ADMIN_EMAIL`)
   **self-healing**: setiap kali masuk lewat auth, flag `is_super_admin` +
   role `super_admin` dipastikan ada (idempoten), dan akunnya ditautkan ke
   record karyawan dengan email sama bila belum tertaut (menghilangkan
   "Belum ada akun yang terhubung" di kartu Peran Akun).
3. Hanya superadmin yang dapat mengubah role akun lain (sudah ada: Hak Akses →
   Peran Akun, `work-order.assign`-style gate `akun.update`).

## Root cause (Phase 2, evidence-based)

- Role `super_admin` SUDAH ada di DB (pivot ok, guard `web`, tanpa team
  scoping). Yang hilang adalah **link `users.employee_id`**: tidak ada satu
  pun code path yang menautkan user ke employee — seeder tidak,
  `SamlController::loginUser()` juga tidak. Kartu "Peran Akun" resolve via
  `Employee::user()` (hasOne via `employee_id`) → NULL → UI menampilkan
  "Belum ada akun yang terhubung". 0 dari 127 employee tertaut.
- Hypotheses gugur: guard mismatch, team scoping, urutan seeder, role hilang.

## Keputusan (settled)

- **`guest` = system role ke-10** (seeder-owned, `is_system=1`, nol
  permission). `CONTEXT.md`: "sembilan" → "sepuluh" + glossary `Guest`.
  ADR-0006 baru (docs/ read-only untuk edit; file baru ok).
- **Permission `guest` = nol.** Konsekuensi yang diterima: user `guest`
  tidak bisa membuat WO sampai diberi role (glosarium: setiap authenticated
  user bisa jadi requester — untuk `guest` berlaku setelah diberi role).
- **Pemicu guest**: hanya di jalur SSO (`SamlController::loginUser()`),
  setelah user dibuat/ditemukan dan SEBELUM `Auth::login`; guard:
  `! $user->isSuperAdmin() && $user->employee_id === null &&
  ! $user->roles()->exists()` → `assignRole('guest')` dalam `DB::transaction`
  (idempoten: setelah ada role, syarat tidak berlaku lagi; `assignRole`
  Spatie tidak duplikasi pivot).
- **Exclusion guest = `isSuperAdmin()`** (flag ATAU role — ADR-0004 rule 7;
  tidak ada email di logic guest).
- **Auto-assign super admin = config `auth.super_admin_email`** (env
  `SUPER_ADMIN_EMAIL`; tidak ada email literal di application logic).
  Wajib, karena pada login pertama role/flag belum ada — exclusion berbasis
  role akan bocor. Saat provisioning platform: `Role::firstOrCreate(
  super_admin, web)` → `assignRole` → `forceFill(is_super_admin=true)` →
  link employee by email bila `employee_id` null (bila sudah tertaut ke
  employee lain: konflik, jangan sentuh).
- **Spatie v8 compliance**: `Gate::before` sudah benar (return `null` untuk
  non-superadmin, `AppServiceProvider::configureGate`); role `super_admin`
  tetap nol permission; flag `is_super_admin` tetap dipakai sebagai sumber
  platform access (`EnsurePlatformTenantAccess`, `TenantAccess`) — kenapa
  dua mekanisme (ADR-0004): flag untuk platform surface, role untuk Gate.
- **Linking umum employee↔akun TIDAK ikut** (tetap future work / Akun
  CRUD): auto-link by email untuk semua user akan membuat user tertaut tidak
  dapat `guest` lalu ditolak `no_role` — di luar scope dan berisiko.
- **Password login** (Fortify) tidak mem-provision: user di sana sudah
  ada sebelumnya; perbaikan lewat seeder idempoten + perintah one-off.
- **Revisi**: sync Peran Akun (ADR-0005) REPLACE seluruh set role → `guest`
  hilang otomatis saat role nyata disinkronkan (replace, bukan add).

## Verification (wajib hijau)

- `tests/Feature/Auth/GuestProvisioningTest.php`: first login unlinked →
  guest; second login → tak berubah; existing user ber-role → tak
  tersentuh; linked user (`employee_id` terisi) → no guest;
  superadmin first login → dapat `super_admin`, tidak dapat `guest`;
  guest ditolak di gated route (`/hak-kses` 403, review/assign WO 403).
- `tests/Feature/Rbac/SuperadminAccountTest.php`: setelah seeding akun
  platform punya flag + role + tertaut ke employee ber-email sama, dan
  bisa reach gated route (regresi symptom UI "Peran Akun").
- `RoleAndPermissionSeederTest`: sepuluh system role; `guest` is_system +
  nol permission; `super_admin` tetap nol.
- Full suite + pint + `types:check` + `vp check` (file tersentuh) + build.

## One-off command (untuk DB existing; tampilkan ke user sebelum jalan)

```
php artisan app:grant-super-admin-role --dry-run   # pratinjau
php artisan app:grant-super-admin-role             # tulis idempoten
```

Isi: pastikan role+flag (idempoten), link employee by email bila
`employee_id` null; **tidak** menyentuh `tenant_id`; `--dry-run` tidak
menulis.