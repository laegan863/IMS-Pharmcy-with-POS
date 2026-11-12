<?php

use App\Enums\Permission;
use App\Http\Controllers\Catalog\CustomerController;
use App\Http\Controllers\Catalog\LookupController;
use App\Http\Controllers\Catalog\PrescriberController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductImportController;
use App\Http\Controllers\Catalog\ProductLookupController;
use App\Http\Controllers\Catalog\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'two-factor.required'])
    ->prefix('catalog')
    ->name('catalog.')
    ->group(function () {
        $can = fn (Permission $permission) => 'can:'.$permission->value;

        // JSON lookups used by the POS and line-item editors.
        Route::get('lookup/products', ProductLookupController::class)
            ->middleware($can(Permission::ProductsView))
            ->name('lookup.products');
        Route::get('lookup/customers', [CustomerController::class, 'lookup'])
            ->middleware('permission:'.Permission::PosSell->value.'|'.Permission::CustomersManage->value)
            ->name('lookup.customers');
        Route::get('lookup/prescribers', [PrescriberController::class, 'lookup'])
            ->middleware($can(Permission::PrescriptionsManage))
            ->name('lookup.prescribers');

        Route::middleware($can(Permission::ProductsImport))->group(function () {
            Route::get('products/import', [ProductImportController::class, 'create'])->name('products.import');
            Route::get('products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');
            Route::post('products/import', [ProductImportController::class, 'store'])->name('products.import.store');
        });

        Route::get('products', [ProductController::class, 'index'])
            ->middleware($can(Permission::ProductsView))
            ->name('products.index');

        Route::middleware($can(Permission::ProductsManage))->group(function () {
            Route::resource('products', ProductController::class)->except(['index', 'show', 'destroy']);

            Route::get('lookups/{type}', [LookupController::class, 'index'])->name('lookups.index');
            Route::post('lookups/{type}', [LookupController::class, 'store'])->name('lookups.store');
            Route::put('lookups/{type}/{id}', [LookupController::class, 'update'])->whereNumber('id')->name('lookups.update');
        });

        Route::middleware($can(Permission::SuppliersManage))->group(function () {
            Route::resource('suppliers', SupplierController::class)->except(['show', 'destroy']);
        });

        Route::middleware($can(Permission::CustomersManage))->group(function () {
            Route::resource('customers', CustomerController::class)->except(['show', 'destroy']);
        });

        Route::middleware($can(Permission::PrescribersManage))->group(function () {
            Route::resource('prescribers', PrescriberController::class)->except(['show', 'destroy']);
        });
    });
