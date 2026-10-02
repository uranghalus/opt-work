<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Saml2\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSaml2();
        $this->configureSuperAdmin();
    }

    /**
     * Bypass all permission checks for the Super Admin role.
     *
     * See https://spatie.be/docs/laravel-permission/v8/basic-usage/super-admin.
     */
    protected function configureSuperAdmin(): void
    {
        Gate::before(fn ($user): ?bool => $user->hasRole('super_admin') ? true : null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }

    protected function configureSaml2(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('saml2', Provider::class);
        });
    }
}
