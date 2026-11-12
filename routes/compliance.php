<?php

use App\Enums\Permission;
use App\Http\Controllers\Catalog\PrescriberController;
use App\Http\Controllers\Compliance\PrescriptionController;
use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'two-factor.required'])->group(function () {
    $sellOrDispense = 'permission:'.Permission::PosSell->value.'|'.Permission::PrescriptionsManage->value;

    // POS helpers: anyone who sells can look up and record a prescription
    // and see generic alternatives.
    Route::middleware($sellOrDispense)->prefix('pos/rx')->name('pos.rx.')->group(function () {
        Route::get('open-lines', [PrescriptionController::class, 'openLines'])->name('open-lines');
        Route::post('/', [PrescriptionController::class, 'quickStore'])->name('store');
        Route::get('alternatives', [PrescriptionController::class, 'alternatives'])->name('alternatives');
        Route::get('prescribers', [PrescriberController::class, 'lookup'])->name('prescribers');
        Route::post('prescribers', [PrescriberController::class, 'quickStore'])->name('prescribers.store');
    });

    Route::middleware('can:'.Permission::PrescriptionsManage->value)
        ->prefix('compliance/prescriptions')
        ->name('compliance.prescriptions.')
        ->group(function () {
            Route::get('/', [PrescriptionController::class, 'index'])->name('index');
            Route::get('create', [PrescriptionController::class, 'create'])->name('create');
            Route::post('/', [PrescriptionController::class, 'store'])->name('store');
            Route::get('{prescription}', [PrescriptionController::class, 'show'])->name('show');
            Route::get('{prescription}/photo', [PrescriptionController::class, 'photo'])->name('photo');
        });

    // Each report checks its own permission.
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('{key}', [ReportController::class, 'show'])->name('show');
        Route::get('{key}/excel', [ReportController::class, 'excel'])->name('excel');
        Route::get('{key}/pdf', [ReportController::class, 'pdf'])->name('pdf');
    });
});
