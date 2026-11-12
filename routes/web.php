<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/install', fn () => nexus_demo() ? abort(404) : redirect('/install.php'))->name('install');

Route::middleware('installed')->get('/demo', DemoController::class)->name('demo');

require __DIR__.'/auth.php';
require __DIR__.'/storefront.php';
require __DIR__.'/admin.php';
require __DIR__.'/vendor.php';
require __DIR__.'/webhooks.php';
