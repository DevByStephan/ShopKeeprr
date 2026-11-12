<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\StoreMode;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly TaxService $tax,
        private readonly SettingsService $settings,
        private readonly StoreModeCompatibility $compatibility,
    ) {
    }

    public function current(bool $create = true): ?Cart
    {
        $user = Auth::user();
        $mode = nexus_mode();

        if ($user) {
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();

            if (! $cart) {
                $guestId = session('nexus_cart_id');
                $guest = $guestId ? Cart::query()->whereNull('user_id')->find($guestId) : null;

                if ($guest) {
                    $guest->user_id = $user->id;
                    $guest->session_id = null;
                    $guest->save();
                    $cart = $guest;
                }
            }

            if (! $cart && $create) {
                $cart = $this->createForUser($user);
            }
        } else {
            $cart = null;
            $cartId = session('nexus_cart_id');
            if ($cartId) {
                $cart = Cart::query()->whereNull('user_id')->find($cartId);
            }

            if (! $cart) {
                $cart = Cart::query()
                    ->whereNull('user_id')
                    ->where('session_id', session()->getId())
                    ->latest('id')
                    ->first();
            }

            if (! $cart && $create) {
                $cart = Cart::query()->create([
                    'store_id' => Store::default()?->id,
                    'session_id' => session()->getId(),
                    'currency' => (string) nexus_setting('store.currency', 'USD'),
                    'mode' => $mode?->value,
                    'mode_revision' => $this->settings->modeRevision(),
                ]);
            }
        }

        if ($cart) {
            session(['nexus_cart_id' => $cart->id]);
            $this->load($cart);
        }

        return $cart;
    }

    public function add(
        Product $product,
        int $quantity = 1,
        ?ProductVariant $variant = null,
        ?SubscriptionPlan $plan = null,
    ): CartItem {
        $cart = $this->current();
        $user = Auth::user();
        $mode = nexus_mode();

        if (! $mode) {
            throw ValidationException::withMessages(['cart' => 'The store is not configured to accept orders yet.']);
        }

        if ($mode === StoreMode::B2B && nexus_setting('b2b.hide_prices_guests', true) && ! $user?->hasWholesaleAccess()) {
            throw ValidationException::withMessages([
                'cart' => 'This is a trade store. Apply for a wholesale account or sign in to see your pricing.',
            ]);
        }

        $this->compatibility->assertProduct($product, $mode, $variant, $plan);

        $min = $mode === StoreMode::B2B && ! $plan ? max(1, (int) $product->min_order_quantity) : 1;
        $quantity = max($min, $quantity);

        $this->assertStock($cart, $product, $variant, $quantity);

        $unitPrice = $plan ? (float) $plan->price : $product->priceFor($user, $variant, $quantity, $mode);

        $item = $cart->items->first(fn (CartItem $existing) => $existing->product_id === $product->id
            && $existing->variant_id === $variant?->id
            && $existing->subscription_plan_id === $plan?->id);

        if ($item) {
            $newQuantity = $item->quantity + $quantity;
            $this->assertStock($cart, $product, $variant, $newQuantity, $item);
            $item->quantity = $newQuantity;
            $item->unit_price = $plan ? (float) $plan->price : $product->priceFor($user, $variant, $newQuantity, $mode);
            $item->save();
        } else {
            $item = $cart->items()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'subscription_plan_id' => $plan?->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'options' => $variant?->options,
            ]);
        }

        $this->markCurrent($cart);
        $this->load($cart);

        return $item;
    }

    public function update(CartItem $item, int $quantity): void
    {
        $cart = $item->cart;
        $product = $item->product;
        $user = Auth::user();
        $mode = nexus_mode();

        if (! $mode) {
            throw ValidationException::withMessages(['cart' => 'The store is not configured to accept orders yet.']);
        }

        if (! $product) {
            $item->delete();
            $this->load($cart);

            return;
        }

        if ($quantity <= 0) {
            $item->delete();
            $this->load($cart);

            return;
        }

        $this->compatibility->assertProduct($product, $mode, $item->variant, $item->plan);

        $min = $mode === StoreMode::B2B && ! $item->subscription_plan_id ? max(1, (int) $product->min_order_quantity) : 1;
        $quantity = max($min, $quantity);
        $this->assertStock($cart, $product, $item->variant, $quantity, $item);

        $item->quantity = $quantity;
        $item->unit_price = $item->plan
            ? (float) $item->plan->price
            : $product->priceFor($user, $item->variant, $quantity, $mode);
        $item->save();

        $this->markCurrent($cart);
        $this->load($cart);
    }

    public function remove(CartItem $item): void
    {
        $cart = $item->cart;
        $item->delete();
        $this->load($cart);
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->coupon_id = null;
        $this->markCurrent($cart);
        $this->load($cart);
    }

    public function applyCoupon(string $code): Coupon
    {
        if (nexus_mode() === StoreMode::Subscription) {
            throw ValidationException::withMessages(['coupon' => 'Coupons cannot be applied to recurring plan prices.']);
        }

        $cart = $this->current();
        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->first();

        if (! $coupon || ! $coupon->isValidFor($this->subtotal($cart), Auth::user())) {
            throw ValidationException::withMessages(['coupon' => 'That coupon is not valid for this order.']);
        }

        $cart->coupon_id = $coupon->id;
        $cart->save();
        $this->load($cart);

        return $coupon;
    }

    public function removeCoupon(): void
    {
        $cart = $this->current(false);
        if ($cart) {
            $cart->coupon_id = null;
            $cart->save();
            $this->load($cart);
        }
    }

    public function mergeGuestCart(User $user, ?int $guestCartId = null): void
    {
        $guest = Cart::query()->whereNull('user_id')->find($guestCartId ?: session('nexus_cart_id'));

        if (! $guest) {
            return;
        }

        $userCart = Cart::query()->where('user_id', $user->id)->latest('id')->first();

        if (! $userCart) {
            $guest->user_id = $user->id;
            $guest->session_id = null;
            $guest->save();
            session(['nexus_cart_id' => $guest->id]);
            $this->load($guest);

            return;
        }

        foreach ($guest->items as $item) {
            $existing = $userCart->items->first(fn (CartItem $row) => $row->product_id === $item->product_id
                && $row->variant_id === $item->variant_id
                && $row->subscription_plan_id === $item->subscription_plan_id);

            if ($existing) {
                $existing->quantity += $item->quantity;
                $existing->save();
            } else {
                $item->cart_id = $userCart->id;
                $item->save();
            }
        }

        $guest->delete();
        session(['nexus_cart_id' => $userCart->id]);

        $mode = nexus_mode();
        if ($mode) {
            $this->reconcileCart($userCart, $mode, $this->settings->modeRevision());
        }

        $this->load($userCart);
    }

    /**
     * Revalidate and reprice every line in a cart against the target model.
     * Invalid lines are removed, never silently repriced into a different
     * product type. Returns a summary suitable for admin reporting/tests.
     *
     * @return array{removed: int, invalidated: bool, repriced: bool, stale: bool}
     */
    public function reconcileCart(Cart $cart, StoreMode $mode, int $revision): array
    {
        $this->load($cart);

        $summary = [
            'removed' => 0,
            'invalidated' => false,
            'repriced' => false,
            'stale' => $cart->mode !== $mode->value || (int) $cart->mode_revision !== $revision,
        ];

        $user = Auth::user() ?? $cart->user;

        foreach ($cart->items as $item) {
            $problem = $this->compatibility->itemProblem($item, $mode);

            if ($problem) {
                $item->delete();
                $summary['removed']++;
                $summary['invalidated'] = true;
                continue;
            }

            $product = $item->product;
            $min = $mode === StoreMode::B2B && ! $item->subscription_plan_id
                ? max(1, (int) $product->min_order_quantity)
                : 1;

            $quantity = max($min, (int) $item->quantity);

            if ($product->manage_stock) {
                $available = $product->stockFor($item->variant);
                $quantity = min($quantity, max(0, $available));
            }

            if ($quantity <= 0) {
                $item->delete();
                $summary['removed']++;
                $summary['invalidated'] = true;
                continue;
            }

            $price = $item->subscription_plan_id !== null
                ? (float) $item->plan?->price
                : $product->priceFor($user, $item->variant, $quantity, $mode);

            $dirty = false;

            if ($item->quantity !== $quantity) {
                $item->quantity = $quantity;
                $dirty = true;
                $summary['invalidated'] = true;
            }

            if (abs((float) $item->unit_price - $price) >= 0.005) {
                $item->unit_price = $price;
                $dirty = true;
                $summary['repriced'] = true;
            }

            if ($dirty) {
                $item->save();
            }
        }

        $this->load($cart);

        if ($cart->coupon && $mode !== StoreMode::Subscription
            && ! $cart->coupon->isValidFor($this->subtotal($cart), $user)) {
            $cart->coupon_id = null;
            $summary['invalidated'] = true;
        }

        // A stale cart that needed a price or line change is never charged
        // silently. The shopper reviews the updated bag before paying.
        if ($summary['stale'] && ($summary['repriced'] || $summary['removed'] > 0)) {
            $summary['invalidated'] = true;
        }

        $cart->mode = $mode->value;
        $cart->mode_revision = $revision;
        $cart->save();

        return $summary;
    }

    /**
     * Reconcile every active cart in one pass. Used after an atomic model
     * switch; the database transaction is already committed by then.
     *
     * @return array{carts: int, removed: int, invalidated: int}
     */
    public function reconcileAll(StoreMode $mode, int $revision): array
    {
        $summary = ['carts' => 0, 'removed' => 0, 'invalidated' => 0];

        Cart::query()
            ->with(['items.product.vendor', 'items.variant', 'items.plan', 'coupon', 'user'])
            ->orderBy('id')
            ->chunkById(100, function ($carts) use ($mode, $revision, &$summary): void {
                foreach ($carts as $cart) {
                    $result = $this->reconcileCart($cart, $mode, $revision);
                    $summary['carts']++;
                    $summary['removed'] += $result['removed'];

                    if ($result['invalidated']) {
                        $summary['invalidated']++;
                    }
                }
            });

        return $summary;
    }

    /**
     * @return array{
     *     subtotal: float, discount: float, shipping: float, tax: float, total: float,
     *     shipping_method: array{id: int|null, name: string, cost: float, zone: string},
     *     options: array<int, array{id: int|null, name: string, cost: float, zone: string}>,
     *     tax_rate: float, weight: float, item_count: int
     * }
     */
    public function totals(Cart $cart, ?array $address = null, ?int $shippingMethodId = null): array
    {
        $this->load($cart);

        $subtotal = $this->subtotal($cart);
        $weight = $this->weight($cart);
        $isSubscriptionMode = nexus_mode() === StoreMode::Subscription;
        $requiresShipping = $cart->items->contains(fn (CartItem $item) => (bool) $item->product?->requires_shipping);
        $country = $address['country'] ?? (string) nexus_setting('store.country', 'US');
        $state = $address['state'] ?? null;
        $inclusive = (bool) nexus_setting('tax.prices_include_tax', false);

        $coupon = $isSubscriptionMode ? null : $cart->coupon;
        $couponValid = $coupon && $coupon->isValidFor($subtotal, Auth::user());
        $discount = 0.0;
        $freeShipping = false;

        if ($couponValid) {
            if ($coupon->type === DiscountType::FreeShipping) {
                $freeShipping = true;
            } else {
                $discount = $coupon->discountFor($subtotal);
            }
        }

        $options = $requiresShipping
            ? $this->shipping->options($country, $state, $subtotal, $weight)
            : [['id' => 0, 'name' => 'Digital delivery', 'cost' => 0.0, 'zone' => '']];

        if (! $requiresShipping) {
            $method = $options[0];
        } else {
            $method = null;
            if ($shippingMethodId) {
                foreach ($options as $option) {
                    if ((int) $option['id'] === $shippingMethodId) {
                        $method = $option;
                        break;
                    }
                }
            }
            $method ??= $options[0] ?? ['id' => null, 'name' => 'Shipping', 'cost' => 0.0, 'zone' => ''];
        }

        $shippingCost = $freeShipping ? 0.0 : (float) $method['cost'];
        if (! $requiresShipping) {
            $shippingCost = 0.0;
        }

        $taxRate = $this->tax->rateFor($country, $state);
        $taxable = max(0, $subtotal - $discount)
            + ((bool) nexus_setting('tax.shipping_taxable', false) ? $shippingCost : 0);
        $taxAmount = $this->tax->calculate($taxable, $taxRate, $inclusive);

        $total = $inclusive
            ? $subtotal - $discount + $shippingCost
            : $subtotal - $discount + $shippingCost + $taxAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'shipping' => round($shippingCost, 2),
            'tax' => round($taxAmount, 2),
            'total' => round(max(0, $total), 2),
            'shipping_method' => $method,
            'options' => $options,
            'tax_rate' => $taxRate,
            'weight' => round($weight, 3),
            'item_count' => (int) $cart->items->sum('quantity'),
        ];
    }

    public function subtotal(Cart $cart): float
    {
        return round((float) $cart->items->sum(fn (CartItem $item) => $item->lineTotal()), 2);
    }

    public function weight(Cart $cart): float
    {
        return round((float) $cart->items->sum(function (CartItem $item) {
            $weight = $item->variant?->weight ?? $item->product?->weight ?? 0;

            return (float) $weight * $item->quantity;
        }), 3);
    }

    public function load(Cart $cart): void
    {
        $cart->load([
            'items.product.images',
            'items.product.vendor',
            'items.product.tierPrices',
            'items.variant',
            'items.plan',
            'coupon',
        ]);
    }

    private function createForUser(User $user): Cart
    {
        return Cart::query()->create([
            'store_id' => $user->store_id ?: Store::default()?->id,
            'user_id' => $user->id,
            'currency' => (string) nexus_setting('store.currency', 'USD'),
            'mode' => nexus_mode()?->value,
            'mode_revision' => $this->settings->modeRevision(),
        ]);
    }

    private function markCurrent(Cart $cart): void
    {
        $cart->mode = nexus_mode()?->value;
        $cart->mode_revision = $this->settings->modeRevision();
        $cart->save();
    }

    /**
     * Keep a single cart internally consistent. The final conditional
     * decrement at checkout is the database-level guard that prevents two
     * shoppers from buying the last unit.
     */
    private function assertStock(Cart $cart, Product $product, ?ProductVariant $variant, int $quantity, ?CartItem $ignore = null): void
    {
        if (! $product->manage_stock) {
            return;
        }

        $available = $product->stockFor($variant);
        $committed = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
            ->sum('quantity');

        if ($available < ((int) $committed + $quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'Only '.max(0, $available - (int) $committed).' of this item are available.',
            ]);
        }
    }
}
