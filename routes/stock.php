<?php

use App\Enums\Permission;
use App\Http\Controllers\Stock\StockAdjustmentController;
use App\Http\Controllers\Stock\StockController;
use App\Http\Controllers\Stock\StockCountController;
use App\Http\Controllers\Stock\StockTransferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'two-factor.required'])
    ->prefix('stock')
    ->name('stock.')
    ->group(function () {
        $can = fn (Permission ...$permissions) => 'permission:'.implode('|', array_map(fn (Permission $p) => $p->value, $permissions));

        Route::middleware($can(Permission::StockView))->group(function () {
            Route::get('/', [StockController::class, 'index'])->name('index');
            Route::get('products/{product}', [StockController::class, 'product'])->name('product');
            Route::get('batches/lookup', [StockController::class, 'batchLookup'])->name('batches.lookup');
            Route::get('batches/{batch}', [StockController::class, 'batch'])->name('batch');
        });

        Route::middleware($can(Permission::StockQuarantine))->group(function () {
            Route::post('batches/{batch}/quarantine', [StockController::class, 'quarantine'])->name('batches.quarantine');
            Route::post('batches/{batch}/release', [StockController::class, 'release'])->name('batches.release');
        });

        Route::middleware($can(Permission::StockImport))->group(function () {
            Route::get('opening', [StockController::class, 'openingCreate'])->name('opening');
            Route::get('opening/template', [StockController::class, 'openingTemplate'])->name('opening.template');
            Route::post('opening', [StockController::class, 'openingStore'])->name('opening.store');
        });

        // Adjustments and disposals: drafted by staff who adjust or dispose,
        // posted by those who approve (checked in the service).
        Route::middleware($can(Permission::StockAdjust, Permission::StockDispose, Permission::StockAdjustApprove))->group(function () {
            Route::get('documents/{type}', [StockAdjustmentController::class, 'index'])->whereIn('type', ['adjustment', 'disposal'])->name('adjustments.index');
            Route::get('documents/{type}/create', [StockAdjustmentController::class, 'create'])->whereIn('type', ['adjustment', 'disposal'])->name('adjustments.create');
            Route::post('documents/{type}', [StockAdjustmentController::class, 'store'])->whereIn('type', ['adjustment', 'disposal'])->name('adjustments.store');
            Route::get('adjustments/{adjustment}', [StockAdjustmentController::class, 'show'])->name('adjustments.show');
            Route::put('adjustments/{adjustment}', [StockAdjustmentController::class, 'update'])->name('adjustments.update');
        });

        Route::middleware($can(Permission::StockCount, Permission::StockCountApprove))->group(function () {
            Route::get('counts', [StockCountController::class, 'index'])->name('counts.index');
            Route::post('counts', [StockCountController::class, 'store'])->name('counts.store');
            Route::get('counts/{count}', [StockCountController::class, 'show'])->name('counts.show');
            Route::put('counts/{count}', [StockCountController::class, 'update'])->name('counts.update');
        });

        Route::middleware($can(Permission::StockTransfer))->group(function () {
            Route::get('transfers', [StockTransferController::class, 'index'])->name('transfers.index');
            Route::get('transfers/create', [StockTransferController::class, 'create'])->name('transfers.create');
            Route::post('transfers', [StockTransferController::class, 'store'])->name('transfers.store');
            Route::get('transfers/{transfer}', [StockTransferController::class, 'show'])->name('transfers.show');
            Route::put('transfers/{transfer}', [StockTransferController::class, 'update'])->name('transfers.update');
            Route::post('transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('transfers.receive');
        });
    });
