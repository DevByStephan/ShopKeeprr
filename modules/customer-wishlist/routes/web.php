<?php

use Illuminate\Support\Facades\Route;
use Modules\Wishlist\Http\Controllers\WishlistController;

Route::middleware(['web', 'installed', 'auth'])
    ->group(function () {
        Route::get('account/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('wishlist/{product:slug}', [WishlistController::class, 'toggle'])->middleware('throttle:30,1')->name('wishlist.toggle');
        Route::delete('wishlist/{product:slug}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
    });
