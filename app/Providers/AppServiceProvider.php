<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
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
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // URL generation is anchored to APP_URL, not the request host: the
        // SAML AuthnRequest Issuer and ACS must stay identical to what the
        // identity provider registered, regardless of which hostname a
        // visitor typed (localhost vs 127.0.0.1 vs a LAN IP).
        URL::forceRootUrl(config('app.url'));
        URL::forceScheme(str_starts_with((string) config('app.url'), 'https') ? 'https' : 'http');

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
