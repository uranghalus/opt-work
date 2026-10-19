# 02: Add HOD resolution pass to SyncDepartments

**Status:** pending
**Blocked by:** 01
**Depends on:** 01

## Why

Current SyncDepartments maps HOD via `hod_user_id` using the bridge Employee.optigate_user_id → User.employee_id. We need to also resolve HOD to the direct Employee FK.

## Spec

### 2.1 Update SyncDepartments command

Add second pass after upsert:
1. Build map: portal HOD UUID → local employee ID
2. Update departments: set `hod_employee_id` from map
3. Log unresolved HODs (where portal has HOD but local employee not found)

### 2.2 API response field

From doc example: `hod: { id: "uuid", name: "..." }`

Match key: `hod.id` (portal user UUID) → `employees.optigate_user_id` → `employees.id`

## Acceptance

- [ ] HOD resolved to employee on sync
- [ ] Unresolved HODs logged as warnings
- [ ] Existing hod_user_id still works (backward compatible)
