<?php

declare(strict_types=1);

use App\Http\Controllers\MasterData\DepartmentController;
use App\Http\Controllers\MasterData\DivisionController;
use App\Http\Controllers\MasterData\EmployeeController;
use App\Http\Controllers\MasterData\PositionController;
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
*/

Route::prefix('{tenant}')
    ->middleware([
        'web',
        InitializeTenancyByPath::class,
    ])
    ->group(function (): void {
        Route::middleware(['auth', 'verified', 'ensure.tenant.access'])->group(function (): void {
            Route::prefix('divisions')->name('divisions.')->middleware('permission:division.read')->group(function (): void {
                Route::get('/', [DivisionController::class, 'index'])->name('index');
                Route::get('create', [DivisionController::class, 'create'])->middleware('permission:division.create')->name('create');
                Route::post('/', [DivisionController::class, 'store'])->middleware('permission:division.create')->name('store');
                Route::get('{division}', [DivisionController::class, 'show'])->name('show');
                Route::get('{division}/edit', [DivisionController::class, 'edit'])->middleware('permission:division.update')->name('edit');
                Route::put('{division}', [DivisionController::class, 'update'])->middleware('permission:division.update')->name('update');
                Route::delete('{division}', [DivisionController::class, 'destroy'])->middleware('permission:division.delete')->name('destroy');
            });

            Route::prefix('departments')->name('departments.')->middleware('permission:department.read')->group(function (): void {
                Route::get('/', [DepartmentController::class, 'index'])->name('index');
                Route::get('create', [DepartmentController::class, 'create'])->middleware('permission:department.create')->name('create');
                Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:department.create')->name('store');
                Route::get('{department}', [DepartmentController::class, 'show'])->name('show');
                Route::get('{department}/edit', [DepartmentController::class, 'edit'])->middleware('permission:department.update')->name('edit');
                Route::put('{department}', [DepartmentController::class, 'update'])->middleware('permission:department.update')->name('update');
                Route::delete('{department}', [DepartmentController::class, 'destroy'])->middleware('permission:department.delete')->name('destroy');
            });

            Route::prefix('positions')->name('positions.')->middleware('permission:employee.read')->group(function (): void {
                Route::get('/', [PositionController::class, 'index'])->name('index');
                Route::get('create', [PositionController::class, 'create'])->middleware('permission:employee.create')->name('create');
                Route::post('/', [PositionController::class, 'store'])->middleware('permission:employee.create')->name('store');
                Route::get('{position}', [PositionController::class, 'show'])->name('show');
                Route::get('{position}/edit', [PositionController::class, 'edit'])->middleware('permission:employee.update')->name('edit');
                Route::put('{position}', [PositionController::class, 'update'])->middleware('permission:employee.update')->name('update');
                Route::delete('{position}', [PositionController::class, 'destroy'])->middleware('permission:employee.delete')->name('destroy');
            });

            Route::prefix('employees')->name('employees.')->middleware('permission:employee.read')->group(function (): void {
                Route::get('/', [EmployeeController::class, 'index'])->name('index');
                Route::get('create', [EmployeeController::class, 'create'])->middleware('permission:employee.create')->name('create');
                Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employee.create')->name('store');
                Route::get('{employee}', [EmployeeController::class, 'show'])->name('show');
                Route::get('{employee}/edit', [EmployeeController::class, 'edit'])->middleware('permission:employee.update')->name('edit');
                Route::put('{employee}', [EmployeeController::class, 'update'])->middleware('permission:employee.update')->name('update');
                Route::delete('{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:employee.delete')->name('destroy');
            });
        });
    });
