<?php

use App\Enums\Permission;
use App\Http\Controllers\Stock\StockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'two-factor.required'])
    ->prefix('stock')
    ->name('stock.')
    ->group(function () {
        Route::middleware('can:'.Permission::StockView->value)->group(function () {
            Route::get('/', [StockController::class, 'index'])->name('index');
            Route::get('products/{product}', [StockController::class, 'product'])->name('product');
            Route::get('batches/{batch}', [StockController::class, 'batch'])->name('batch');
        });

        Route::middleware('can:'.Permission::StockImport->value)->group(function () {
            Route::get('opening', [StockController::class, 'openingCreate'])->name('opening');
            Route::get('opening/template', [StockController::class, 'openingTemplate'])->name('opening.template');
            Route::post('opening', [StockController::class, 'openingStore'])->name('opening.store');
        });
    });
