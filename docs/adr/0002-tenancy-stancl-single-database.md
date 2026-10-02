# Tenancy via stancl/tenancy (tenancyforlaravel.com), single-database mode

The user explicitly pointed at tenancyforlaravel.com as the reference for tenant setup (Round 3), superseding ADR-0001. OptiWorks uses the **stancl/tenancy** package in **single-database mode**: the `DatabaseTenancyBootstrapper` and database creation jobs are disabled, data stays in one database, and isolation is enforced by the package's `BelongsToTenant` trait (`tenant_id` column + automatic global scope) — which reproduces exactly the hand-rolled `TenantAware`/`TenantScope` pattern proven in the opti-work2 reference implementation.

Tenants (cabang) are identified by **path** (`/{tenant}/...` via `InitializeTenancyByPath`) — no wildcard DNS needed for an on-premise facility app, and a cabang switcher in the application chrome becomes plain navigation. `User` is deliberately a **global model**: it carries `tenant_id` as its home-cabang column but has no global scope, so authentication survives cabang switching (a scoped `User` would vanish from the auth lookup when the path changes). Access to another cabang's data is blocked by the `ensure.tenant.access` middleware.

## Considered Options

- **Manual tenant_id + global scope (ADR-0001)** — proven in opti-work2; superseded by the user's explicit tenancyforlaravel.com decision.
- **stancl/tenancy multi-database** — stronger isolation, but users-per-tenant-DB breaks the "cabang switcher in chrome" model and complicates auth.
- **stancl/tenancy single-database (chosen)** — package-native tenant infrastructure + the proven tenant_id scoping shape.

## Consequences

- Unique indexes on primary models must be tenant-scoped: `unique(['tenant_id', ...])`.
- `unique`/`exists` validation rules must be scoped manually with `where('tenant_id', tenant()?->getTenantKey())`.
- Master data (divisions, departments, positions, employees) is tenant-scoped; `tenants` (stancl) is the central cabang registry.
