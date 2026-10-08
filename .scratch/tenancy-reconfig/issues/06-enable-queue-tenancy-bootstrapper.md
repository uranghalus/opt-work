# 06: Enable queue tenancy bootstrapping (pre-existing misconfiguration)

**What to build:** `config/tenancy.php:44` disables `QueueTenancyBootstrapper` with the comment *"Note: phpredis is needed"*. **That reason is wrong.** phpredis is required by `RedisTenancyBootstrapper` (line 45, also disabled). `QueueTenancyBootstrapper` needs no phpredis at all — the upstream default config **enables** queue tenancy and comments out redis (`vendor/stancl/tenancy/assets/config.php:34-35`).

Consequence today: jobs dispatched from tenant context are not re-initialized in that tenant when processed. Given this project uses Reverb for realtime work-order notifications, a queued job may read or write tenant-scoped rows in central context — where `TenantScope::apply()` bails out early (`vendor/stancl/tenancy/src/Database/TenantScope.php:16-18`), meaning **unscoped** queries across all branches.

**Blocked by:** None. Independent of RBAC and the tenant CRUD.

**Status:** done

- [x] `QueueTenancyBootstrapper` enabled; the incorrect phpredis comment is corrected
- [x] A job dispatched from tenant context processes inside that tenant
- [x] No cross-tenant data leakage through queued jobs
- [x] Tests proven to fail when the bootstrapper is disabled (mutation-checked)

---

## Scope

**Config** — `config/tenancy.php:44`: uncomment `QueueTenancyBootstrapper::class`
and drop the wrong "phpredis is needed" note. Leave `RedisTenancyBootstrapper`
commented (it genuinely needs phpredis, which this project does not use).

Do **not** add `RedisTenancyBootstrapper` — there is no Redis cache store
configured; Reverb is for broadcasting, not caching.

## Verification

`tests/Feature/QueueTenancyBootstrapperTest.php` — 3 tests, all passing.

The suite pins `QUEUE_CONNECTION=sync`, and `.env` uses `database`, so **the driver matters
more than usual here**. Each test forces `config(['queue.default' => 'database'])`, calls
`Tenancy::end()` to imitate a fresh worker process, then runs the worker itself via
`artisan('queue:work --once')`.

Running the worker is the point, not a convenience: `JobProcessing` — the event
`QueueTenancyBootstrapper` listens on — is raised by `Illuminate\Queue\Worker`, **not** by
`$job->fire()`. An earlier version of this test called `fire()` directly and produced two
false results — one test failed for the wrong reason and another passed while the bug was
present, because the bootstrapper had simply never been given a chance to run.

**Mutation check.** With `QueueTenancyBootstrapper` commented out, two of the three tests
fail, and the failure is the bug itself:

```
- Array &0 [
- 0 => 'WO-HQ-1',
+ 1 => 'WO-PLANT-1',     <- another branch's row, visible from a queued job
- ]
```

So the tests have teeth rather than merely passing.

Assertions covered: the payload carries `tenant_id` for a branch dispatch and carries none
for a central dispatch; the job sees `tenancy()->initialized === true` and the right tenant
key; and a job dispatched from `hq` sees only `hq` work orders.

## Note: nothing is actually queued in production yet

`app/Notifications/WorkOrderCreated.php` uses the `Queueable` trait but does **not**
implement `ShouldQueue`, so it is delivered synchronously and never touches a worker. The
bootstrapper is correct and tested, but today it has no real traffic. Making notifications
queue-backed (adding `ShouldQueue`) would put it on the critical path — note that
`SerializesModels` would then re-fetch the `WorkOrder` inside the worker, which is exactly
the tenant-scoped read this bootstrapper exists to keep scoped.

## Related, deferred

`redis.prefix_base` / `RedisTenancyBootstrapper` stay off. If per-branch cache
isolation is needed later, that is a separate ticket — `CacheTenancyBootstrapper`
is already enabled and tags cache entries per branch.