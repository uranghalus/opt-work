# 05: Tests for HOD employee linking

**Status:** pending
**Blocked by:** 02
**Depends on:** 02

## Why

Need test coverage for HOD resolution to employee.

## Spec

Add to `tests/Feature/SyncDepartmentsTest.php`:

1. HOD is resolved to the right employee after sync
2. HOD empty or changed upstream updates or clears the link
3. Unresolved HOD gives null, a warning, and no crash
4. Relations resolve in both directions (Department.hodEmployee, Employee.headedDepartments)
5. No N+1 queries on Department index page

## Acceptance

- [ ] All new tests pass
- [ ] Full test suite green
