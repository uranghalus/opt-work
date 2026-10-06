<?php

use App\Http\Middleware\EnsurePlatformTenantAccess;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'ensure.tenant.access' => EnsureTenantAccess::class,
            'ensure.platform.tenant.access' => EnsurePlatformTenantAccess::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // There is no local login page; guests are sent straight to the SSO portal.
        $middleware->redirectGuestsTo(fn () => route('saml.redirect'));

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The SAML assertion consumer service receives signed POST assertions
        // from the identity provider, which cannot carry our CSRF token.
        // Relay state and SAML signature validation protect this endpoint.
        $middleware->validateCsrfTokens(except: [
            'saml/acs',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
