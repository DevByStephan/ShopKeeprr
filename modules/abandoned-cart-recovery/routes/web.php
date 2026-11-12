<?php

use Illuminate\Support\Facades\Route;
use Modules\AbandonedCart\Http\Controllers\Admin\AbandonedCartController;
use Modules\AbandonedCart\Http\Controllers\RecoveryController;

Route::middleware(['web', 'installed', 'signed'])
    ->get('checkout/recover/{order}', [RecoveryController::class, 'return'])
    ->name('abandoned.return');

Route::middleware(['web', 'installed', 'admin', 'admin.activity'])
    ->prefix('admin/abandoned-cart')
    ->name('admin.abandoned.')
    ->group(function () {
        Route::get('/', [AbandonedCartController::class, 'index'])->name('index');
        Route::patch('settings', [AbandonedCartController::class, 'settings'])->name('settings');
        Route::post('run', [AbandonedCartController::class, 'run'])->name('run');
    });
