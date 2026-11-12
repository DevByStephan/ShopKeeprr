<?php

namespace Modules\Loyalty;

use App\Models\Order;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Modules\Loyalty\Services\LoyaltyService;

class LoyaltyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'loyalty');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/web.php';
            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        }

        Order::updated(function (Order $order): void {
            if (! Schema::hasTable('loyalty_accounts') || ! $order->wasChanged('payment_status')) {
                return;
            }

            app(LoyaltyService::class)->awardForOrder($order);
        });

        Order::created(function (Order $order): void {
            if (! Schema::hasTable('loyalty_accounts') || $order->payment_status?->value !== 'paid') {
                return;
            }

            app(LoyaltyService::class)->awardForOrder($order);
        });
    }
}
