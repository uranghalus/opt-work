# 04b: Handle Users Without Tenant (Edge Case)

**What to build:** Handle superadmin with no home tenant - redirect to `/admin` with tenant list.

**Blocked by:** 04 (same controller change)

**Status:** ready-for-agent

- [ ] If superadmin has no `tenant_id` and no operable tenants → show message in `/admin`
- [ ] If superadmin has operable tenants → show tenant selector in `/admin`