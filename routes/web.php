<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SamlController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('saml.redirect')
)->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
    });
});
Route::prefix('saml')->group(function () {
    // SP-initiated SSO: send the user to the identity provider.
    Route::get('redirect', [SamlController::class, 'redirect'])->name('saml.redirect');

    // Assertion consumer service: receives SAML responses over both HTTP-POST
    // and HTTP-Redirect bindings. CSRF validation is skipped on POST because
    // signed SAML messages cannot carry our CSRF token; SP-initiated
    // responses are protected by the relay state check, and IdP-initiated
    // ones by the signature/issuer/timestamp validation of the SAML provider.
    Route::match(['get', 'post'], 'acs', [SamlController::class, 'acs'])->name('saml.acs');

    // Single logout service: receives unsolicited logout requests from the
    // identity provider. The portal registers this as 'saml/logout' so we
    // expose that path as an alias alongside the canonical 'saml/sls'.
    Route::get('sls', [SamlController::class, 'sls'])->name('saml.sls');
    Route::get('logout', [SamlController::class, 'sls'])->name('saml.logout');

    // SP-initiated single logout: ends the local session, then forwards a
    // LogoutRequest to the identity provider's SLO endpoint.
    Route::post('slo', [SamlController::class, 'initiateLogout'])->name('saml.slo');

    // Publishes this app's SAML metadata for identity provider configuration.
    Route::get('metadata', [SamlController::class, 'metadata'])->name('saml.metadata');
});

require __DIR__.'/settings.php';
