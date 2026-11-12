<?php

namespace Modules\Bundles\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Bundles\Models\Bundle;

class BundleCartService
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function add(Bundle $bundle): void
    {
        $bundle->loadMissing('products');

        if (! $bundle->is_active || $bundle->products->isEmpty()) {
            throw ValidationException::withMessages(['bundle' => 'This bundle is not available.']);
        }

        $cart = $this->cartService->current();
        $subtotal = $bundle->subtotal();
        $discount = $bundle->discount();

        foreach ($bundle->products as $product) {
            $quantity = (int) $product->pivot->quantity;
            $lineTotal = (float) $product->price * $quantity;
            $share = $subtotal > 0 ? $discount * ($lineTotal / $subtotal) : 0;
            $unitPrice = round(max(0, ($lineTotal - $share) / max(1, $quantity)), 2);

            if (! $product->is_active) {
                throw ValidationException::withMessages(['bundle' => $product->name.' is no longer available.']);
            }

            $existing = $cart->items->first(fn (CartItem $item) => $item->product_id === $product->id && $item->variant_id === null);

            $newQuantity = $quantity + ($existing?->quantity ?? 0);

            if ($product->manage_stock && $newQuantity > $product->stock_quantity) {
                throw ValidationException::withMessages([
                    'bundle' => 'There is not enough stock of '.$product->name.' to add this bundle.',
                ]);
            }

            if ($existing) {
                $total = ((float) $existing->unit_price * $existing->quantity) + ($unitPrice * $quantity);
                $existing->quantity = $newQuantity;
                $existing->unit_price = round($total / $newQuantity, 2);
                $existing->save();
            } else {
                $cart->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'subscription_plan_id' => null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'options' => ['bundle' => $bundle->name],
                ]);
            }
        }

        $this->cartService->load($cart);
    }
}
