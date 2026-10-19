# 02: Perbaiki sinkronisasi department dengan API Optigate

**Status:** claimed
**Blocked by:** None
**Depends on:** None

## Why

Audit对比 API doc dan code menemukan 3 bug:

1. **Pagination tidak robust** — loop bergantung pada `meta.total`, tapi tidak mengecek `meta.last_page`. Jika API mengembalikan total yang salah, sync bisa infinite loop atau berhenti terlalu dini.
2. **Tidak ada transaction wrap** — jika sync gagal di tengah batch (misal timeout), data bisa setengah write. Harus dibungkus `DB::transaction()`.
3. **Department yang dihapus upstream** — saat ini code memberi suffix `-sync-deleted-xxxxxx` pada `kode_department`. Lebih baik pakai field `is_active = false` agar histori employees masih tersambung.

## Spec

### 2.1 Tambah kolom `is_active` ke departments

Migration baru:

```php
$table->boolean('is_active')->default(true)->after('optigate_department_id');
$table->index('is_active');
```

Default `true` untuk backward compatibility.

### 2.2 Update `SyncDepartments`

- **Pagination**: tambahkan cek `last_page`:
  ```php
  $lastPage = $response->json('meta.last_page', 1);
  // break jika page > last_page
  ```
- **Transaction**: bungkus seluruh loop upsert per tenant:
  ```php
  DB::transaction(function () use ($tenant, $departments) {
      // upsert logic
  });
  ```
- **Deactivate semantics**: ganti logic "rename suffix" dengan:
  ```php
  $stale->each(fn($d) => $d->update(['is_active' => false]));
  ```

### 2.3 Field mapping

- `leadership_title`: **skip dulu** (tidak ada business logic yang bergantung). Tambah komentar `// OQ: leadership_title — apakah perlu disimpan?`
- `users_count`, `positions_count`: **skip** (read-only, tidak dipakai app)

## Acceptance

- [ ] Sync idempotent: re-run tidak duplikat
- [ ] Pagination aman jika `meta.total` null atau salah
- [ ] Gagal di tengah batch → rollback semua perubahan tenant tsb
- [ ] Dept yang dihapus upstream → `is_active = false`, bukan rename kode
