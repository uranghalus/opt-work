# 04: Update Department page to show HOD employee

**Status:** pending
**Blocked by:** 02
**Depends on:** 02

## Why

UI should display the HOD employee name directly on the Department page.

## Spec

### 4.1 Controller update

Update `DepartmentController@index` to eager load `hodEmployee`:
```php
$departments = Department::query()
    ->with(['division', 'hodEmployee'])
    ->withCount(['employees', 'positions'])
    ->orderBy('kode_department')
    ->paginate(10)
    ->withQueryString();
```

### 4.2 Frontend update

Update `resources/js/pages/departments/index.tsx`:
- Add HOD column to table
- Display employee name or "—" if none

## Acceptance

- [ ] HOD name displays on Department index page
- [ ] No N+1 queries (uses eager loading)
