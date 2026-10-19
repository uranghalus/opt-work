# 03: Pastikan relasi Department ↔ Employee双向正确

**Status:** pending
**Blocked by:** 02 (migration is_active harus dulu)
**Depends on:** 02

## Why

Audit menemukan:

1. FK sudah ada di migration, tapi tidak ada index composite `(tenant_id, department_id)` untuk query per-tenant yang cepat
2. Model relations sudah benar (`hasMany` / `belongsTo`), tapi N+1 risk di Department show page
3. Saat dept di-deactivate, employees yang指向它 tetap punya FK — perlu logging/warning

## Spec

### 3.1 Tambah composite index

Migration baru:

```php
$table->index(['tenant_id', 'department_id']);
```

### 3.2 Eager loading di show page

Controller `DepartmentController@show`:
```php
$department->load(['employees' => function ($q) {
    $q->with(['position', 'division']);
}]);
```

### 3.3 Warning di UI untuk employees di inactive dept

Di page show department, jika ada employee dengan `department->is_active == false`, tampilkan warning:
> ⚠️ Employee ini berada di department yang sudah tidak aktif. Hubungi admin untuk update.

## Acceptance

- [ ] Query `Employee::where('tenant_id', ...)->where('department_id', ...)` pakai composite index
- [ ] Department show page tidak N+1 untuk employees
- [ ] Employee di inactive dept menampilkan warning
