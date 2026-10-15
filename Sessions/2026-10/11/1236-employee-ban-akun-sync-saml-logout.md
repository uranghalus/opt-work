# 12:36 — Employee ban/unban + kolom Akun + sync button fix + SAML logout POST binding

## Konteks
Sesi lanjutan setelah [[Sessions/2026-10/11/1145-tenant-url-code-switching]]. Lima commit (12:36–13:47): fitur blokir karyawan + perbaikan sync portal + dua bugfix dari penggunaan live (tombol sync gagal, logout SAML 405).

## Yang dikerjakan

### 1. Blokir/unblokir + status akun (`b793c9f` feat)
- `employees.banned_at`: blokir level record (superadmin only, POST `employees/{employee}/ban|unban`). Sync portal tidak menyentuhnya → ban bertahan lintas sync; akun terkait tidak diubah. Cross-cabang aman: karyawan di-resolve terhadap tenant dari route (resolusi manual, alasan sama dengan WorkOrderController — implicit binding jalan sebelum tenancy bootstrap).
- `employees.optigate_user_id` (unique per tenant): kunci idempoten stabil untuk `app:sync-employees`, yang sebelumnya menulis ke kolom tidak ada (`id_employee/id_department/id_position`) dan tak pernah persist.
- EmployeeResource: `has_account` (indikator akun terkait) + `is_banned`; object user null-safe; field mati `is_active` dibuang (tabel users tak punya kolom itu — badge lama selalu true).
- CONTEXT.md: glossary Akun & Blokir sesuai keputusan grilling.
- File: EmployeeController (+58), EmployeeResource, Employee, migrasi `2026_10_11_000001_add_ban_and_sync_identity_to_employees_table`, routes (+3), EmployeeBanTest (baru, 160 baris), SyncEmployeesTest (+91).

### 2. Revamp halaman index karyawan (`d1b45a6` feat)
- Kolom **Akun** menggantikan badge Status mati: hijau "Punya akun" / netral "Tanpa akun" dengan ikon (tidak pernah color-only). Badge **Diblokir** menempel di sel nama; baris terblokir redup.
- Aksi baris superadmin: Blokir (destructive, dialog konsekuensi mengikuti pola copy transisi Business Unit) dan Batalkan blokir (langsung). Tersembunyi untuk non-superadmin.
- Toolbar di-dedup: Sinkronkan + Tambah Karyawan hanya di heading band; sticky bar menyimpan search, Kolom, per-page. Empty state glyph UserX.
- File: `resources/js/pages/employees/index.tsx` (+164/−29 dalam commit ini; total 678 baris berubah sepanjang sesi), SyncEmployees.

### 3. Dokumentasi (`e075bd9` docs)
- Referensi tenancy dikoreksi ke spatie/laravel-multitenancy v4.

### 4. Fix tombol sync (`8facd09` fix)
Akar (reproduksi live): command mengandalkan hook creating BelongsToTenant untuk isi `tenant_id`, tapi kolom tidak fillable → mass assignment diam-diam membuangnya; di konteks CLI/tombol tidak ada `Tenant::current()` untuk backfill → insert crash "Field tenant_id does not have a default value". Test lama lolos karena jalan dengan tenant aktif.
- SyncEmployees resolve tenant eksplisit + forceFill `tenant_id` (firstOrNew + forceFill, landlord-side).
- Pull portal di-scope `company_id=<tenant.optigate_company_id>` (fail-closed jika tenant tak punya mapping) — sebelumnya `/api/users` tanpa filter mencampur karyawan semua company ke satu cabang.
- Http::fake pattern pakai query string (`*/api/users*`); 2 test regresi baru: pembuatan di konteks CLI tanpa tenant aktif, scoping company_id.

### 5. Fix logout SAML (`0a87b1d` fix)
Akar: OptiGate menjawab logout SP-initiated dengan auto-POST form (LogoutResponse bertanda tangan) ke SLS URL `saml/logout`, tapi route hanya menerima GET → `MethodNotAllowedHttpException` di setiap logout.
- `saml/sls` dan `saml/logout` menerima GET+POST (meniru dual-binding ACS); metadata SP kini advertise kedua binding SLS (diturunkan dari verb route).
- Kedua path di-exempt CSRF: POST lintas situs dari IdP tak bisa membawa token kita; proteksi = pesan bertanda tangan + cek issuer/destination/signature (alasan sama dengan `saml/acs`).
- File: bootstrap/app.php (+6), routes/web.php (+9), SamlTest (+54), FakeIdentityProvider (+82).

## Verifikasi
- Full suite (dicek ulang 13/10): **202 tests, 194 passed, 8 skipped, 0 failures, 761 assertions** (~28s).
- Working tree bersih; semua commit pushed ke origin/main.

## Commits
`b793c9f` feat(employees) ban/unban · `d1b45a6` feat(employees) index revamp · `e075bd9` docs tenancy refs · `8facd09` fix(employees) sync button · `0a87b1d` fix(saml) logout POST binding

## Terkait
- [[Sessions/2026-10/changelog]]
- [[Sessions/2026-10/11/1145-tenant-url-code-switching]] — sesi langsung sebelumnya.
- Follow-up terbuka: phpstan ~53 error pre-existing; issue `.scratch/optigate-sync/issues/01` (penyelarasan pola sync departments/employees — SyncEmployees sudah dapat scoping company_id + tenant eksplisit, tapi pagination/retry/deactivation dan sync departments belum); keputusan menunggu owner: redirect URL lama ber-ULID → ber-code.
