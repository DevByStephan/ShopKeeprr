<?php

namespace Modules\HelloShopKeeprr;

use Illuminate\Support\ServiceProvider;

class HelloShopKeeprrServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hello-shopkeeprr');

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/web.php';
            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        }
    }
}
