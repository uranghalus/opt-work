---
status: accepted
---

# Role grant/revoke is superadmin-only

ADR-0005 put role assignment behind `akun.update` and left the grant hierarchy as an open question (Q6). The employee page could therefore let any `akun.update` holder assign roles such as `admin_tenant`, `general_manager`, or a custom role with elevated permissions. The policy selected for the MVP is the strict one: only the platform superadmin grants and revokes roles.

## Decisions

1. **Only a platform superadmin may grant or revoke roles.** The check is `User::isSuperAdmin()` (the `is_super_admin` flag or the `super_admin` role), not `akun.update`.
2. **`akun.update` keeps its account-management meaning.** It still gates link/unlink and the tenant-scoped candidate list; it no longer exposes `assignable_roles` or authorizes `syncAkunRoles`.
3. **The UI is read-only for everyone else.** A viewer with `akun.read` still sees the account and its current roles, but the "Ubah Peran" action is hidden and the card states that only a superadmin can change roles.
4. **`super_admin` remains unassignable and sealed.** No caller, including a superadmin, can grant the bypass role through this surface or edit an account that already holds it.
5. **Custom roles remain assignable by a superadmin.** They are not given a rank because there is no delegated hierarchy in this phase. If role management is later delegated, it needs an explicit rank/capability model, not an implicit `akun.update` grant.
6. **Server-side enforcement is authoritative.** The FormRequest/UI are convenience layers; the controller returns 403 for every non-superadmin, including an `admin_tenant` that holds `akun.update`.

## Consequences

- `admin_tenant` can still link/unlink accounts, but cannot assign roles. A newly linked roleless account needs a superadmin before it can access the app.
- `docs/adr/0005-user-role-assignment.md` decision 2 is amended: `akun.update` no longer gates role writes.
- Existing role-assignment tests use a superadmin actor; new tests assert `admin_tenant` and permission-only actors receive 403 and see an empty `assignable_roles`.
- Future delegation requires a new decision (for example an explicit role rank or a dedicated `role.assign` capability) rather than widening `akun.update` again.

## References

- ADR-0005 — original `akun.update` role-assignment surface, amended here.
- ADR-0004 — RBAC vocabulary, superadmin bypass, and global roles.
- `.scratch/employee-page/research/employee-page-improvements.md` — Q6 gap and evidence.
- `app/Http/Controllers/MasterData/EmployeeController.php` — `syncAkunRoles` and `show`.
- `resources/js/pages/employees/show.tsx` — read-only role card for non-superadmins.
