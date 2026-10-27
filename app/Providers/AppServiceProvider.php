<?php

namespace App\Providers;

use App\Console\Commands\SyncTenantData;
use App\Http\Responses\LoginResponse;
use App\Services\DailyWorkVerifierScope;
use App\Services\LoginLanding;
use App\Services\WorkOrderRequestService;
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

        $this->commands([
            SyncTenantData::class,
        ]);
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

        /**
         * D3 — detail WO hanya untuk pihak terlibat ATAU pemegang
         * work-order.read. Pihak terlibat:
         *   - Requester (requester_user_id)
         *   - HOD / Deputy HOD (manager_user_id) dept target
         *   - Pelaksana (user.employee_id ∈ work_orders.assigned_employees)
         * Guest (role guest, nol permission ADR-0006) terblokir otomatis.
         */
        Gate::define('work-order.view', function ($user, $workOrder): bool {
            // requester
            if ($workOrder->requester_user_id === $user->getKey()) {
                return true;
            }

            // R8: WO yang belum dirilis (masih di gerbang approval
            // department pengaju) hanya terlihat oleh requester dan rantai
            // approver department pengaju — department tujuan maupun
            // pemegang work-order.read biasa diblokir sampai approve.
            if ($workOrder->released_at === null) {
                return app(WorkOrderRequestService::class)->isApprover($user, $workOrder);
            }

            // HOD / deputy dept target
            $dept = $workOrder->targetDepartment;
            if ($dept !== null) {
                if ($dept->hod_user_id === $user->getKey()
                    || $dept->manager_user_id === $user->getKey()) {
                    return true;
                }
            }

            // pelaksana lintas department
            $employeeId = $user->employee_id;
            if ($employeeId !== null && is_array($workOrder->assigned_employees)) {
                if (in_array((string) $employeeId, $workOrder->assigned_employees, true)) {
                    return true;
                }
            }

            // fall back to broad read permission
            return $user->can('work-order.read');
        });

        /**
         * Verifikasi Daily Work (Task 3 plan .scratch/daily-work-
         * verification/plan.md) — detail + proxy attachment hanya untuk
         * owner ATAU scope verifikasi (admin tenant / hod+manager dept
         * karyawan / TL department sama, via DailyWorkVerifierScope).
         */
        Gate::define('daily-work.view', function ($user, $dailyWork): bool {
            if ($dailyWork->employee_id === $user->employee_id) {
                return true;
            }

            return app(DailyWorkVerifierScope::class)->canVerify($user, $dailyWork);
        });
    }
}
