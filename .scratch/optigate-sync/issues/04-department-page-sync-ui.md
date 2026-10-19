# 04: Perbaiki UI Department page untuk sync & last-synced info

**Status:** pending
**Blocked by:** 02 (karena butuh kolom is_active untuk logic)
**Depends on:** 02

## Why

UI saat ini:
- Tombol sync tidak ada permission check di frontend
- Tidak ada info last synced
- Flash message terlalu generic

## Spec

### 4.1 Persist last_synced_at

Setelah sync sukses, simpan timestamp ke `app_settings` atau column baru di `tenants` table:
```php
$tenant->update(['last_department_synced_at' => now()]);
```

### 4.2 Pass last_synced ke frontend

Di `DepartmentController@index`:
```php
return Inertia::render('departments/index', [
    'departments' => $departments,
    'last_synced_at' => $tenant?->last_department_synced_at,
]);
```

### 4.3 Tampilkan di UI

Di `resources/js/pages/departments/index.tsx`:
- Tambah section bawah button sync: "Last synced: {formatted date}"
- Jika null: "Belum pernah disinkronkan"
- Jika sync error: tetap tampilkan last synced sebelumnya (jangan kosongkan)

### 4.4 Permission gate (opsional)

Tambahkan prop `canSync` dari controller berdasarkan Gate check yang sama.

## Acceptance

- [ ] Last synced timestamp muncul di page setelah sync sukses
- [ ] Timestamp updated setiap sync sukses
- [ ] UI menampilkan fallback jika belum pernah sync
