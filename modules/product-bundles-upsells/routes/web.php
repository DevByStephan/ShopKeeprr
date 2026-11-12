<?php

use Illuminate\Support\Facades\Route;
use Modules\Bundles\Http\Controllers\Admin\BundleController as AdminBundleController;
use Modules\Bundles\Http\Controllers\BundleCartController;

Route::middleware(['web', 'installed'])
    ->post('bundles/{bundle:slug}/add', [BundleCartController::class, 'add'])
    ->middleware('throttle:20,1')
    ->name('bundles.add');

Route::middleware(['web', 'installed', 'admin', 'admin.activity'])
    ->prefix('admin/bundles')
    ->name('admin.bundles.')
    ->group(function () {
        Route::get('/', [AdminBundleController::class, 'index'])->name('index');
        Route::get('create', [AdminBundleController::class, 'create'])->name('create');
        Route::post('/', [AdminBundleController::class, 'store'])->name('store');
        Route::get('{bundle}/edit', [AdminBundleController::class, 'edit'])->name('edit');
        Route::put('{bundle}', [AdminBundleController::class, 'update'])->name('update');
        Route::delete('{bundle}', [AdminBundleController::class, 'destroy'])->name('destroy');
    });
