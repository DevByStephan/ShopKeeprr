<?php

namespace Modules\AbandonedCart\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Modules\AbandonedCart\Models\Reminder;

class RecoveryController extends Controller
{
    public function return(Order $order): RedirectResponse
    {
        $reminder = Reminder::query()->where('order_id', $order->id)->first();

        if ($reminder && ! $reminder->clicked_at) {
            $reminder->update(['clicked_at' => now()]);
        }

        session(['url.intended' => route('storefront.account.orders')]);

        return redirect()->route('storefront.account.orders')->with(
            'status',
            'Welcome back. Your order is saved and ready when you are.'
        );
    }
}
