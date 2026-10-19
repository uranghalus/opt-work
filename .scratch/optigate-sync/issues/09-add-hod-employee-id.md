# 01: Add hod_employee_id column to departments table

**Status:** claimed
**Blocked by:** None
**Depends on:** None

## Why

Current HOD link uses `hod_user_id` → users bridge via employees.optigate_user_id. This is indirect and makes the relation unclear.

## Spec

### 1.1 Migration

```php
Schema::table('departments', function (Blueprint $table) {
    $table->foreignUuid('hod_employee_id')->nullable()->after('hod_user_id')
          ->constrained('employees')->nullOnDelete();
    $table->index('hod_employee_id');
});
```

### 1.2 Model update

Add to `Department.php`:
- `@property string|null $hod_employee_id`
- Add `#[Fillable(...)]` attribute
- Add relation: `public function hodEmployee(): BelongsTo`
- Keep existing `hod()` relation for backward compatibility

### 1.3 Backfill migration

Option A: Migrate existing `hod_user_id` → `hod_employee_id` via users.employee_id bridge
Option B: Leave both columns, use new one going forward

Recommendation: Option A - backfill from existing data where possible.

## Acceptance

- [ ] Migration runs without errors
- [ ] Model has both relations (hod for User, hodEmployee for Employee)
- [ ] Backfill migration moves existing data if any
