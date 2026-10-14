<?php

use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

// Hak Akses — global role management (ADR-0004). A first-class top-level
// module, deliberately outside both the {tenant} prefix (roles apply across
// all cabang) and the settings section. Every action is permission-gated in
// the controller, not on the route.
Route::middleware(['auth'])->prefix('hak-akses')->name('roles.')->group(function (): void {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::patch('{role}', [RoleController::class, 'update'])->name('update');
    Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
    Route::put('{role}/permissions', [RoleController::class, 'syncPermissions'])->name('permissions.sync');
});
