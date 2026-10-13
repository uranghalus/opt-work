<?php

use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RoleController;
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

// Hak Akses — global role management (ADR-0004). Deliberately outside the
// {tenant} prefix: roles apply across all cabang. Every action is
// permission-gated in the controller, not on the route.
Route::middleware(['auth'])->prefix('settings/roles')->name('roles.')->group(function (): void {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::patch('{role}', [RoleController::class, 'update'])->name('update');
    Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
    Route::put('{role}/permissions', [RoleController::class, 'syncPermissions'])->name('permissions.sync');
});

Route::middleware(['auth'])->prefix('settings/permissions')->name('permissions.')->group(function (): void {
    Route::get('/', [PermissionController::class, 'index'])->name('index');
    Route::post('/', [PermissionController::class, 'store'])->name('store');
    Route::patch('{permission}', [PermissionController::class, 'update'])->name('update');
    Route::delete('{permission}', [PermissionController::class, 'destroy'])->name('destroy');
});
