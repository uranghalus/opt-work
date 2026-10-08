<?php

namespace Tests\Support;

use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Records the tenancy context a queued job actually runs in.
 *
 * The bootstrapper's whole job is to restore the dispatching branch inside the
 * worker, and the failure mode when it is disabled is silent: the job still runs,
 * it just runs centrally. So the probe has to capture what it saw rather than
 * assert on side effects that look the same either way.
 */
class ProbeTenantContext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tenant key seen while the job was executing. */
    public static ?string $seenTenantKey = null;

    /** Whether tenancy was bootstrapped while the job was executing. */
    public static ?bool $sawInitializedTenancy = null;

    /** Work order numbers visible to the job, in order. */
    public static array $visibleWorkOrders = [];

    public function handle(): void
    {
        static::$seenTenantKey = tenant()?->getTenantKey();
        static::$sawInitializedTenancy = tenancy()->initialized;
        static::$visibleWorkOrders = WorkOrder::query()->pluck('nomor_wo')->all();
    }

    /**
     * Clears what the previous run recorded so tests cannot read stale values.
     */
    public static function reset(): void
    {
        static::$seenTenantKey = null;
        static::$sawInitializedTenancy = null;
        static::$visibleWorkOrders = [];
    }
}
