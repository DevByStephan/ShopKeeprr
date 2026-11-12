<?php

namespace Modules\Reviews;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Reviews\Models\Review;

class ReviewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'reviews');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/web.php';
            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        }

        View::composer('reviews::product-section', function ($view): void {
            if (! Schema::hasTable('product_reviews')) {
                return;
            }

            $product = $view->getData()['product'] ?? null;

            if (! $product) {
                return;
            }

            $approved = Review::query()
                ->with('user')
                ->where('product_id', $product->id)
                ->where('status', Review::STATUS_APPROVED)
                ->latest('approved_at')
                ->limit(8)
                ->get();

            $view->with([
                'reviews' => $approved,
                'reviewCount' => Review::query()->where('product_id', $product->id)->where('status', Review::STATUS_APPROVED)->count(),
                'averageRating' => round((float) Review::query()->where('product_id', $product->id)->where('status', Review::STATUS_APPROVED)->avg('rating'), 1),
                'existingReview' => auth()->check()
                    ? Review::query()->where('product_id', $product->id)->where('user_id', auth()->id())->first()
                    : null,
            ]);
        });
    }
}
