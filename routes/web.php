<?php

use App\Http\Controllers\SamlController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
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
    // identity provider.
    Route::get('sls', [SamlController::class, 'sls'])->name('saml.sls');

    // Publishes this app's SAML metadata for identity provider configuration.
    Route::get('metadata', [SamlController::class, 'metadata'])->name('saml.metadata');
});

require __DIR__.'/settings.php';
