<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MarketplaceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\StorefrontBuilderController;
use App\Http\Controllers\Admin\StoreModeController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\VendorController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('installed')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
    });

    Route::middleware(['installed', 'admin', 'admin.activity'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('store-mode/preflight', [StoreModeController::class, 'preflight'])->name('store-mode.preflight');
        Route::post('store-mode/switch', [StoreModeController::class, 'switch'])->name('store-mode.switch');
        Route::get('store-mode/history', [StoreModeController::class, 'history'])->name('store-mode.history');
        Route::get('store-mode/integrity', [StoreModeController::class, 'integrity'])->name('store-mode.integrity');

        Route::resource('products', ProductController::class)->except('show');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('coupons', CouponController::class)->except('show');
        Route::post('pages/legal/restore', [PageController::class, 'restoreLegal'])->name('pages.legal.restore');
        Route::resource('pages', PageController::class)->except('show');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('orders/{order}/paid', [OrderController::class, 'markPaid'])->name('orders.paid');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/note', [OrderController::class, 'note'])->name('orders.note');
        Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

        Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');

        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::post('subscriptions/{subscription}/action', [SubscriptionController::class, 'action'])->name('subscriptions.action');

        Route::get('shipping', [ShippingController::class, 'index'])->name('shipping.index');
        Route::post('shipping/zones', [ShippingController::class, 'storeZone'])->name('shipping.zones.store');
        Route::patch('shipping/zones/{zone}', [ShippingController::class, 'updateZone'])->name('shipping.zones.update');
        Route::delete('shipping/zones/{zone}', [ShippingController::class, 'destroyZone'])->name('shipping.zones.destroy');
        Route::post('shipping/methods', [ShippingController::class, 'storeMethod'])->name('shipping.methods.store');
        Route::patch('shipping/methods/{method}', [ShippingController::class, 'updateMethod'])->name('shipping.methods.update');
        Route::delete('shipping/methods/{method}', [ShippingController::class, 'destroyMethod'])->name('shipping.methods.destroy');

        Route::get('taxes', [TaxController::class, 'index'])->name('taxes.index');
        Route::post('taxes', [TaxController::class, 'store'])->name('taxes.store');
        Route::patch('taxes/{rate}', [TaxController::class, 'update'])->name('taxes.update');
        Route::delete('taxes/{rate}', [TaxController::class, 'destroy'])->name('taxes.destroy');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::post('vendors/{vendor}/status', [VendorController::class, 'status'])->name('vendors.status');
        Route::post('vendors/{vendor}/stripe', [VendorController::class, 'connectStripe'])->name('vendors.stripe');
        Route::get('vendors/{vendor}/stripe/return', [VendorController::class, 'stripeReturn'])->name('vendors.stripe.return');
        Route::post('vendors/{vendor}/stripe/refresh', [VendorController::class, 'refreshStripe'])->name('vendors.stripe.refresh');
        Route::post('vendors/{vendor}/payout', [VendorController::class, 'payout'])->name('vendors.payout');
        Route::resource('vendors', VendorController::class)->except('destroy');

        Route::get('plugins', [PluginController::class, 'index'])->name('plugins.index');
        Route::post('plugins/upload', [PluginController::class, 'upload'])->name('plugins.upload');
        Route::patch('plugins/{module}/toggle', [PluginController::class, 'toggle'])->name('plugins.toggle');
        Route::delete('plugins/{module}', [PluginController::class, 'destroy'])->name('plugins.destroy');

        Route::prefix('marketplace')->name('marketplace.')->group(function () {
            Route::get('/', [MarketplaceController::class, 'index'])->name('index');
            Route::post('connect', [MarketplaceController::class, 'connect'])->name('connect');
            Route::get('link', [MarketplaceController::class, 'link'])->name('link');
            Route::get('link/status', [MarketplaceController::class, 'status'])->name('status');
            Route::post('disconnect', [MarketplaceController::class, 'disconnect'])->name('disconnect');
            Route::post('refresh', [MarketplaceController::class, 'refresh'])->name('refresh');
            Route::post('install', [MarketplaceController::class, 'install'])->name('install');
        });

        Route::prefix('appearance/builder')->name('builder.')->group(function () {
            Route::get('/', [StorefrontBuilderController::class, 'index'])->name('index');
            Route::get('preview', [StorefrontBuilderController::class, 'preview'])->name('preview');
            Route::put('draft', [StorefrontBuilderController::class, 'saveDraft'])->name('draft');
            Route::post('publish', [StorefrontBuilderController::class, 'publish'])->name('publish');
            Route::post('discard', [StorefrontBuilderController::class, 'discard'])->name('discard');
            Route::post('reset', [StorefrontBuilderController::class, 'reset'])->name('reset');
            Route::post('disable', [StorefrontBuilderController::class, 'disable'])->name('disable');
            Route::post('image', [StorefrontBuilderController::class, 'uploadImage'])->name('image');
        });

        Route::get('theme', [ThemeController::class, 'index'])->name('theme.index');
        Route::patch('theme', [ThemeController::class, 'update'])->name('theme.update');
        Route::post('theme/activate', [ThemeController::class, 'activate'])->name('theme.activate');
        Route::get('theme/edit/{file}', [ThemeController::class, 'edit'])->where('file', '.*')->name('theme.edit');
        Route::put('theme/edit/{file}', [ThemeController::class, 'save'])->where('file', '.*')->name('theme.save');
        Route::delete('theme/edit/{file}', [ThemeController::class, 'reset'])->where('file', '.*')->name('theme.reset');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('updates', [UpdateController::class, 'index'])->name('updates.index');
        Route::post('updates/check', [UpdateController::class, 'check'])->name('updates.check');
        Route::get('updates/{version}/confirm', [UpdateController::class, 'confirm'])->where('version', '[0-9.]+')->name('updates.confirm');
        Route::post('updates/apply', [UpdateController::class, 'apply'])->name('updates.apply');
        Route::post('updates/{run}/rollback', [UpdateController::class, 'rollback'])->whereNumber('run')->name('updates.rollback');
    });
});
