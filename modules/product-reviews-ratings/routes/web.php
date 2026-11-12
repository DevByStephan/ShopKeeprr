<?php

use Illuminate\Support\Facades\Route;
use Modules\Reviews\Http\Controllers\Admin\ReviewController as AdminReviewController;
use Modules\Reviews\Http\Controllers\ReviewController;

Route::middleware(['web', 'installed'])
    ->prefix('products/{product:slug}')
    ->name('reviews.')
    ->group(function () {
        Route::post('reviews', [ReviewController::class, 'store'])->middleware(['auth', 'throttle:8,1'])->name('store');
    });

Route::middleware(['web', 'installed'])
    ->post('reviews/{review}/helpful', [ReviewController::class, 'helpful'])
    ->middleware('throttle:30,1')
    ->name('reviews.helpful');

Route::middleware(['web', 'installed', 'admin', 'admin.activity'])
    ->prefix('admin/reviews')
    ->name('admin.reviews.')
    ->group(function () {
        Route::get('/', [AdminReviewController::class, 'index'])->name('index');
        Route::patch('{review}', [AdminReviewController::class, 'update'])->name('update');
        Route::delete('{review}', [AdminReviewController::class, 'destroy'])->name('destroy');
    });
