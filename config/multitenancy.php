<?php

// Force SQLite in-memory for testing environment
// Check $_SERVER directly because .env is loaded before phpunit.xml env vars
$isTesting = (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'testing')
    || (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing')
    || env('APP_ENV') === 'testing'
    || env('PHPUNIT_TEST') === 'true';

return [
    'tenant_finder' => App\TenantFinder::class,

    'tenant_artisan_search_fields' => [
        'id',
    ],

    'switch_tenant_tasks' => [
        \Spatie\Multitenancy\Tasks\PrefixCacheTask::class,
        // \Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask::class, // Disabled: single-database mode
        \Spatie\Multitenancy\Tasks\SwitchRouteCacheTask::class,
        \App\Tasks\SwitchQueueConnectionTask::class,
    ],

    'tenant_model' => App\Models\Tenant::class,

    'queues_are_tenant_aware_by_default' => true,

    'tenant_database_connection_name' => $isTesting ? 'sqlite' : env('TENANT_DB_CONNECTION', 'mysql'),

    'landlord_database_connection_name' => $isTesting ? 'sqlite' : env('LANDLORD_DB_CONNECTION', 'mysql'),

    'current_tenant_context_key' => 'tenantId',

    'current_tenant_container_key' => 'currentTenant',

    'shared_routes_cache' => false,

    'actions' => [
        'make_tenant_current_action' => Spatie\Multitenancy\Actions\MakeTenantCurrentAction::class,
        'forget_current_tenant_action' => Spatie\Multitenancy\Actions\ForgetCurrentTenantAction::class,
        'make_queue_tenant_aware_action' => Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction::class,
        'migrate_tenant' => Spatie\Multitenancy\Actions\MigrateTenantAction::class,
    ],

    'queueable_to_job' => [
        \Illuminate\Broadcasting\BroadcastEvent::class => 'mailable',
        \Illuminate\Mail\SendQueuedMailable::class => 'notification',
        \Illuminate\Queue\CallQueuedClosure::class => 'closure',
        \Spatie\Multitenancy\Jobs\TenantAware::class => 'class',
        \Illuminate\Notifications\SendQueuedNotifications::class => 'event',
    ],

    'tenant_aware_interface' => Spatie\Multitenancy\Jobs\TenantAware::class,

    'not_tenant_aware_interface' => Spatie\Multitenancy\Jobs\NotTenantAware::class,

    'tenant_aware_jobs' => [
        // ...
    ],

    'not_tenant_aware_jobs' => [
        // ...
    ],
];