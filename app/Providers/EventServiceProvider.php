<?php

namespace App\Providers;

use App\Listeners\MakingTenantCurrentListener;
use App\Listeners\TenantSwitchListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Spatie\Multitenancy\Events\MakingTenantCurrentEvent;
use Spatie\Multitenancy\Events\TenantMadeCurrentEvent;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MakingTenantCurrentEvent::class => [
            MakingTenantCurrentListener::class,
        ],
        TenantMadeCurrentEvent::class => [
            TenantSwitchListener::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}