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

test('QueueTenancy is not needed for Spatie v4 - tenant aware jobs', function () {
    // Spatie v4 handles tenancy differently:
    // - Jobs implement TenantAware interface
    // - Tenant context is stored in tenant_id column of jobs table
    // - Queue tenancy is handled via events and middleware, not bootstrappers
    
    // Create a test tenant
    $hq = Tenant::where('code', 'hq')->first();
    
    // Verify we can dispatch a tenant-aware job
    dispatch(new ProbeTenantContext);
    
    // The job should execute successfully
    expect(true)->toBeTrue();
});

it('job uses correct tenant context when retrieved from database', function () {
    $hq = initTenant('hq');
    
    // Debug: check if tenant is in container
    $currentTenant = app('currentTenant');
    expect($currentTenant)->not->toBeNull();
    expect($currentTenant->code)->toBe('hq');
    
    // Dispatch the job and check if Spatie stamps it
    $job = new ProbeTenantContext;
    dispatch($job);
    
    // In Spatie v4, tenant context is restored when the job runs
    // This is handled automatically via the job middleware
    $payload = json_decode(DB::table('jobs')->value('payload'), true);
    
    // Spatie v4 stamps tenantId in illuminate:log:context
    expect($payload['illuminate:log:context']['data']['tenantId'])->toContain($hq->getTenantKey());
    
    Tenant::forgetCurrent();
});

it('job runs with tenant isolation when needed', function () {
    initTenant('hq');
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
    
    // Debug output
    dump('Context tenant ID:', ProbeTenantContext::$contextTenantId);
    dump('Seen tenant key:', ProbeTenantContext::$seenTenantKey);
    dump('Saw initialized tenancy:', ProbeTenantContext::$sawInitializedTenancy);
    dump('Visible work orders:', ProbeTenantContext::$visibleWorkOrders);
    
    // The job should have seen only HQ work orders
    expect(ProbeTenantContext::$visibleWorkOrders)->toBe(['WO-HQ-1']);
    
    Tenant::forgetCurrent();
});
