<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    // Cabang (tenant) administration — platform level, deliberately outside the
    // {tenant} prefix. Tenants are synced from Optigate API (Companies).
    // Only index/show are available; create/update/delete disabled.
    Route::prefix('settings/tenants')->name('tenants.')->middleware('ensure.platform.tenant.access')->group(function (): void {
        Route::get('/', [TenantController::class, 'index'])->name('index');
        Route::get('{tenant}', [TenantController::class, 'show'])->name('show');
    });
});
