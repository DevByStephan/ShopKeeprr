<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Storefront\StorefrontBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(StorefrontBuilder $builder): View|RedirectResponse
    {
        $builderBlocks = $builder->homeBlocks();

        if ($builderBlocks !== null) {
            return view('theme.builder.home', ['blocks' => $builderBlocks]);
        }

        $featured = Product::query()
            ->visibleInMode()
            ->featured()
            ->with(['images', 'category', 'activeVariants', 'tierPrices'])
            ->latest('published_at')
            ->take(6)
            ->get();

        $newArrivals = Product::query()
            ->visibleInMode()
            ->with(['images', 'category', 'activeVariants'])
            ->latest('published_at')
            ->take(8)
            ->get();

        $deals = Product::query()
            ->visibleInMode()
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->with(['images', 'category', 'activeVariants', 'tierPrices'])
            ->orderByDesc('compare_at_price')
            ->take(8)
            ->get();

        $categories = Category::query()
            ->active()
            ->withCount(['products' => fn ($query) => $query->visibleInMode()])
            ->orderBy('sort_order')
            ->take(8)
            ->get();

        return nexus_view('home', compact('featured', 'newArrivals', 'deals', 'categories'));
    }
}
