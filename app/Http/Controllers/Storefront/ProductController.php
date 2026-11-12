<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\StoreMode;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(string $slug): View
    {
        $mode = nexus_mode();

        $product = Product::query()
            ->visibleInMode($mode)
            ->where('slug', $slug)
            ->with(['images', 'category', 'vendor', 'activeVariants', 'activeSubscriptionPlans', 'tierPrices.tier'])
            ->firstOrFail();

        if (! $mode || ! $product->supportsMode($mode)) {
            abort(404);
        }

        if ($product->is_wholesale_only && $mode === StoreMode::B2B && ! auth()->user()?->hasWholesaleAccess()) {
            abort(404);
        }

        $related = Product::query()
            ->visibleInMode($mode)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->with(['images', 'category'])
            ->take(4)
            ->get();

        return nexus_view('product.show', compact('product', 'related'));
    }
}
