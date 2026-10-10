<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\MasterData\DepartmentController;
use App\Http\Controllers\MasterData\DivisionController;
use App\Http\Controllers\MasterData\EmployeeController;
use App\Http\Controllers\MasterData\PositionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SamlController;
use App\Http\Controllers\Settings\TenantController;
use App\Http\Controllers\TenantSwitchController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('saml.redirect');
    }

    $user = auth()->user();

    if ($user->is_super_admin) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->tenant_id) {
        return redirect()->route('dashboard', ['tenant' => $user->tenant_id]);
    }

    // Superadmin without home tenant
    return redirect()->route('admin.dashboard');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
    });

    // Tenant switching routes (signed URLs for security)
    Route::get('tenant/switch/{tenant}', [TenantSwitchController::class, 'switch'])
        ->name('tenant.switch')
        ->middleware('signed');

    Route::get('tenant/switch-url/{tenant}', [TenantSwitchController::class, 'signedSwitchUrl'])
        ->name('tenant.switch-url');

    Route::post('tenant/stop-impersonating', [TenantSwitchController::class, 'stopImpersonating'])
        ->name('tenant.stop-impersonating');

    Route::get('tenant/impersonation-status', [TenantSwitchController::class, 'impersonationStatus'])
        ->name('tenant.impersonation-status');
});

// Central Admin Panel — outside {tenant} prefix, for superadmins/executives only
Route::middleware(['auth', 'ensure.platform.tenant.access'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('root');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Unit Bisnis (cabang) — platform level, super admin only.
// Tenants are synced from the Optigate API; there is no manual CRUD.
Route::middleware(['auth', 'ensure.platform.tenant.access'])->group(function () {
    Route::get('settings/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('settings/tenants/sync', [TenantController::class, 'sync'])->name('tenants.sync');
    Route::get('settings/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
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

    // Post-login landing when the assertion's company cannot be matched to
    // an active unit bisnis. The reason key is flashed by the ACS handler
    // and mapped to fixed copy here; internals stay in the log.
    Route::get('denied', function () {
        $messages = [
            'no_company_data' => 'Login berhasil, tetapi akun Anda tidak membawa data perusahaan dari portal. Hubungi administrator.',
            'no_match' => 'Perusahaan pada akun Anda belum terdaftar sebagai unit bisnis. Minta administrator menjalankan sinkronisasi.',
            'multiple_matches' => 'Data perusahaan Anda terdeteksi ganda pada sistem. Hubungi administrator untuk perbaikan.',
            'inactive' => 'Unit bisnis Anda sedang tidak aktif. Hubungi administrator untuk mengaktifkannya kembali.',
        ];

        $reason = session('saml.denial_reason');

        return inertia('saml/denied', [
            'message' => $messages[$reason] ?? 'Anda tidak dapat mengakses sistem saat ini. Hubungi administrator.',
        ]);
    })->name('saml.denied');

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

Route::middleware(['auth', 'tenant.access'])->prefix('{tenant}')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('work-orders', WorkOrderController::class)
        ->names('work-orders')
        ->parameters(['work-orders' => 'workOrder']);
    Route::get('work-orders/{workOrder}/attachments/{index}', [WorkOrderController::class, 'attachment'])->name('work-orders.attachments.show');

    Route::resource('divisions', DivisionController::class)->names('divisions');
    Route::post('divisions/sync', [DivisionController::class, 'sync'])->name('divisions.sync');

    Route::resource('employees', EmployeeController::class)->names('employees');
    Route::post('employees/sync', [EmployeeController::class, 'sync'])->name('employees.sync');

    Route::resource('departments', DepartmentController::class)->names('departments');
    Route::post('departments/sync', [DepartmentController::class, 'sync'])->name('departments.sync');

    Route::resource('positions', PositionController::class)->names('positions');
});

require __DIR__.'/settings.php';
