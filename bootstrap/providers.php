<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TenancyServiceProvider;
use SocialiteProviders\Manager\ServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    TenancyServiceProvider::class,
    ServiceProvider::class,
];
