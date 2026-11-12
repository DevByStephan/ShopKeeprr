<?php

use App\Http\Controllers\Vendor\DashboardController;
use App\Http\Controllers\Vendor\OrderController;
use App\Http\Controllers\Vendor\PayoutController;
use App\Http\Controllers\Vendor\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', 'auth', 'vendor'])
    ->prefix('vendor')
    ->name('vendor.')
    ->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Historical orders, balances and payout settings stay reachable
        // after the store leaves Multi-Vendor mode. Financial obligations run
        // through the original order context, not the current storefront mode.
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{split}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
        Route::post('payouts', [PayoutController::class, 'request'])->name('payouts.request');
        Route::post('payouts/stripe', [PayoutController::class, 'connectStripe'])->name('payouts.stripe');
        Route::get('payouts/stripe/return', [PayoutController::class, 'stripeReturn'])->name('payouts.stripe.return');

        // Creating or editing listings only makes sense in Multi-Vendor mode.
        Route::middleware('store.mode:multivendor')->group(function (): void {
            Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::patch('products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        });
    });
