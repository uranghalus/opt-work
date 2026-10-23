<?php

use App\Models\Tenant;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\BroadcastProbeNotification;

beforeEach(function () {
    // A real queue connection, otherwise the payload is never stored.
    config(['queue.default' => 'database']);
});

it('processes broadcast notification jobs dispatched without a tenant current', function () {
    // Defect pre-existing (terungkap saat slice WhatsApp 2026-10-21): command
    // eskalasi memanggil notify() tanpa tenant current — BroadcastNotificationCreated
    // mendarat di queue TANPA tenantId, spatie menolak job tenant-aware itu dan
    // MENG-DELETE-nya, sehingga saluran broadcast dibuang percuma di produksi.
    // Fix: kelas unwrapped-nya masuk multitenancy.not_tenant_aware_jobs.
    Tenant::forgetCurrent();

    $user = createUser();
    $user->notify(new BroadcastProbeNotification);

    // The wrapper job lands on the queue with NO tenant stamp in the payload —
    // without this the test would not reproduce the defect at all.
    $payload = json_decode(DB::table('jobs')->value('payload'), true);

    expect($payload['illuminate:log:context']['data']['tenantId'] ?? null)->toBeNull();

    // Before the fix, spatie threw CurrentTenantCouldNotBeDeterminedInTenantAwareJob
    // from the JobProcessing listener (→ JobExceptionOccurred) and deleted the job.
    $exceptionOccurred = null;

    Event::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event) use (&$exceptionOccurred): void {
        $exceptionOccurred = $event->exception;
    });

    $this->artisan('queue:work --once --queue=default --timeout=0 --sleep=0')
        ->assertExitCode(0);

    // The job ran to completion: no tenant-awareness exception, and nothing
    // left on the queue (processed, not orphaned).
    expect($exceptionOccurred)->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('still stamps the tenant on jobs dispatched with a tenant current', function () {
    // The fix must not weaken tenancy for ordinary jobs: a notification sent
    // WITH a tenant current still carries the tenant stamp in the payload.
    $hq = createTestTenant(['code' => 'hq', 'name' => 'Head Office']);

    $user = createUser();
    $user->notify(new BroadcastProbeNotification);

    $payload = json_decode(DB::table('jobs')->value('payload'), true);

    expect($payload['illuminate:log:context']['data']['tenantId'])->toContain($hq->getTenantKey());

    Tenant::forgetCurrent();
});
