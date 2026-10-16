<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Services\LoginLanding;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Saml2\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Password login lands through the same role-aware rule as SAML
        // (LoginLanding) instead of the dead config('fortify.home') route.
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGate();
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

    /**
     * Super admin bypasses every gate (ADR-0004). The single source of truth
     * is User::isSuperAdmin() — the is_super_admin flag or the seeded
     * super_admin role. Returning null lets Spatie's permission check decide
     * for everyone else.
     */
    protected function configureGate(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            return $user !== null && $user->isSuperAdmin() ? true : null;
        });
    }
}
