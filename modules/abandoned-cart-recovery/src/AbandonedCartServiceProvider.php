<?php

namespace Modules\AbandonedCart;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\AbandonedCart\Console\SendRemindersCommand;

class AbandonedCartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([SendRemindersCommand::class]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'abandoned-cart');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/web.php';
            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        }

        if ($this->app->runningInConsole()) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('shopkeeprr:abandoned-cart:remind')
                    ->everyThirtyMinutes()
                    ->withoutOverlapping();
            });
        }
    }
}
