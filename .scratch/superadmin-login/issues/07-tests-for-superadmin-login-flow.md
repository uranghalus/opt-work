# 07: Tests for Superadmin Login Flow

**What to build:** Feature tests covering the superadmin login and tenant switching flow.

**Blocked by:** 01-06 (needs all implementation done)

**Status:** ready-for-agent

- [ ] Test: Superadmin logs in → redirected to `/admin`
- [ ] Test: Superadmin sees tenant list in `/admin`
- [ ] Test: Superadmin clicks "Switch" → lands on `/{tenant}/dashboard`
- [ ] Test: Tenant admin logs in → redirected to `/{their_tenant}/dashboard`
- [ ] Test: Superadmin without home tenant sees tenant selector