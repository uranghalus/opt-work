---
status: accepted
---

# User↔role assignment on the Akun, reached via the linked Karyawan

RBAC v2 shipped role/permission CRUD (ADR-0004) but left assignment without a surface, so created roles were unusable. We add role assignment targeted at the `users` record of a linked employee, with a new `akun` permission resource, and fix the HOD dual-mechanism boundary while we are here.

## Decisions

1. **Employee-centric surface.** The Karyawan show page exposes the linked Akun's roles (self-contained heading-band action, following the SyncButton/Employees pattern). There is no dedicated Akun CRUD surface yet; a Karyawan without a linked Akun shows a read-only "belum ada akun" state. A standalone Akun management feature remains future work.
2. **New `akun` permission resource, not `employee.update`.** Per ADR-0004 rule 2 the resource carries the four CRUD verbs (`akun.read|create|update|delete`). `akun.update` gates writing roles; `akun.read` gates reading the roles section; `akun.create`/`akun.delete` are seeded forward-looking (allowlisted with a comment per ADR-0004 rule 3, "gated when Akun CRUD lands"). Reusing `employee.update` was rejected: editing person data and changing what logins may do are different privileges, and collapsing them creates a privilege-escalation path. Defaults are granted to `admin_tenant` via the per-release grant step (`app:grant-default-permissions`).
3. **Assignment touches only Spatie roles.** The write never touches `users.is_super_admin`, `users.is_hod`, or `departments.hod_user_id`/`manager_user_id`. The Role answers "what may this login do"; the department FK answers "which department does this person lead"; the `is_hod` flag is a notification-fallback marker. Conflating these three is the bug source this slice exists to avoid.
4. **`super_admin` is never assignable.** Server-side guard in the FormRequest (mirroring the delete/rename guards of ADR-0004 rule 6). The `is_super_admin` flag stays bootstrap/seeder-only — the UI can never grant the bypass, and `isSuperAdmin()` keeps a single source of truth.
5. **field_staff ⇄ karyawan pair invariant, enforced server-side.** Assigning `field_staff` auto-attaches `karyawan`; revoking `karyawan` from a `field_staff` holder is rejected with an explanatory error. This makes the glossary/ADR-0004 rule 5 invariant a database-backed guarantee instead of an admin convention.
6. **Shared `can` flags extended through the Gate.** `role.create/update/delete` and `permission.read/create/update/delete` join `role.read` in `HandleInertiaRequests`, always computed via `can()` (ADR-0004 rule 7), so Peran/Izin action buttons hide from users without the rights.

## Consequences

- Role changes take effect on the user's next request: the Spatie user↔role pivot is not cached, and the permission catalogue cache auto-flushes on role/permission edits. Inertia `auth.user.roles` and `can` flags recompute per request.
- New controllers, requests, and tests follow the existing naming conventions; permission literals must appear in the seeded catalogue and the honesty-test allowlist only where genuinely ungated.
- ADR-0004's stale `/settings/roles` route mention was corrected in passing (this session changed the hak-akses surface again, meeting the TODO's condition for updating it).

## References

- ADR-0004 — hak-akses surface, verb map, allowlist, guards this build extends.
- `.claude/plan/2026-10-14-work-plan.md` — the grilled plan this decision came from.
- CONTEXT.md — Akun, Role Assignment, System Role glossary entries.
