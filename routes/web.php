<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified', 'two-factor.required'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('notifications/read', [DashboardController::class, 'readNotifications'])->name('notifications.read');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/catalog.php';
require __DIR__.'/stock.php';
require __DIR__.'/purchasing.php';
require __DIR__.'/pos.php';
require __DIR__.'/compliance.php';
