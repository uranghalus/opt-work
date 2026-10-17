---
status: accepted
---

# Guest role and the self-healed platform account (login provisioning)

RBAC v2 (ADR-0004/0005) made roles assignable but left a hole: every SSO
account that had not yet been linked to a Karyawan hit the role-aware login
gate and was denied (`no_role`) or landed with nothing — and the operational
diagnosis showed the platform account itself (its `super_admin` role was
present, but its Karyawan link was never created, so the Karyawan page
reported "Belum ada akun yang terhubung"). We close both at the login seam.

## Decisions

1. **`guest`, the tenth system role, carries zero permissions.** Assigned at
   first SSO login to an account that has no role and no linked Karyawan
   (`users.employee_id IS NULL`), inside one transaction, exactly once (the
   "no role" guard cannot hold twice). Zero permissions is the point: until
   the Super Admin grants real roles, the account can do nothing. `guest` is
   seeder-owned (`is_system`), so Hak Akses cannot delete or rename it.
   Rejected: a normal deletable role (fragile), a flag instead of a role
   (contradicts the role-centric model), a landing surface for guests (scope).
2. **Provisioning lives at `SamlController::loginUser()`**, before
   `Auth::login`, because the role-aware landing gate runs right after — a
   guest must exist before the gate sees the account. Password login is not
   provisioned: those accounts pre-exist; the seeder and the one-off command
   cover them.
3. **Exclusion of the platform account uses the configured email
   (`config('auth.super_admin_email')`, env-fed, never an email literal in
   code) plus `User::isSuperAdmin()` (flag OR role — ADR-0004 rule 7, the
   Spatie v8 super-admin pattern).** Role-based exclusion ALONE leaks: on the
   very first login the account has neither role nor flag yet. The platform
   check therefore runs first in the transaction and ends up _ensuring_ the
   super-admin state (role + `is_super_admin` flag) rather than merely
   skipping guest — the account self-heals on every login. The flag stays the
   source for platform surfaces (`TenantAccess`, `EnsurePlatformTenantAccess`)
   while the role drives `Gate::before`; both are set, as ADR-0004 already
   prescribed.
4. **The platform account links itself to the Karyawan with the same email**
   when unlinked (`withoutGlobalScope('tenant')` — provisioning runs before
   any tenant exists, and the platform account is not tenant-scoped). A
   conflicting link is never overwritten. Employee↔Akun linking for everyone
   else stays future work (Akun CRUD): auto-linking all accounts would make
   employees-linked users guest-ineligible and deny them at login.
5. **Guest removal happens through Role Assignment** (ADR-0005): the sync
   replaces the entire role set, so assigning real roles evicts `guest`
   (replace, not add). An account that is merely linked still keeps `guest`
   until roles are synced — deliberate: linking and role assignment are two
   separate acts.
6. **Server-side authority unchanged.** Shared `auth.can` flags stay Gate-
   computed, so a guest's flags are all false and every gated route denies.

## Consequences

- `RoleAndPermissionSeeder` gains `guest` (empty defaults, `is_system`) and
  links the platform account to its Karyawan; `RoleAndPermissionSeederTest`
  now expects ten system roles. `SyncAkunRolesRequest` whitelists `guest`
  (SYSTEM_ROLES minus super_admin) — a HOD may assign guest manually; that is
  harmless.
- `app:grant-super-admin-role` is the idempotent, additive one-off repair for
  existing databases (`--dry-run` previews; never deletes, never touches
  `tenant_id`). Test-covered, including idempotency.
- `SamlTest`'s first-login case now asserts the provisioned reality (guest +
  dashboard redirect); the old `no_role` denial survives as its own test for
  the unseeded-catalogue case.
- Super admin remains non-assignable through the UI (ADR-0005 guard), and
  zero-permission super_admin + `Gate::before` remain the Spatie-recommended
  super-admin shape (no permission list needed).

## References

- ADR-0004 (hak-akses surface, Gate::before, system roles), ADR-0005
  (role assignment replaces the role set).
- Spatie v8 "Defining a Super-Admin" — `Gate::before` returning `null` for
  non-super admins; the role itself carries no permissions.
- `.scratch/guest-role/spec.md` + issues 01–03; diagnosis recorded in the
  issue-03 file (role present, link missing).
