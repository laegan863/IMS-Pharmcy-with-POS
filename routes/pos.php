<?php

use App\Enums\Permission;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\ReadingController;
use App\Http\Controllers\Pos\SaleController;
use App\Http\Controllers\Pos\ShiftController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'two-factor.required', 'can:'.Permission::PosSell->value])
    ->prefix('pos')
    ->name('pos.')
    ->group(function () {
        Route::get('/', [PosController::class, 'screen'])->name('screen');
        Route::post('quote', [PosController::class, 'quote'])->name('quote');
        Route::post('sales', [PosController::class, 'complete'])->middleware('throttle:60,1')->name('sales.complete');
        Route::get('held', [PosController::class, 'heldIndex'])->name('held.index');
        Route::post('held', [PosController::class, 'hold'])->name('held.store');
        Route::post('held/{held}/resume', [PosController::class, 'resume'])->name('held.resume');

        Route::get('shifts/open', [ShiftController::class, 'create'])->name('shifts.create');
        Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
        Route::post('shifts/cash', [ShiftController::class, 'cash'])->name('shifts.cash');
        Route::get('shifts/close', [ShiftController::class, 'count'])->name('shifts.count');
        Route::post('shifts/close', [ShiftController::class, 'close'])->name('shifts.close');
        Route::get('shifts/{shift}/x-reading', [ShiftController::class, 'xReading'])->name('shifts.x-reading');

        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::post('sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
        Route::post('sales/{sale}/refund', [SaleController::class, 'refund'])->name('sales.refund');
    });

Route::middleware(['auth', 'verified', 'two-factor.required', 'can:'.Permission::PosReadings->value])
    ->prefix('pos/readings')
    ->name('pos.readings.')
    ->group(function () {
        Route::get('/', [ReadingController::class, 'index'])->name('index');
        Route::post('/', [ReadingController::class, 'store'])->name('store');
        Route::get('{reading}', [ReadingController::class, 'show'])->name('show');
    });
