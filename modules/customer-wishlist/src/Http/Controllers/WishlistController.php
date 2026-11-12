<?php

namespace Modules\Wishlist\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Wishlist\Models\WishlistItem;

class WishlistController extends Controller
{
    public function index(): View
    {
        $items = WishlistItem::query()
            ->with(['product.images', 'product.category'])
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(12);

        return view('wishlist::index', compact('items'));
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $existing = WishlistItem::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('status', $product->name.' removed from your wishlist.');
        }

        WishlistItem::query()->create([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return back()->with('status', $product->name.' saved to your wishlist.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        WishlistItem::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return back()->with('status', 'Removed from your wishlist.');
    }
}
