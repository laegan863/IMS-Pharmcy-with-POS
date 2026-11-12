<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StoreSettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'two-factor.required'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::middleware('can:'.Permission::SettingsManage->value)->group(function () {
            Route::get('settings', [StoreSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [StoreSettingsController::class, 'update'])->name('settings.update');
        });

        Route::middleware('can:'.Permission::UsersManage->value)->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
        });

        Route::middleware('can:'.Permission::RolesManage->value)->group(function () {
            Route::resource('roles', RoleController::class)->except(['show']);
        });

        Route::middleware('can:'.Permission::BranchesManage->value)->group(function () {
            Route::resource('branches', BranchController::class)->except(['show', 'destroy']);
            Route::post('branches/{branch}/terminals', [BranchController::class, 'storeTerminal'])->name('branches.terminals.store');
            Route::put('branches/{branch}/terminals/{terminal}', [BranchController::class, 'updateTerminal'])
                ->whereNumber('terminal')
                ->name('branches.terminals.update');
        });

        Route::get('audit-log', [AuditLogController::class, 'index'])
            ->middleware('can:'.Permission::AuditView->value)
            ->name('audit-log.index');
    });
