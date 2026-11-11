<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'installed', 'auth'])
    ->get('account/hello-shopkeeprr', function () {
        return view('hello-shopkeeprr::index');
    })
    ->name('hello-shopkeeprr.index');
