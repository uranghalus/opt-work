---
status: superseded by ADR-0002
---

# Tenant scoping instead of tenancyforlaravel

Multi-cabang isolation in OptiWorks uses tenant scoping in a single MySQL database — a `tenant_id` column on relevant tables plus Laravel model global scopes and an `ensure.tenant` middleware — following the proven opti-work2 reference implementation. The `tenancyforlaravel` package named in the PRD draft was rejected: the reference implementation already proves the scoping approach, and package-based tenancy would force reworking a schema-wide convention that already works.

## Considered Options

- **tenancyforlaravel (multi-database / domain-based)** — named in the PRD draft; rejected as unnecessary rework with no proven gain for this scale.
- **Single database with tenant scoping (chosen)** — matches opti-work2; simpler maintenance, isolation enforced via global scopes and middleware.
