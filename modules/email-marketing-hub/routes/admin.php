<?php

use Illuminate\Support\Facades\Route;
use Modules\EmailMarketingHub\Http\Controllers\Admin\EmailHubController;

Route::middleware(['web', 'installed', 'admin', 'admin.activity'])
    ->prefix('admin/email-marketing')
    ->name('emailhub.admin.')
    ->group(function (): void {
        Route::get('/', [EmailHubController::class, 'index'])->name('index');
        Route::put('/settings', [EmailHubController::class, 'save'])->name('settings');
        Route::post('/test', [EmailHubController::class, 'test'])->name('test');
        Route::post('/sync', [EmailHubController::class, 'sync'])->name('sync');
        Route::post('/contacts/{contact}/retry', [EmailHubController::class, 'retry'])->name('contacts.retry');
        Route::post('/contacts/{contact}/unsubscribe', [EmailHubController::class, 'unsubscribe'])->name('contacts.unsubscribe');
    });
