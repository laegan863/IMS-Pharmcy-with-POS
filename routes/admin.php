<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\StoreSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'two-factor.required'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('settings', [StoreSettingsController::class, 'edit'])
            ->can(Permission::SettingsManage->value)
            ->name('settings.edit');
        Route::put('settings', [StoreSettingsController::class, 'update'])
            ->can(Permission::SettingsManage->value)
            ->name('settings.update');
    });
