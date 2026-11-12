<?php

use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/{gateway}', [PaymentWebhookController::class, 'handle'])
    ->where('gateway', 'stripe|paypal')
    ->name('webhooks.handle');
