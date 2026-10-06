<?php

declare(strict_types=1);

use App\Http\Controllers\MasterData\DepartmentController;
use App\Http\Controllers\MasterData\DivisionController;
use App\Http\Controllers\MasterData\EmployeeController;
use App\Http\Controllers\MasterData\PositionController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
|--------------------------------------------------------------------------
| Tenant Routes (cabang aktif via path identification)
|--------------------------------------------------------------------------
|
| Routes prefixed with /{tenant} initialize tenancy from the path segment.
| Data isolation is enforced by the BelongsToTenant global scope and the
| ensure.tenant.access middleware. See ADR 0002.
|
| Authorization is tenant-level: any authenticated user may perform any action
| within their own branch. Per-role gating returns with spatie/laravel-permission
| — see .scratch/tenancy-reconfig/issues/02.
|
*/

Route::prefix('{tenant}')
    ->middleware([
        'web',
        InitializeTenancyByPath::class,
    ])
    ->group(function (): void {
        Route::middleware(['auth', 'verified', 'ensure.tenant.access'])->group(function (): void {
            Route::prefix('divisions')->name('divisions.')->group(function (): void {
                Route::get('/', [DivisionController::class, 'index'])->name('index');
                Route::get('create', [DivisionController::class, 'create'])->name('create');
                Route::post('/', [DivisionController::class, 'store'])->name('store');
                Route::get('{division}', [DivisionController::class, 'show'])->name('show');
                Route::get('{division}/edit', [DivisionController::class, 'edit'])->name('edit');
                Route::put('{division}', [DivisionController::class, 'update'])->name('update');
                Route::delete('{division}', [DivisionController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('departments')->name('departments.')->group(function (): void {
                Route::get('/', [DepartmentController::class, 'index'])->name('index');
                Route::get('create', [DepartmentController::class, 'create'])->name('create');
                Route::post('/', [DepartmentController::class, 'store'])->name('store');
                Route::get('{department}', [DepartmentController::class, 'show'])->name('show');
                Route::get('{department}/edit', [DepartmentController::class, 'edit'])->name('edit');
                Route::put('{department}', [DepartmentController::class, 'update'])->name('update');
                Route::delete('{department}', [DepartmentController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('positions')->name('positions.')->group(function (): void {
                Route::get('/', [PositionController::class, 'index'])->name('index');
                Route::get('create', [PositionController::class, 'create'])->name('create');
                Route::post('/', [PositionController::class, 'store'])->name('store');
                Route::get('{position}', [PositionController::class, 'show'])->name('show');
                Route::get('{position}/edit', [PositionController::class, 'edit'])->name('edit');
                Route::put('{position}', [PositionController::class, 'update'])->name('update');
                Route::delete('{position}', [PositionController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('employees')->name('employees.')->group(function (): void {
                Route::get('/', [EmployeeController::class, 'index'])->name('index');
                Route::get('create', [EmployeeController::class, 'create'])->name('create');
                Route::post('/', [EmployeeController::class, 'store'])->name('store');
                Route::get('{employee}', [EmployeeController::class, 'show'])->name('show');
                Route::get('{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
                Route::put('{employee}', [EmployeeController::class, 'update'])->name('update');
                Route::delete('{employee}', [EmployeeController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('work-orders')->name('work-orders.')->group(function (): void {
                Route::get('/', [WorkOrderController::class, 'index'])->name('index');
                Route::get('create', [WorkOrderController::class, 'create'])->name('create');
                Route::post('/', [WorkOrderController::class, 'store'])->name('store');
                Route::get('{workOrder}', [WorkOrderController::class, 'show'])->name('show');
                Route::get('{workOrder}/attachments/{index}', [WorkOrderController::class, 'attachment'])
                    ->where('index', '[0-9]+')
                    ->name('attachments.show');
            });
        });
    });
