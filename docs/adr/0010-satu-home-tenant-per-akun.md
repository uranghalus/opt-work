---
status: accepted
---

# One home tenant per account; the `employee_user` pivot is deferred (Q14)

The same person can exist as karyawan rows in several branches (`employees.email` is nullable and not unique), but `users.email` is globally unique and `users.employee_id` carries a single unique link — so one account can ever point at one karyawan. Q14 asked whether cross-branch staff should be modelled with an `employee_user` pivot now.

## Decisions

1. **One home tenant per account — final for the MVP.** `users.tenant_id` stays the single scalar home tenant; `TenantAccess` keeps granting non-superadmins exactly that one branch.
2. **Last-assertion-wins is retained.** Every SAML login persists the asserted company as the home tenant (`SamlController::acs`); the portal company attribute, not the employee link, drives it.
3. **Drift is logged, never auto-repaired.** When a login flips the home tenant away from the linked karyawan's cabang, the login succeeds and the link survives; the drift guard (`SamlController::warnHomeTenantDrift`) records a `Log::warning` so the downstream breakage (role-write and `tenant.access` 403s) is diagnosable instead of silent. Breaking or re-pointing links is never done automatically.
4. **The pivot `employee_user` waits for v2.** Reopen it as an access-model decision (pivot + membership semantics + per-tenant login lookup) when cross-branch staff are confirmed real and second-branch employee rows must carry accounts. Blockers that make a schema-only pivot pointless today: the scalar `users.tenant_id` backs every access gate, the SAML assertion carries exactly one company name per login, roles are global (Spatie teams disabled), and `Employee`'s `BelongsToTenant` scope means any global link table would need explicit tenant discipline on every read.

## Consequences

- Staff genuinely working two branches log in with two accounts — impossible under the same email, so their second-branch karyawan row stays account-less by design (surfaced, not hidden).
- The silent home-tenant flip at login is now observable in the log; the broken states it used to create invisibly (unwritable roles, 403 on the branch) remain broken until an admin fixes the link — by design, since only the assertion's company is trusted.
- No schema, gate, or notification consumer changes with this ADR — it documents and guards the status quo.

## References

- `.scratch/employee-page/research/ban-roleless-cross-tenant.md` §Q14 (riset 2026-10-22: options table, drift trace, pivot blast radius)
- `docs/adr/0008-superadmin-only-role-assignment.md` (roles stay global regardless of tenancy)
- `docs/adr/0009-whatsapp-gateway.md` (precedent: global tables read with explicit discipline)
