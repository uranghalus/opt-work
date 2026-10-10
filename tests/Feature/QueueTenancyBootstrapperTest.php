<?php

use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProbeTenantContext;

beforeEach(function () {
    ProbeTenantContext::reset();

    // A real queue connection, otherwise the payload is never stored.
    config(['queue.default' => 'database']);

    // Create test tenants if they don't exist
    Tenant::firstOrCreate(['code' => 'hq'], [
        'optigate_company_id' => 1,
        'name' => 'Headquarters',
        'is_active' => true,
    ]);
    Tenant::firstOrCreate(['code' => 'plant-1'], [
        'optigate_company_id' => 2,
        'name' => 'Plant 1',
        'is_active' => true,
    ]);
});

it('job uses correct tenant context when retrieved from database', function () {
    $hq = initTenant('hq');

    // Verify we can dispatch a tenant-aware job
    dispatch(new ProbeTenantContext);

    // Spatie v4 stamps the tenant id into the payload's log context,
    // which is what the worker later restores the tenant from.
    $payload = json_decode(DB::table('jobs')->value('payload'), true);

    expect($payload['illuminate:log:context']['data']['tenantId'])->toContain($hq->getTenantKey());

    Tenant::forgetCurrent();
});

it('job runs with tenant isolation when needed', function () {
    $hq = initTenant('hq');
    WorkOrder::factory()->create(['nomor_wo' => 'WO-HQ-1']);

    initTenant('plant-1');
    WorkOrder::factory()->create(['nomor_wo' => 'WO-PLANT-1']);

    // Dispatched from hq, the job should run in hq context
    initTenant('hq');
    dispatch(new ProbeTenantContext);

    // Clear tenant to imitate fresh worker process
    Tenant::forgetCurrent();

    // Run the job - it should restore the tenant context
    $this->artisan('queue:work --once --queue=default --timeout=0 --sleep=0')
        ->assertExitCode(0);

    // The worker restored the dispatching tenant...
    expect(ProbeTenantContext::$seenTenantKey)->toBe($hq->getTenantKey());

    // ...so the tenant scope only exposes that tenant's work orders...
    expect(ProbeTenantContext::$visibleWorkOrders)->toBe(['WO-HQ-1']);

    // ...while the scope-free query proves both rows exist and the scope is the filter.
    expect(ProbeTenantContext::$visibleWorkOrdersWithoutScope)->toContain('WO-HQ-1', 'WO-PLANT-1');

    Tenant::forgetCurrent();
});
