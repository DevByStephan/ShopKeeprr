<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Developer\ProductController as DeveloperProductController;
use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\DeveloperVerificationController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MarketplaceLinkController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\Webhooks\PayPalWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('installed')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/plugins', [ProductController::class, 'plugins'])->name('plugins.index');
    Route::get('/themes', [ProductController::class, 'themes'])->name('themes.index');
    Route::get('/business-models', [ProductController::class, 'businessModels'])->name('business-models.index');
    Route::get('/pages/{page}', [PageController::class, 'show'])->name('pages.show');

    Route::get('/developers', [DeveloperController::class, 'index'])->middleware('developer.portal')->name('developers.index');
    Route::post('/developers/join', [DeveloperController::class, 'join'])->middleware(['auth', 'verified', 'developer.portal'])->name('developers.join');
    Route::post('/developers/application-fee', [DeveloperVerificationController::class, 'pay'])->middleware(['auth', 'verified', 'developer.portal'])->name('developers.verify.pay');
    Route::post('/developers/payout/verification', [DeveloperController::class, 'resendPayoutVerification'])->middleware(['auth', 'verified', 'developer.portal'])->name('developers.payout.send');
    Route::get('/developers/application-fee/return', [DeveloperVerificationController::class, 'returnFromPayPal'])->middleware(['auth', 'verified', 'developer.portal'])->name('developers.verify.return');
    Route::get('/developers/application-fee/cancel', [DeveloperVerificationController::class, 'cancel'])->middleware(['auth', 'verified', 'developer.portal'])->name('developers.verify.cancel');
    Route::get('/developers/payout/verify/{developer}', [DeveloperVerificationController::class, 'verifyPayoutEmail'])->middleware(['signed', 'throttle:10,1'])->name('developers.payout.verify');

    // Documentation stays public so developers can build for their own clients
    // even when the seller portal is closed.
    Route::get('/developers/docs', [DeveloperController::class, 'docs'])->name('developers.docs');

    Route::middleware(['auth', 'verified', 'developer.portal', 'developer'])->prefix('developers')->name('developers.')->group(function () {
        Route::get('/dashboard', [DeveloperController::class, 'dashboard'])->name('dashboard');
        Route::get('/sales', [DeveloperController::class, 'sales'])->name('sales');
        Route::get('/payouts', [DeveloperController::class, 'payouts'])->name('payouts');
        Route::patch('/profile', [DeveloperController::class, 'updateProfile'])->name('profile.update');
        Route::get('/products', [DeveloperProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [DeveloperProductController::class, 'create'])->name('products.create');
        Route::post('/products', [DeveloperProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [DeveloperProductController::class, 'edit'])->name('products.edit');
        Route::patch('/products/{product}', [DeveloperProductController::class, 'update'])->name('products.update');

        Route::post('/products/{product}/releases', [Admin\ReleaseController::class, 'store'])->name('releases.store');
        Route::post('/products/{product}/releases/{release}/current', [Admin\ReleaseController::class, 'current'])->name('releases.current');
        Route::post('/products/{product}/releases/{release}/scan', [Admin\ReleaseController::class, 'scan'])->name('releases.scan');
        Route::delete('/products/{product}/releases/{release}', [Admin\ReleaseController::class, 'destroy'])->name('releases.destroy');
    });
    Route::get('/demo', fn () => config('site.demo_url')
        ? redirect()->away(config('site.demo_url'))
        : redirect()->route('products.index'))->name('demo');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('login.store');
        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
        Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

    Route::middleware('auth')->group(function () {
        Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::post('/checkout/{product}', [CheckoutController::class, 'start'])->name('checkout.start');
        Route::get('/checkout/pay/{order}', [CheckoutController::class, 'pay'])->name('checkout.pay');
        Route::post('/checkout/paypal/{order}', [CheckoutController::class, 'createPayPal'])->name('checkout.paypal.create');
        Route::post('/checkout/paypal/{order}/capture', [CheckoutController::class, 'capturePayPal'])->name('checkout.paypal.capture');
        Route::get('/checkout/return/{order}', [CheckoutController::class, 'return'])->name('checkout.return');
        Route::post('/checkout/cancel/{order}', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
        Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

        Route::prefix('account')->name('account.')->group(function () {
            Route::get('/', [AccountController::class, 'index'])->name('index');
            Route::get('/licenses', [AccountController::class, 'licenses'])->name('licenses');
            Route::post('/licenses/{license}/deactivate', [AccountController::class, 'deactivate'])->name('licenses.deactivate');
            Route::get('/downloads', [AccountController::class, 'downloads'])->name('downloads');
            Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
            Route::get('/orders/{order}', [AccountController::class, 'order'])->name('order');
            Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
            Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
            Route::put('/password', [AccountController::class, 'updatePassword'])->name('password.update');
        });

        Route::get('/help', [SupportTicketController::class, 'index'])->name('support.index');
        Route::post('/help', [SupportTicketController::class, 'store'])->name('support.store');
        Route::get('/help/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
        Route::post('/help/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');
        Route::post('/help/{ticket}/close', [SupportTicketController::class, 'close'])->name('support.close');

        Route::get('/downloads/{release}', [DownloadController::class, 'download'])->name('downloads.release');
    });

    Route::get('/downloads/signed/{release}/{license}', [DownloadController::class, 'signed'])
        ->middleware('signed')
        ->name('downloads.signed');

    Route::middleware('auth')->prefix('marketplace')->name('marketplace.')->group(function () {
        Route::get('link', [MarketplaceLinkController::class, 'show'])->name('link');
        Route::post('link', [MarketplaceLinkController::class, 'approve'])->name('link.approve');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('guest')->group(function () {
            Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
            Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
        });

        Route::middleware('admin')->group(function () {
            Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
            Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
            Route::get('installations', [Admin\InstallationController::class, 'index'])->name('installations.index');

            Route::get('products', [Admin\ProductController::class, 'index'])->name('products.index');
            Route::get('products/create', [Admin\ProductController::class, 'create'])->name('products.create');
            Route::post('products', [Admin\ProductController::class, 'store'])->name('products.store');
            Route::get('products/{product}/edit', [Admin\ProductController::class, 'edit'])->name('products.edit');
            Route::patch('products/{product}', [Admin\ProductController::class, 'update'])->name('products.update');

            Route::post('products/{product}/releases', [Admin\ReleaseController::class, 'store'])->name('releases.store');
            Route::post('products/{product}/releases/{release}/current', [Admin\ReleaseController::class, 'current'])->name('releases.current');
            Route::post('products/{product}/releases/{release}/scan', [Admin\ReleaseController::class, 'scan'])->name('releases.scan');
            Route::delete('products/{product}/releases/{release}', [Admin\ReleaseController::class, 'destroy'])->name('releases.destroy');

            Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
            Route::post('orders/{order}/paid', [Admin\OrderController::class, 'markPaid'])->name('orders.paid');
            Route::post('orders/{order}/refund', [Admin\OrderController::class, 'refund'])->name('orders.refund');
            Route::post('orders/{order}/cancel', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');

            Route::get('licenses', [Admin\LicenseController::class, 'index'])->name('licenses.index');
            Route::post('licenses', [Admin\LicenseController::class, 'store'])->name('licenses.store');
            Route::get('licenses/{license}', [Admin\LicenseController::class, 'show'])->name('licenses.show');
            Route::post('licenses/{license}/revoke', [Admin\LicenseController::class, 'revoke'])->name('licenses.revoke');
            Route::post('licenses/{license}/extend', [Admin\LicenseController::class, 'extend'])->name('licenses.extend');
            Route::post('licenses/{license}/reset', [Admin\LicenseController::class, 'reset'])->name('licenses.reset');

            Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
            Route::get('customers/{user}', [Admin\CustomerController::class, 'show'])->name('customers.show');

            Route::get('developers', [Admin\DeveloperController::class, 'index'])->name('developers.index');
            Route::get('developers/{developer}', [Admin\DeveloperController::class, 'show'])->name('developers.show');
            Route::patch('developers/{developer}', [Admin\DeveloperController::class, 'update'])->name('developers.update');
            Route::patch('developers/{developer}/override', [Admin\DeveloperController::class, 'override'])->name('developers.override');
            Route::post('developers/{developer}/payout', Admin\DeveloperPayoutController::class)->name('developers.payout');

            Route::get('payouts', [Admin\PayoutController::class, 'index'])->name('payouts.index');
            Route::post('payouts/pay-all', [Admin\PayoutController::class, 'payAll'])->name('payouts.pay-all');

            Route::get('pages', [Admin\PageController::class, 'index'])->name('pages.index');
            Route::get('pages/{page}/edit', [Admin\PageController::class, 'edit'])->name('pages.edit');
            Route::put('pages/{page}', [Admin\PageController::class, 'update'])->name('pages.update');

            Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
            Route::patch('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

            Route::get('mail', [Admin\MailController::class, 'index'])->name('mail.index');
            Route::get('mail/compose', [Admin\MailController::class, 'compose'])->name('mail.compose');
            Route::post('mail/send', [Admin\MailController::class, 'send'])->name('mail.send');
            Route::get('mail/settings', [Admin\MailController::class, 'settings'])->name('mail.settings');
            Route::patch('mail/settings', [Admin\MailController::class, 'updateSettings'])->name('mail.settings.update');
            Route::post('mail/test', [Admin\MailController::class, 'test'])->name('mail.test');
            Route::post('mail/test-imap', [Admin\MailController::class, 'testImap'])->name('mail.test.imap');
            Route::get('mail/{uid}', [Admin\MailController::class, 'show'])->whereNumber('uid')->name('mail.show');

            Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit.index');
            Route::get('crons', [Admin\CronController::class, 'index'])->name('crons.index');
            Route::get('changelog', [Admin\ChangelogController::class, 'index'])->name('changelog.index');

            Route::get('support', [Admin\SupportTicketController::class, 'index'])->name('support.index');
            Route::get('support/{ticket}', [Admin\SupportTicketController::class, 'show'])->name('support.show');
            Route::post('support/{ticket}/reply', [Admin\SupportTicketController::class, 'reply'])->name('support.reply');
            Route::patch('support/{ticket}', [Admin\SupportTicketController::class, 'updateStatus'])->name('support.update');
        });
    });

    Route::post('/webhooks/paypal', PayPalWebhookController::class)->name('webhooks.paypal');
});

Route::get('/install', fn () => redirect('/install.php'))->name('install');
