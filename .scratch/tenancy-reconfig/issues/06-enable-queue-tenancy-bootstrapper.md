# 06: Enable queue tenancy bootstrapping (pre-existing misconfiguration)

**What to build:** `config/tenancy.php:44` disables `QueueTenancyBootstrapper` with the comment *"Note: phpredis is needed"*. **That reason is wrong.** phpredis is required by `RedisTenancyBootstrapper` (line 45, also disabled). `QueueTenancyBootstrapper` needs no phpredis at all — the upstream default config **enables** queue tenancy and comments out redis (`vendor/stancl/tenancy/assets/config.php:34-35`).

Consequence today: jobs dispatched from tenant context are not re-initialized in that tenant when processed. Given this project uses Reverb for realtime work-order notifications, a queued job may read or write tenant-scoped rows in central context — where `TenantScope::apply()` bails out early (`vendor/stancl/tenancy/src/Database/TenantScope.php:16-18`), meaning **unscoped** queries across all branches.

**Blocked by:** None. Independent of RBAC and the tenant CRUD.

**Status:** ready-for-agent

- [ ] `QueueTenancyBootstrapper` enabled; the incorrect phpredis comment is corrected
- [ ] A job dispatched from tenant context processes inside that tenant
- [ ] No cross-tenant data leakage through queued jobs

---

## Scope

**Config** — `config/tenancy.php:44`: uncomment `QueueTenancyBootstrapper::class`
and drop the wrong "phpredis is needed" note. Leave `RedisTenancyBootstrapper`
commented (it genuinely needs phpredis, which this project does not use).

Do **not** add `RedisTenancyBootstrapper` — there is no Redis cache store
configured; Reverb is for broadcasting, not caching.

## Verification

This one needs a real test, not inspection — the failure mode is silent:

1. A test that creates a work order in tenant `hq` with a queued notification,
   asserts the job runs with `tenancy()->initialized === true` and
   `tenant()->getTenantKey() === 'hq'`.
2. Assert the job cannot see another branch's rows.

**Check the queue connection first.** Docs advise against mixing central and
tenant queue connections. If the project uses the `sync` driver in local dev,
dispatched jobs run inline and this bug is invisible locally — confirm which
driver `.env` uses before concluding the change works. It will only manifest
under a real worker.

## Related, deferred

`redis.prefix_base` / `RedisTenancyBootstrapper` stay off. If per-branch cache
isolation is needed later, that is a separate ticket — `CacheTenancyBootstrapper`
is already enabled and tags cache entries per branch.