<?php

use App\Models\Tenant;
use App\TenantFinder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Mail\SendQueuedMailable;
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
        // worker probe on an undefined property.
        BroadcastEvent::class => 'event',
        SendQueuedMailable::class => 'mailable',
        SendQueuedNotifications::class => 'notification',
        CallQueuedClosure::class => 'closure',
    ],

    'tenant_aware_interface' => TenantAware::class,

    'not_tenant_aware_interface' => NotTenantAware::class,

    'scheduler_model' => null,

    'tenant_aware_jobs' => [
        ProbeTenantContext::class,
    ],

    'not_tenant_aware_jobs' => [],
];
