<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\User;
use App\Services\CartService;
use App\Services\ModuleManager;
use App\Services\SettingsService;
use App\Services\Storefront\BlockRegistry;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(BlockRegistry::class);
    }

    public function boot(): void
    {
        try {
            $this->app->make(SettingsService::class)->applyMailConfiguration();
        } catch (\Throwable) {
            // The installer and early boot must work before the settings table exists.
        }

        try {
            // Modules may register payment gateways, shipping rules or extra
            // storefront builder blocks during boot.
            $this->app->make(ModuleManager::class)->registerEnabledProviders();
        } catch (\Throwable) {
            // A broken module must not take the whole storefront down.
        }

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                app(CartService::class)->mergeGuestCart($event->user, session('nexus_cart_id'));
            }
        });

        View::composer('theme.layouts.app', function ($view) {
            $cart = app(CartService::class)->current(false);
            $view->with('navCartCount', $cart?->itemCount() ?? 0);

            try {
                $view->with('navCategories', Category::query()
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->take(8)
                    ->get());
            } catch (\Throwable) {
                $view->with('navCategories', collect());
            }
        });
    }
}
