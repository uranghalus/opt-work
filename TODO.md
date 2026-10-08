# TODO - Lanjutan Multitenancy & Master Data

## Konteks Singkat
Frontend build sudah hijau. Rute `{tenant}` dan Wayfinder sudah ter-generate. Helper `tenant()` dan `Tenant::getTenantKey()` sudah ada.
Blocker sekarang adalah schema `tenants` ganda → FK constraint gagal di test MasterData.

## Prioritas Tinggi

### 1. Resolve duplicate tenants table migrations conflict
**Masalah:** Ada dua migrasi membuat tabel `tenants` dengan skema berbeda pada koneksi yang sama.
- `database/migrations/2019_09_15_000010_create_tenants_table.php` → `id string primary` sesuai Spatie v4
- `database/migrations/landlord/2026_10_08_062157_create_landlord_tenants_table.php` → `id bigIncrements`, `domain`, `database`

Ini menyebabkan FK `divisions.tenant_id → tenants.id` gagal di SQLite in-memory test.

**File yang perlu diperiksa/diubah:**
- `config/multitenancy.php` → cek `tenant_database_connection_name` dan `landlord_database_connection_name`
- `.env` / `.env.testing` → cek koneksi DB testing
- `database/migrations/landlord/2026_10_08_062157_create_landlord_tenants_table.php` → hapus/rename/deskop jika tidak dipakai
- `database/migrations/2019_09_15_000010_create_tenants_table.php` → schema referensi yang benar

**Langkah:**
1. Konfirmasi apakah landlord & tenant pakai DB terpisah atau single DB.
2. Jika single DB, hapus atau komentari migrasi landlord yang membuat `tenants` ulang.
3. Jalankan `php artisan migrate:fresh --env=testing` dan cek schema `tenants`.

### 2. Konfirmasi koneksi DB untuk testing
**File:**
- `config/database.php`
- `.env.testing`
- `phpunit.xml`

**Langkah:**
Pastikan koneksi testing menggunakan SQLite in-memory yang sama untuk landlord & tenant, atau pisahkan sesuai config multitenancy.

### 3. Pastikan DivisionController set tenant_id dengan benar
**File:**
- `app/Http\Controllers/MasterData/DivisionController.php` → method `store`
- `app/Models/Division.php` → trait `BelongsToTenant`

Saat ini `Division::create($validated)` tidak menyertakan `tenant_id`. Trait `BelongsToTenant` seharusnya auto-set dari tenant current, tapi verifikasi:
- Apakah `IdentifyTenant` middleware benar-benar membuat tenant current sebelum controller dijalankan?
- Jika perlu, tambahkan `tenant_id` secara eksplisit:
  ```php
  $data = $validated;
  $data['tenant_id'] = tenant()?->getKey();
  Division::create($data);
  ```

### 4. Jalankan dan perbaiki test MasterData
**File test:**
- `tests/Feature/MasterDataTest.php` → `it('creates a division scoped to the active cabang')`
- `tests/Pest.php` → helper `initTenant`, `createUser`, `makeSuperAdmin`

**Langkah:**
```bash
php artisan test tests/Feature/MasterDataTest.php
```
Perbaiki FK error sampai test hijau.

## Prioritas Menengah
### 5. Konsistensi penggunaan getTenantKey
**File yang memakai `tenant()?->getTenantKey()`:**
- `app/Http\Controllers/MasterData/DivisionController.php`
- Controller master data lain: Department, Position, Employee

Pastikan semua controller menggunakan `tenant()?->getKey()` atau `getTenantKey()` secara konsisten.

## Catatan
- Helper `tenant()` sudah ada di `app/Helpers/tenant_helper.php` dan ter-autoload di `composer.json`
- `App\Models\Tenant::getTenantKey()` sudah ada
- Build frontend sudah OK, BranchSwitcher sudah real

Mulai dari poin 1. Jika ragu soal landlord vs tenant DB, tanyakan sebelum mengubah migrasi.
