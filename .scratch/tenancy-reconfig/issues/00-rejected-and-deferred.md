# Rejected / Deferred

Decisions taken during the tenancy + RBAC grilling session (2026-10-06) that were
considered and explicitly **not** actioned. Kept so the reasoning is not
re-litigated, and so the re-entry point is obvious.

Research: `research-stancl-tenancy.md` · Seams audit: `tenant-crud-seams.md`
(contents returned by the explore agent; not yet written to disk)

## Settled decisions

| # | Decision | Why |
| --- | --- | --- |
| 1 | **RBAC removed in stages** — gates off now (issue 02), package/tables/seeder kept | Re-entry stays additive; avoids "is RBAC on?" ambiguity |
| 2 | **`tenant-operator` = global role** (may operate any branch) | Each branch has its own admin, already scoped by `users.tenant_id`. No per-branch join table needed today. |
| 3 | **`tenants.id` stays a slug** (`hq`, `plant-1`) | Legible URLs; branch codes are stable identifiers. Renames are rare. |
| 4 | **Dedicated columns** `name`/`code`/`is_active`, not `data` JSON | Docs: needed in a `WHERE`? Make it a column. Fixes the broken seeder permanently. |
| 5 | **Tenant CRUD at `/settings/tenants`** | Mirrors the existing central-CRUD precedent; re-gating later is one middleware. |
| 6 | **Tenant-level isolation is the authorization rule** | Any authenticated user may act within their own branch. Cross-branch needs `is_super_admin`. |
| 7 | **`/dashboard` stays central**; login redirects single-branch users to `/{tenant}/…` | Only a cross-branch page can host a switcher. |

## Deferred — per-branch permission sets

A "user may operate branches X, Y, Z" table. Requires Spatie `teams => true`
(adds `team_id` to `model_has_roles` + `role_has_permissions`) or a hand-rolled
join table.

`TenantAccess` (issue 02) is deliberately shaped so this lands without touching
callers. Revisit when RBAC returns and `tenant-operator` needs to be scoped
rather than global.

## Deferred — multi-database tenancy

Single-database mode is retained. The docs describe it as lower devops complexity
at the cost of manual scoping; that trade is still correct at this stage.
Revisit only if cross-branch aggregate queries become a real requirement.

## Deferred — `domains` table

`domains` is installed and entirely unused — path identification doesn't need it
(`domains` confirmed empty). No action. Drop the table only if a future move to
domain-based identification is ruled out for good.

## Deferred — `database/migrations/tenant` folder

`tenancy:install` always creates it, even in single-database mode. Files placed
there would not run under `php artisan migrate` but would still show up in
tooling. **Empty it** so nobody files a migration into a directory that silently
never executes.

## Known latent issue — `foreignUuid` vs `varchar`

`tenants.id` is `$table->string('id')` (varchar), but every tenant-scoped table
uses `foreignUuid('tenant_id')->constrained('tenants')`. SQLite tolerates the
mismatch; **MySQL may not**. Not fixed — it is a migration-wide concern and
unrelated to this work. Revisit before any non-SQLite environment.

## Known docs gaps (do not treat as guidance)

- **`config:cache` / `route:cache` in multi-tenant**: zero official
  documentation. Nothing in the docs repo or issue tracker. Verify locally with
  `route:cache` + a tenant smoke test before deploying; do not cite docs here.
- **`RefreshDatabase` + `:memory:`**: the docs state a contraindication only for
  "multi-database tenancy & the automatic mode". Concluding it is *safe* in
  single-database mode is a mechanistic inference, **not** a documented
  statement. Prove it with one feature test before relying on it.
- **`/docs/v3/testing`** is literally marked "TODO: Review". Do not treat it as
  comprehensive.
- `tenants:new` / `tenants:delete` **do not exist** in v3. Tenant CRUD is plain
  Eloquent. There is no artisan surface to build on.