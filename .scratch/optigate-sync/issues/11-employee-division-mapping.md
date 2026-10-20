# 11: Reopen employee division mapping — portal-aligned hierarchy

**Status:** ready
**Blocked by:** None
**Depends on:** ADR-0007

## Why

The earlier answer ("division mapping is NOT needed") is superseded by the Employee page work. The local schema currently stores `departments.division_id`, while the portal stores `divisions.department_id` (nullable for direct divisions). A department can have many divisions, so the current local direction cannot represent the portal hierarchy and the employee org path stays empty.

Decision: adopt the portal-aligned direction. See `docs/adr/0007-department-division-direction.md`.

## Spec

- Add `divisions.department_id` (nullable FK to `departments`, `nullOnDelete`) and `divisions.is_direct` (boolean).
- Backfill from `departments.division_id` only where exactly one department points at the division; log/report ambiguous rows.
- Drop `departments.division_id` after backfill.
- Replace relations: `Department::divisions()` hasMany, `Division::department()` belongsTo.
- Update Department/Division index/show to present the new hierarchy:
  - Department: division count/list.
  - Division: parent department or "Direct (GM)"; null + `is_direct=false` = "Belum dipetakan".
- `SyncDivisions`: map portal `department_id` through `departments.optigate_department_id`, persist `is_direct`.
- `SyncTenantData`: pass tenant ULID, run departments → divisions → positions → employees → departments (HOD resolution).
- Update tests/factories for normal and direct divisions.

## Acceptance

- [ ] Migration backfills unambiguous links and drops `departments.division_id`.
- [ ] Department page shows multiple divisions; Division page shows parent/direct state.
- [ ] `app:sync-divisions` persists `department_id` and `is_direct`.
- [ ] Employee sync can populate department/position/division after the ordered chain.
- [ ] Tests cover normal division, direct division, ambiguous backfill, and sync mapping.
- [ ] Full suite green.
