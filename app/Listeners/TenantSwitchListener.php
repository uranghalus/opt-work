<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Events\TenantMadeCurrentEvent;

class TenantSwitchListener
{
    /**
     * Handle the event.
     */
    public function handle(TenantMadeCurrentEvent $event): void
    {
        $tenant = $event->tenant;
        $user = auth()->user();

        Log::info('Tenant made current', [
            'tenant_id' => $tenant->getTenantKey(),
            'tenant_name' => $tenant->name ?? $tenant->getTenantKey(),
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'is_super_admin' => $user?->is_super_admin ?? false,
            'is_impersonating' => session('is_impersonating', false),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);

        // Clear per-tenant singleton instances (config, permissions, etc.)
        // This ensures fresh data when switching tenants
        if (app()->bound('permission.cache')) {
            app('permission.cache')->forget('permissions');
        }
    }
}