<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function index(): View
    {
        $cart = $this->cartService->current();
        $mode = nexus_mode();

        if ($mode) {
            $this->cartService->reconcileCart($cart, $mode, app(\App\Services\SettingsService::class)->modeRevision());
        }

        $totals = $this->cartService->totals($cart);

        return nexus_view('cart.index', compact('cart', 'totals'));
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::query()->visibleInMode()->findOrFail($data['product_id']);
        $variant = ! empty($data['variant_id']) ? $product->activeVariants()->findOrFail($data['variant_id']) : null;
        $plan = ! empty($data['subscription_plan_id']) ? SubscriptionPlan::query()->where('product_id', $product->id)->where('is_active', true)->findOrFail($data['subscription_plan_id']) : null;

        $this->cartService->add($product, (int) ($data['quantity'] ?? 1), $variant, $plan);

        return redirect()->route('storefront.cart')->with('status', $product->name.' added to your bag.');
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($item);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']]);
        $this->cartService->update($item, (int) $data['quantity']);

        return redirect()->route('storefront.cart')->with('status', 'Your bag has been updated.');
    }

    public function remove(CartItem $item): RedirectResponse
    {
        $this->authorizeItem($item);
        $this->cartService->remove($item);

        return redirect()->route('storefront.cart')->with('status', 'Item removed.');
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate(['coupon' => ['required', 'string', 'max:60']]);
        $this->cartService->applyCoupon($data['coupon']);

        return redirect()->route('storefront.cart')->with('status', 'Coupon applied.');
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cartService->removeCoupon();

        return redirect()->route('storefront.cart')->with('status', 'Coupon removed.');
    }

    private function authorizeItem(CartItem $item): void
    {
        $cart = $this->cartService->current(false);
        abort_unless($cart && $item->cart_id === $cart->id, 404);
    }
}
