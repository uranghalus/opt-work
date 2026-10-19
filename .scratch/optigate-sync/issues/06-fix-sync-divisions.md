# 01: Fix SyncDivisions command — match Department pattern

**Status:** claimed
**Blocked by:** None
**Depends on:** None

## Why

Current `SyncDivisions` has multiple bugs:
1. Calls wrong endpoint: `/api/divisions` instead of `/api/companies/{company}/divisions`
2. Uses non-existent field `id_division` in updateOrCreate key
3. No pagination handling
4. No transaction wrap
5. No tenant iteration (hardcodes `Tenant::current()`)
6. No idempotency (no `optigate_division_id` column exists)

## Spec

### 1.1 Migration: add `optigate_division_id`

```php
$table->string('optigate_division_id', 36)->nullable()->after('nama_division');
$table->unique(['tenant_id', 'optigate_division_id']);
```

### 1.2 Rewrite `SyncDivisions.php`

Follow `SyncDepartments` pattern:
- Multi-tenant support via `--tenant` option or iterate all active tenants
- Endpoint: `GET /api/companies/{company}/divisions?paginate=true&per_page=100&page=N`
- Field mapping: `id` → `optigate_division_id`, `code` → `kode_division`, `name` → `nama_division`
- Pagination with `last_page` check
- `DB::transaction()` wrap
- Deactivate removed divisions: add `is_active` boolean (optional, discuss first)

### 1.3 Model update

Add `optigate_division_id` to `$fillable` and add getter if needed.

## Acceptance

- [ ] Command runs without error
- [ ] Idempotent: re-run doesn't duplicate
- [ ] Tenant-scoped correctly
- [ ] Tests pass
