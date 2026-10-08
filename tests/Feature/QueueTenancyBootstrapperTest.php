<?php

use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;
use Tests\Support\ProbeTenantContext;

/**
 * Ticket 06 — `QueueTenancyBootstrapper` was disabled with the note "phpredis is
 * needed", which is wrong: that applies to `RedisTenancyBootstrapper`. Queue
 * tenancy needs no extension at all.
 *
 * With the bootstrapper off, a job dispatched inside a branch runs in central
 * context, where `TenantScope` bails out early and queries come back unscoped
 * across every branch. The failure is silent, so these tests process a real queued
 * job instead of trusting inspection.
 *
 * The suite pins `QUEUE_CONNECTION=sync`, under which the job runs inline inside
 * the still-initialized tenant and the bug is invisible. Every test here forces the
 * `database` driver, ends tenancy to imitate a fresh worker process, then runs the
 * worker itself — `JobProcessing`, where the bootstrapper re-initializes tenancy,
 * is raised by the worker and not by `$job->fire()`.
 */
beforeEach(function () {
    ProbeTenantContext::reset();

    // A real queue connection, otherwise the payload is never stored.
    config(['queue.default' => 'database']);
});

/**
 * Processes the single stored job the way `php artisan queue:work` does.
 */
function runStoredJob($test): void
{
    $test->artisan('queue:work --once --queue=default --timeout=0 --sleep=0')
        ->assertExitCode(0);
}

test('a job dispatched from a branch runs inside that branch', function () {
    $hq = initTenant('hq');
    WorkOrder::factory()->create(['nomor_wo' => 'WO-HQ-1']);

    dispatch(new ProbeTenantContext);

    $payload = json_decode(DB::table('jobs')->value('payload'), true);

    expect($payload)->toHaveKey('tenant_id', $hq->getTenantKey());

    // Imitate a worker: a brand new process has no tenancy initialized.
    Tenancy::end();
    expect(tenancy()->initialized)->toBeFalse();

    runStoredJob($this);

    expect(ProbeTenantContext::$sawInitializedTenancy)->toBeTrue()
        ->and(ProbeTenantContext::$seenTenantKey)->toBe('hq');

    Tenancy::end();
})->group('queue-tenancy');

test('a queued job cannot see another branch rows', function () {
    initTenant('hq');
    WorkOrder::factory()->create(['nomor_wo' => 'WO-HQ-1']);

    initTenant('plant-1');
    WorkOrder::factory()->create(['nomor_wo' => 'WO-PLANT-1']);

    // Dispatched from hq, so the plant row must stay invisible.
    initTenant('hq');
    dispatch(new ProbeTenantContext);

    Tenancy::end();
    runStoredJob($this);

    expect(ProbeTenantContext::$visibleWorkOrders)->toBe(['WO-HQ-1']);

    Tenancy::end();
})->group('queue-tenancy');

test('a job dispatched from central context is not stamped with a branch', function () {
    // Central dispatch is not tenant work, so the payload carries no branch and the
    // job must run without tenancy rather than inheriting whatever is current.
    Tenancy::end();

    dispatch(new ProbeTenantContext);

    expect(json_decode(DB::table('jobs')->value('payload'), true))
        ->not->toHaveKey('tenant_id');

    runStoredJob($this);

    expect(ProbeTenantContext::$sawInitializedTenancy)->toBeFalse()
        ->and(ProbeTenantContext::$seenTenantKey)->toBeNull();
})->group('queue-tenancy');
