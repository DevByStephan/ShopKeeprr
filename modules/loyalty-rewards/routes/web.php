<?php

use Illuminate\Support\Facades\Route;
use Modules\Loyalty\Http\Controllers\Admin\LoyaltyAdminController;
use Modules\Loyalty\Http\Controllers\LoyaltyController;

Route::middleware(['web', 'installed', 'auth'])->group(function () {
    Route::get('account/rewards', [LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::post('account/rewards/redeem', [LoyaltyController::class, 'redeem'])->middleware('throttle:10,1')->name('loyalty.redeem');
});

Route::middleware(['web', 'installed', 'admin', 'admin.activity'])
    ->prefix('admin/loyalty')
    ->name('admin.loyalty.')
    ->group(function () {
        Route::get('/', [LoyaltyAdminController::class, 'index'])->name('index');
        Route::patch('settings', [LoyaltyAdminController::class, 'settings'])->name('settings');
        Route::post('adjust', [LoyaltyAdminController::class, 'adjust'])->name('adjust');
    });
