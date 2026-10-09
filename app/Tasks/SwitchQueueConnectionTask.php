<?php

namespace App\Tasks;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class SwitchQueueConnectionTask implements SwitchTenantTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::find($tenant->getTenantKey());

        if (! $tenantModel) {
            return;
        }

        // For single-database mode, we ensure the queue uses the same connection
        // and that the tenant context is available for job serialization
        $queueConfig = Config::get('queue.connections.database');
        $queueConfig['connection'] = Config::get('database.default');
        Config::set('queue.connections.database', $queueConfig);

        // Store tenant ID in queue config for job serialization
        Config::set('queue.tenant_id', $tenantModel->getTenantKey());

        // Resolve and re-register the queue manager with updated config
        // Only for non-sync queues
        if (app()->bound('queue') && Config::get('queue.default') !== 'sync') {
            // Forget resolved queue connections so they pick up new config
            if (method_exists(Queue::class, 'forgetResolved')) {
                Queue::forgetResolved();
            }
        }
    }

    public function forgetCurrent(): void
    {
        Config::set('queue.tenant_id', null);

        if (app()->bound('queue') && Config::get('queue.default') !== 'sync') {
            if (method_exists(Queue::class, 'forgetResolved')) {
                Queue::forgetResolved();
            }
        }
    }
}