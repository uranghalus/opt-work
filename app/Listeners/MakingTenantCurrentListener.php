<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Events\MakingTenantCurrentEvent;

class MakingTenantCurrentListener
{
    /**
     * Handle the event.
     */
    public function handle(MakingTenantCurrentEvent $event): void
    {
        $tenant = $event->tenant;
        $user = auth()->user();

        Log::debug('Making tenant current', [
            'tenant_id' => $tenant->getTenantKey(),
            'tenant_name' => $tenant->name ?? $tenant->getTenantKey(),
            'user_id' => $user?->id,
            'user_email' => $user?->email,
        ]);

        // Clear any per-request cached data that might be tenant-specific
        // This runs BEFORE the tenant is switched
    }
}