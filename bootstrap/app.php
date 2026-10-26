<?php

use App\Http\Middleware\EnsureEmployeeNotBanned;
use App\Http\Middleware\EnsurePlatformTenantAccess;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyTenant;
use App\Services\AccessDeniedAuditor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Companies change rarely; a nightly reconciliation keeps cabang data
        // aligned with the Optigate portal without hammering the API.
        $schedule->command('app:sync-tenants')->dailyAt('01:00');

        // Check overdue work orders and send staged escalations (H+3/5/6).
        $schedule->command('app:check-deadline-escalation')->dailyAt('09:00');

        // Sinkron status sesi WA per tenant dari WAHA (polling keluar —
        // pengganti webhook masuk; ADR-0009). Tiap menit: ringan (satu GET
        // /api/sessions) dan QR pairing cepat terlihat di UI.
        $schedule->command('app:waha-sync-sessions')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'tenant.access' => EnsureTenantAccess::class,
            'ensure.platform.tenant.access' => EnsurePlatformTenantAccess::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // There is no local login page; guests are sent straight to the SSO portal.
        // SSO parked (AUTH_SSO_ENABLED=false): guests land on the local Fortify
        // login screen instead.
        $middleware->redirectGuestsTo(fn () => route(
            config('auth.sso.enabled', true) ? 'saml.redirect' : 'login'
        ));

        // Authenticated users bouncing off guest-only routes (e.g. GET /login)
        // go to the role-aware root instead of the framework default — which
        // would crash generating route('dashboard') without its {tenant} param.
        $middleware->redirectUsersTo(fn () => route('home'));

        $middleware->web(append: [
            IdentifyTenant::class,
            // Q7: blokir per-request — menutup login baru DAN sesi aktif di
            // satu choke point (lihat docblock middleware).
            EnsureEmployeeNotBanned::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The SAML assertion consumer service receives signed POST assertions
        // from the identity provider, which cannot carry our CSRF token.
        // Relay state and SAML signature validation protect this endpoint.
        //
        // The single logout service is exempt for the same reason: an
        // identity provider answers an SP-initiated logout with an auto-POST
        // form carrying the signed LogoutResponse.
        $middleware->validateCsrfTokens(except: [
            'saml/acs',
            'saml/sls',
            'saml/logout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // FR-6.3: setiap akses yang ditolak (403) tercatat di audit_events —
        // render hook, bukan event gate, supaya probe can() tidak membanjiri
        // audit. Return null = lanjut ke render default (halaman/JSON 403).
        //
        // NB: dinotisasi sebagai HttpException 403, bukan AuthorizationException
        // — Handler::prepareException mengonversi AuthorizationException →
        // AccessDeniedHttpException SEBELUM render callbacks dijalankan
        // (Handler.php:777), dan spatie UnauthorizedException memang
        // HttpException(403). AuthorizationException asli tersedia via
        // getPrevious() untuk ekstraksi izin di AccessDeniedAuditor.
        $exceptions->render(
            function (HttpException $exception, Request $request) {
                if ($exception->getStatusCode() === 403) {
                    AccessDeniedAuditor::record($exception, $request);
                }

                return null;
            },
        );
    })->create();
