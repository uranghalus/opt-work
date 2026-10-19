# 03: Tests for Division sync

**Status:** pending
**Blocked by:** 01
**Depends on:** 01

## Why

No test coverage exists for Division sync.

## Spec

Create `tests/Feature/SyncDivisionsTest.php` with:

1. Creates divisions from portal payload keyed by portal id
2. Scopes request to tenant's optigate company
3. Is idempotent: re-run updates, not duplicates
4. Handles pagination correctly
5. Persists last_department_synced_at (or add last_division_synced_at)
6. Failure leaves data unchanged

## Acceptance

- [ ] All tests pass
- [ ] Follows same pattern as SyncDepartmentsTest
