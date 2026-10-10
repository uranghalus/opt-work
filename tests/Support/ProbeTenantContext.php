<?php

namespace Tests\Support;

use App\Models\WorkOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\Multitenancy\Jobs\TenantAware;

/**
 * Records the tenancy context a queued job actually runs in.
 *
 * The bootstrapper's whole job is to restore the dispatching branch inside the
 * worker, and the failure mode when it is disabled is silent: the job still runs,
 * it just runs centrally. So the probe has to capture what it saw rather than
 * assert on side effects that look the same either way.
 */
class ProbeTenantContext implements ShouldQueue, TenantAware
{
    /** Tenant key seen while the job was executing. */
    public static ?string $seenTenantKey = null;

    /** Work order numbers visible to the job under the tenant scope, in order. */
    public static array $visibleWorkOrders = [];

    /** Work order numbers visible to the job with the tenant scope removed. */
    public static array $visibleWorkOrdersWithoutScope = [];

    public function handle(): void
    {
        static::$seenTenantKey = tenant()?->getTenantKey();
        static::$visibleWorkOrders = WorkOrder::query()->pluck('nomor_wo')->all();
        static::$visibleWorkOrdersWithoutScope = WorkOrder::withoutGlobalScope('tenant')->pluck('nomor_wo')->all();
    }

    /**
     * Clears what the previous run recorded so tests cannot read stale values.
     */
    public static function reset(): void
    {
        static::$seenTenantKey = null;
        static::$visibleWorkOrders = [];
        static::$visibleWorkOrdersWithoutScope = [];
    }
}
