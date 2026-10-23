<?php

use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\WhatsAppController;
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
});

// Hak Akses (roles) moved out of the settings section: see routes/hak-akses.php.

Route::middleware(['auth'])->prefix('settings/permissions')->name('permissions.')->group(function (): void {
    Route::get('/', [PermissionController::class, 'index'])->name('index');
    Route::post('/', [PermissionController::class, 'store'])->name('store');
    Route::patch('{permission}', [PermissionController::class, 'update'])->name('update');
    Route::delete('{permission}', [PermissionController::class, 'destroy'])->name('destroy');
});

// Gateway WhatsApp per tenant (ADR-0009 amendmen). Aksi kelola di-Gate di
// controller (whatsapp.update), halaman sendiri butuh whatsapp.read — pola
// sama dengan permissions di atas (tanpa route middleware permission).
Route::middleware(['auth'])->prefix('settings/whatsapp')->name('whatsapp.')->group(function (): void {
    Route::get('/', [WhatsAppController::class, 'index'])->name('index');
    Route::post('/', [WhatsAppController::class, 'store'])->name('store');
    Route::post('/start', [WhatsAppController::class, 'start'])->name('start');
    Route::post('/stop', [WhatsAppController::class, 'stop'])->name('stop');
    Route::get('/qr', [WhatsAppController::class, 'qr'])->name('qr');
});
