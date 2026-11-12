<?php

use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\NewsletterController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ShopController;
use App\Http\Controllers\Storefront\TradeController;
use App\Http\Controllers\Storefront\VendorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', 'store.live'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('storefront.home');
    Route::get('/shop', [ShopController::class, 'index'])->name('storefront.shop');
    Route::get('/category/{category:slug}', [ShopController::class, 'category'])->name('storefront.category');
    Route::middleware('store.mode:multivendor')->group(function () {
        Route::get('/vendors', [VendorController::class, 'index'])->name('storefront.vendors.index');
        Route::get('/vendors/{vendor:slug}', [VendorController::class, 'show'])->name('storefront.vendors.show');
    });
    Route::get('/product/{slug}', [ProductController::class, 'show'])->name('storefront.product');
    Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('storefront.page');
    Route::post('/newsletter', [NewsletterController::class, 'store'])->middleware('throttle:6,1')->name('storefront.newsletter');

    Route::get('/cart', [CartController::class, 'index'])->name('storefront.cart');
    Route::post('/cart/add', [CartController::class, 'add'])->name('storefront.cart.add');
    Route::patch('/cart/{item}', [CartController::class, 'update'])->name('storefront.cart.update');
    Route::delete('/cart/{item}', [CartController::class, 'remove'])->name('storefront.cart.remove');
    Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('storefront.cart.coupon');
    Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('storefront.cart.coupon.remove');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('storefront.checkout');
    Route::post('/checkout/shipping', [CheckoutController::class, 'shipping'])->name('storefront.checkout.shipping');
    Route::post('/checkout', [CheckoutController::class, 'place'])->middleware('throttle:10,1')->name('storefront.checkout.place');
    Route::get('/checkout/confirmation/{order:number}', [CheckoutController::class, 'confirmation'])->name('storefront.checkout.confirmation');
});

Route::middleware(['installed', 'auth'])->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('storefront.account.index');
    Route::get('/account/orders', [AccountController::class, 'orders'])->name('storefront.account.orders');
    Route::get('/account/orders/{order}', [AccountController::class, 'order'])->name('storefront.account.order');
    Route::get('/account/subscriptions', [AccountController::class, 'subscriptions'])->name('storefront.account.subscriptions');
    Route::post('/account/subscriptions/{subscription}/cancel', [AccountController::class, 'cancelSubscription'])->name('storefront.account.subscription.cancel');
    Route::get('/account/profile', [AccountController::class, 'profile'])->name('storefront.account.profile');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])->name('storefront.account.profile.update');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('storefront.account.password');
});

Route::middleware(['installed', 'auth', 'store.mode:b2b'])->group(function () {
    Route::get('/trade', [TradeController::class, 'index'])->name('storefront.trade');
    Route::post('/trade', [TradeController::class, 'apply'])->name('storefront.trade.apply');
});
