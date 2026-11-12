<?php

namespace Modules\EmailMarketingHub;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\EmailMarketingHub\Listeners\RecordMarketingConsent;

class EmailMarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmailMarketingManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'emailhub');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/admin.php';
            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        }

        Event::listen('marketing.opt-in', RecordMarketingConsent::class);

        View::composer('theme.checkout.index', function ($view): void {
            $view->with('emailMarketingHubEnabled', $this->app->make(EmailMarketingManager::class)->enabled());
        });
    }
}
