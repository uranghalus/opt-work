---
status: accepted
---

# Department → Division hierarchy is portal-aligned

The local schema currently stores `departments.division_id`, but the Optigate portal stores `divisions.department_id` (nullable for direct divisions). A department can have many divisions, so the local direction cannot represent the portal hierarchy. This ADR settles the direction before the Employee page work touches schema or sync.

## Decisions

1. **Canonical relation: `divisions.department_id`.** A division belongs to at most one department; a department has many divisions. `departments.division_id` is deprecated and removed. Existing values are backfilled only where the reverse mapping is unambiguous; ambiguous rows stay unlinked and are logged, never guessed.
2. **Direct divisions are explicit.** `divisions.is_direct = true` means the division is directly under the GM; `department_id` is normally null. A null `department_id` with `is_direct = false` means "not mapped yet", not "direct".
3. **Portal is authoritative for the hierarchy.** Department and Division remain sync-only master data. Local create/edit/delete is not reintroduced. The sync writes `department_id` and `is_direct` from the portal payload.
4. **Employee division comes from the position.** `/api/users` exposes `department_id` and `position_id`, not a top-level `division_id`. The local employee division is derived from the local position's `division_id` (which is synced from the portal position).
5. **Sync order is dependency-ordered.** For one tenant: departments → divisions → positions → employees → departments again. The first department pass creates the rows employees need; the second resolves HOD now that employees exist. All child commands receive the tenant ULID, not the tenant code.
6. **The UI must distinguish "direct" from "unknown".** A division with `is_direct = true` renders "Direct (GM)"; a division with no department and `is_direct = false` renders an explicit "Belum dipetakan" state.

## Consequences

- `Department::division()` and `Division::departments()` are replaced by `Department::divisions()` and `Division::department()`.
- Department detail lists divisions; division detail shows the parent department or the direct state. Counts/columns that assumed one division per department are removed.
- `SyncDivisions` maps the portal `department_id` through `departments.optigate_department_id`; `SyncPositions` and `SyncEmployees` continue to map both sides.
- A migration backfills `divisions.department_id` from `departments.division_id` where exactly one department points at the division, then drops the old column. Rollback restores the old column best-effort.
- Tests must cover a normal division (parent department), a direct division (null parent + `is_direct`), ambiguous backfill, and the sync mapping.

## References

- `.scratch/employee-page/research/employee-page-improvements.md` — evidence and gap matrix.
- `optigate-complete-api-docs.md` — division payload `department_id`/`is_direct`, position payload, user payload.
- `.scratch/optigate-sync/issues/11-employee-division-mapping.md` — reopened decision.
- ADR-0004/0005 — master-data and `akun` permission boundaries.
