<?php

use App\Models\Tenant;
use App\TenantFinder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\CallQueuedClosure;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Actions\MigrateTenantAction;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;
use Spatie\Multitenancy\Tasks\PrefixCacheTask;
use Spatie\Multitenancy\Tasks\SwitchRouteCacheTask;
use Tests\Support\ProbeTenantContext;

// Force SQLite in-memory for testing environment
// Check $_SERVER directly because .env is loaded before phpunit.xml env vars
$isTesting = (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'testing')
    || (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing')
    || env('APP_ENV') === 'testing'
    || env('PHPUNIT_TEST') === 'true';

return [
    'tenant_finder' => TenantFinder::class,

    'tenant_artisan_search_fields' => [
        'id',
    ],

    'switch_tenant_tasks' => [
        PrefixCacheTask::class,
        // \Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask::class, // Disabled: single-database mode
        SwitchRouteCacheTask::class,
    ],

    'tenant_model' => Tenant::class,

    'queues_are_tenant_aware_by_default' => true,

    'tenant_database_connection_name' => $isTesting ? 'sqlite' : env('TENANT_DB_CONNECTION', 'mysql'),

    'landlord_database_connection_name' => $isTesting ? 'sqlite' : env('LANDLORD_DB_CONNECTION', 'mysql'),

    'current_tenant_context_key' => 'tenantId',

    'current_tenant_container_key' => 'currentTenant',

    'shared_routes_cache' => false,

    'actions' => [
        'make_tenant_current_action' => MakeTenantCurrentAction::class,
        'forget_current_tenant_action' => ForgetCurrentTenantAction::class,
        'make_queue_tenant_aware_action' => MakeQueueTenantAwareAction::class,
        'migrate_tenant' => MigrateTenantAction::class,
    ],

    'queueable_to_job' => [
        // Map a queueable wrapper to the property that holds the wrapped
        // job — spatie probes it to decide tenant awareness. These were
        // swapped before: every queued broadcast or mailable crashed the
        // worker probe on an undefined property. CallQueuedListener had
        // dropped out of this list at some point (vendor still ships it):
        // without it, queued listeners resolved to the wrapper class
        // itself and fell through to tenant-aware-by-default.
        BroadcastEvent::class => 'event',
        SendQueuedMailable::class => 'mailable',
        SendQueuedNotifications::class => 'notification',
        CallQueuedClosure::class => 'closure',
        CallQueuedListener::class => 'class',
    ],

    'tenant_aware_interface' => TenantAware::class,

    'not_tenant_aware_interface' => NotTenantAware::class,

    'scheduler_model' => null,

    'tenant_aware_jobs' => [
        ProbeTenantContext::class,
    ],

    'not_tenant_aware_jobs' => [
<<<<<<< HEAD
        // Defect pre-existing (terungkap saat slice WhatsApp 2026-10-21):
        // notify() yang berjalan TANPA tenant current (mis. command
        // app:check-deadline-escalation) melempar BroadcastNotificationCreated
        // ke queue tanpa tenantId di payload — spatie lalu melempar
        // CurrentTenantCouldNotBeDeterminedInTenantAwareJob dan
        // MENG-DELETE job-nya, sehingga saluran broadcast dibuang percuma.
        // Payload broadcast tidak membawa data tenant (hanya notifikasi
        // channel), jadi job ini memang non-tenant-aware. Entry ini harus
        // kelas UNWRAPPED-nya — memasukkan BroadcastEvent di sini adalah
        // no-op karena queueable_to_job sudah membongkarnya lebih dulu.
=======
        // Broadcast payload sudah dihitung saat dispatch dan broadcaster-nya
        // tidak menyentuh DB tenant saat worker run — jalur command (mis.
        // eskalasi deadline) notify tanpa tenant current dan tanpa entry ini
        // saluran broadcast-nya dibuang spatie (job di-delete + exception).
        // The awareness probe unwraps `BroadcastEvent` via `queueable_to_job`
        // BEFORE consulting this list, so the entry must name the INNER
        // event class — the wrapper name would be a silent no-op.
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
        BroadcastNotificationCreated::class,
    ],
];
