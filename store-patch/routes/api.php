<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\InstallationController;
use App\Http\Controllers\Api\LicenseApiController;
use App\Http\Controllers\Api\MarketplaceCatalogController;
use App\Http\Controllers\Api\MarketplaceInstallController;
use App\Http\Controllers\Api\MarketplaceLinkController;
use Illuminate\Support\Facades\Route;

Route::post('analytics/collect', [AnalyticsController::class, 'collect'])
    ->middleware('throttle:1200,1')
    ->name('analytics.collect');
Route::options('analytics/collect', [AnalyticsController::class, 'preflight'])
    ->middleware('throttle:1200,1')
    ->name('analytics.preflight');

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('marketplace/catalog', [MarketplaceCatalogController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('marketplace.catalog');
    Route::get('marketplace/download/{product:slug}', [MarketplaceCatalogController::class, 'freeDownload'])
        ->middleware('throttle:20,1')
        ->name('marketplace.download');

    Route::prefix('marketplace')->name('marketplace.')->middleware('throttle:30,1')->group(function () {
        Route::post('link/start', [MarketplaceLinkController::class, 'start'])->name('link.start');
        Route::post('link/poll', [MarketplaceLinkController::class, 'poll'])->name('link.poll');

        Route::middleware('marketplace.site')->group(function () {
            Route::post('link/revoke', [MarketplaceLinkController::class, 'revoke'])->name('link.revoke');
            Route::get('account', [MarketplaceInstallController::class, 'account'])->name('account');
            Route::get('library', [MarketplaceInstallController::class, 'library'])->name('library');
            Route::post('install', [MarketplaceInstallController::class, 'install'])->name('install');
        });

        Route::get('download/{release}/{link}', [MarketplaceInstallController::class, 'download'])
            ->middleware('signed')
            ->name('package');
    });

    Route::get('license/public-key', [LicenseApiController::class, 'publicKey'])->name('license.public-key');

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('licenses/activate', [LicenseApiController::class, 'activate'])->name('licenses.activate');
        Route::post('licenses/validate', [LicenseApiController::class, 'validateLicense'])->name('licenses.validate');
        Route::post('licenses/deactivate', [LicenseApiController::class, 'deactivate'])->name('licenses.deactivate');
        Route::post('downloads', [LicenseApiController::class, 'download'])->name('downloads');
    });

    Route::prefix('installs')->name('installs.')->middleware('throttle:30,1')->group(function () {
        Route::post('register', [InstallationController::class, 'register'])->name('register');
        Route::post('heartbeat', [InstallationController::class, 'heartbeat'])->name('heartbeat');
    });

    Route::get('health', fn () => response()->json([
        'ok' => true,
        'service' => 'ShopKeeprr license service',
        'version' => '1.0',
        'time' => now()->toIso8601String(),
    ]))->name('health');
});
