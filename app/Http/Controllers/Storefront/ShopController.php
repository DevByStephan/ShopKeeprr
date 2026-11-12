<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\StoreMode;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()
            ->visibleInMode()
            ->with(['images', 'category', 'activeVariants', 'tierPrices']);

        if (nexus_mode() === StoreMode::B2B && ! $request->user()?->hasWholesaleAccess()) {
            $query->where('is_wholesale_only', false);
        }

        if ($request->filled('category')) {
            $category = Category::query()->active()->where('slug', $request->string('category'))->first();
            if ($category) {
                $query->whereIn('category_id', [$category->id, ...$category->children()->pluck('id')]);
            }
        }

        if ($request->filled('q')) {
            $query->search($request->string('q')->toString());
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        match ($request->string('sort')->toString()) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name' => $query->orderBy('name'),
            default => $query->latest('published_at'),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::query()->active()->withCount(['products' => fn ($q) => $q->visibleInMode()])->orderBy('sort_order')->get();

        return nexus_view('shop.index', compact('products', 'categories'));
    }

    public function category(Category $category): View
    {
        abort_unless($category->is_active, 404);

        $products = Product::query()
            ->visibleInMode()
            ->whereIn('category_id', [$category->id, ...$category->children()->pluck('id')])
            ->with(['images', 'category', 'activeVariants', 'tierPrices'])
            ->latest('published_at')
            ->paginate(12);

        $categories = Category::query()->active()->withCount(['products' => fn ($q) => $q->visibleInMode()])->orderBy('sort_order')->get();

        return nexus_view('shop.index', compact('products', 'categories', 'category'));
    }
}
