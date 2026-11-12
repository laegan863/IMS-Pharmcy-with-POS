<?php

use App\Enums\Permission;
use App\Http\Controllers\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\SupplierReturnController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'two-factor.required'])
    ->prefix('purchasing')
    ->name('purchasing.')
    ->group(function () {
        $any = 'permission:'.implode('|', [
            Permission::PurchaseOrdersManage->value,
            Permission::PurchaseOrdersApprove->value,
            Permission::ReceiptsManage->value,
        ]);

        // Orders can be read by anyone who orders, approves or receives.
        Route::middleware($any)->group(function () {
            Route::get('orders', [PurchaseOrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [PurchaseOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        });

        Route::middleware('can:'.Permission::PurchaseOrdersManage->value)->group(function () {
            Route::get('orders/create', [PurchaseOrderController::class, 'create'])->name('orders.create');
            Route::post('orders', [PurchaseOrderController::class, 'store'])->name('orders.store');
            Route::put('orders/{order}', [PurchaseOrderController::class, 'update'])->name('orders.update');
            Route::post('orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('orders.cancel');
            Route::get('reorder', [PurchaseOrderController::class, 'reorder'])->name('reorder');
        });

        Route::post('orders/{order}/approve', [PurchaseOrderController::class, 'approve'])
            ->middleware('can:'.Permission::PurchaseOrdersApprove->value)
            ->name('orders.approve');

        Route::middleware('can:'.Permission::ReceiptsManage->value)->group(function () {
            Route::get('receipts', [GoodsReceiptController::class, 'index'])->name('receipts.index');
            Route::get('receipts/create', [GoodsReceiptController::class, 'create'])->name('receipts.create');
            Route::post('receipts', [GoodsReceiptController::class, 'store'])->name('receipts.store');
            Route::get('receipts/{receipt}', [GoodsReceiptController::class, 'show'])->name('receipts.show');
            Route::put('receipts/{receipt}', [GoodsReceiptController::class, 'update'])->name('receipts.update');
        });

        Route::middleware('can:'.Permission::SupplierReturnsManage->value)->group(function () {
            Route::get('returns', [SupplierReturnController::class, 'index'])->name('returns.index');
            Route::get('returns/batches', [SupplierReturnController::class, 'batches'])->name('returns.batches');
            Route::get('returns/create', [SupplierReturnController::class, 'create'])->name('returns.create');
            Route::post('returns', [SupplierReturnController::class, 'store'])->name('returns.store');
            Route::get('returns/{return}', [SupplierReturnController::class, 'show'])->name('returns.show');
            Route::put('returns/{return}', [SupplierReturnController::class, 'update'])->name('returns.update');
        });
    });
