<?php

namespace Modules\Bundles;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Bundles\Models\Bundle;

class BundlesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bundles');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/web.php';
            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        }

        View::composer('bundles::product-section', function ($view): void {
            if (! Schema::hasTable('product_bundles')) {
                return;
            }

            $product = $view->getData()['product'] ?? null;

            if (! $product) {
                return;
            }

            $bundles = Bundle::query()
                ->with(['products.images', 'products.category'])
                ->where('is_active', true)
                ->whereHas('products', fn ($query) => $query->where('products.id', $product->id))
                ->orderBy('name')
                ->limit(3)
                ->get();

            $view->with('bundles', $bundles);
        });
    }
}
