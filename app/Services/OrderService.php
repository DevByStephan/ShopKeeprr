<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreMode;
use App\Mail\AdminNewOrder;
use App\Mail\OrderConfirmation;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNote;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly SettingsService $settings,
        private readonly StoreModeCompatibility $compatibility,
        private readonly \App\Services\Marketplace\VendorSplitService $vendorSplits,
    ) {
    }

    /**
     * @param  array{
     *     customer_name: string, customer_email: string, customer_phone?: string|null,
     *     shipping_address: array, billing_address?: array|null, shipping_method_id?: int|null,
     *     payment_method: string, customer_note?: string|null
     * }  $data
     */
    public function createFromCart(Cart $cart, array $data): Order
    {
        $mode = nexus_mode();

        if (! $mode) {
            throw ValidationException::withMessages(['cart' => 'The store model is not configured.']);
        }

        $expectedRevision = $this->settings->modeRevision();
        $reconciliation = $this->cartService->reconcileCart($cart, $mode, $expectedRevision);

        if ($reconciliation['removed'] > 0 || $reconciliation['invalidated']) {
            throw ValidationException::withMessages([
                'cart' => 'Some items or prices changed while you were checking out. Review your bag before paying.',
            ]);
        }

        $this->cartService->load($cart);

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $user = Auth::user();

        if ($cart->items->contains(fn (CartItem $item) => (bool) $item->subscription_plan_id) && ! $user) {
            throw ValidationException::withMessages([
                'cart' => 'Sign in or create an account to start a subscription, so you can manage renewals later.',
            ]);
        }

        if ($mode === StoreMode::B2B && nexus_setting('b2b.hide_prices_guests', true) && ! $user?->hasWholesaleAccess()) {
            throw ValidationException::withMessages(['cart' => 'Sign in with an approved trade account to place this order.']);
        }

        $address = $data['shipping_address'];
        $totals = $this->cartService->totals($cart, $address, $data['shipping_method_id'] ?? null);
        $minimum = (float) nexus_setting('checkout.min_order_total', 0);

        if ($mode === StoreMode::B2B) {
            $minimum = max($minimum, (float) nexus_setting('b2b.min_order_total', 0));
        }

        if ($minimum > 0 && $totals['subtotal'] < $minimum) {
            throw ValidationException::withMessages([
                'cart' => 'The minimum order value is '.nexus_money($minimum).'.',
            ]);
        }

        foreach ($cart->items as $item) {
            $this->assertItemStock($item);
        }

        $coupon = $cart->coupon;
        $couponValid = $coupon && $coupon->isValidFor($totals['subtotal'], $user);

        $order = DB::transaction(function () use ($cart, $data, $totals, $coupon, $couponValid, $mode, $address, $expectedRevision) {
            // A model switch can land between the cart read above and this
            // transaction. Freeze the model row and refuse to place an order
            // against a revision that is no longer active.
            $activeMode = DB::table('settings')->where('key', 'store.mode')->lockForUpdate()->value('value');
            $activeRevision = (int) DB::table('settings')->where('key', 'store.mode_revision')->value('value');

            if ($activeMode !== $mode->value || $activeRevision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'cart' => 'The store model changed while you were checking out. Review your bag and try again.',
                ]);
            }

            // Re-check every line inside the transaction: a seller can be
            // suspended, a product archived, or a plan disabled after the
            // cart was loaded. The database is the final authority.
            foreach ($cart->items as $item) {
                $item->load(['product.vendor', 'variant', 'plan']);

                if ($problem = $this->compatibility->itemProblem($item, $mode)) {
                    throw ValidationException::withMessages(['cart' => $problem]);
                }
            }

            $order = Order::query()->create([
                'store_id' => $cart->store_id,
                'user_id' => Auth::id(),
                'number' => $this->nextNumber(),
                'access_token' => Str::random(48),
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Pending,
                'fulfillment_status' => 'unfulfilled',
                'currency' => $cart->currency ?: 'USD',
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount'],
                'shipping_total' => $totals['shipping'],
                'tax_total' => $totals['tax'],
                'grand_total' => $totals['total'],
                'paid_total' => 0,
                'refunded_total' => 0,
                'coupon_id' => $couponValid ? $coupon->id : null,
                'coupon_code' => $couponValid ? $coupon->code : null,
                'customer_email' => Str::lower(trim($data['customer_email'])),
                'customer_name' => trim($data['customer_name']),
                'customer_phone' => $data['customer_phone'] ?? null,
                'shipping_address' => $address,
                'billing_address' => $data['billing_address'] ?? $address,
                'shipping_method' => (string) ($totals['shipping_method']['id'] ?? 'digital'),
                'shipping_method_title' => $totals['shipping_method']['name'],
                'payment_method' => $data['payment_method'],
                'customer_note' => $data['customer_note'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
                'mode' => $mode->value,
                'mode_revision' => $this->settings->modeRevision(),
                'is_subscription' => $cart->items->contains(fn (CartItem $item) => (bool) $item->subscription_plan_id),
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $product = $item->product;
                $variant = $item->variant;
                $plan = $item->plan;
                $lineSubtotal = round($item->unit_price * $item->quantity, 2);

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product?->id,
                    'variant_id' => $variant?->id,
                    'vendor_id' => $product?->vendor_id,
                    'subscription_plan_id' => $plan?->id,
                    'product_name' => $product?->name ?? 'Product',
                    'variant_name' => $variant?->displayName(),
                    'sku' => $variant?->sku ?? $product?->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_subtotal' => $lineSubtotal,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'line_total' => $lineSubtotal,
                    'options' => $item->options,
                    'is_subscription' => (bool) $plan,
                    'subscription_interval' => $plan?->interval,
                    'subscription_interval_count' => $plan?->interval_count,
                    'subscription_trial_days' => $plan?->trial_days,
                ]);

                $this->reserveStock($order, $item);
            }

            // Seller ledger rows are part of the order transaction. A vendor
            // sale must never commit without the matching payable snapshot.
            $this->vendorSplits->createForOrder($order);

            if ($couponValid && $coupon) {
                CouponUsage::query()->create([
                    'coupon_id' => $coupon->id,
                    'user_id' => Auth::id(),
                    'order_id' => $order->id,
                    'email' => $order->customer_email,
                    'amount' => $totals['discount'],
                ]);
                Coupon::query()->whereKey($coupon->id)->increment('used_count');
            }

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'from_status' => null,
                'to_status' => 'pending',
                'note' => 'Order placed.',
            ]);

            $cart->items()->delete();
            $cart->coupon_id = null;
            $cart->save();

            return $order->load('items', 'payments', 'user');
        });

        $this->sendOrderEmails($order);

        return $order;
    }

    /**
     * Record a captured payment exactly once.
     *
     * Gateways retry webhooks and a shopper can return from a hosted checkout
     * more than once, so the order row is locked and re-checked inside the
     * transaction. A payment that lands after the abandonment task cancelled
     * the order is never dropped: the order is reopened so the money is always
     * accounted for and visible to the merchant.
     */
    public function markPaid(Order $order, string $gateway, ?string $reference = null, array $payload = [], ?string $gatewaySubscriptionId = null): void
    {
        $changed = DB::transaction(function () use ($order, $gateway, $reference, $payload, $gatewaySubscriptionId): bool {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || $locked->isPaid()) {
                return false;
            }

            $payment = $locked->payments()
                ->where('gateway', $gateway)
                ->where('status', '!=', 'succeeded')
                ->latest()
                ->first();

            if (! $payment) {
                $payment = $locked->payments()->create([
                    'gateway' => $gateway,
                    'amount' => $locked->grand_total,
                    'currency' => $locked->currency,
                    'status' => 'pending',
                ]);
            }

            $payment->update([
                'status' => 'succeeded',
                'gateway_payment_id' => $reference ?: $payment->gateway_payment_id,
                'payload' => $payload ?: $payment->payload,
                'paid_at' => now(),
            ]);

            $fromStatus = $locked->status->value;
            $wasCancelled = $locked->status === OrderStatus::Cancelled;

            // A failed attempt returns the stock to the shelf. If the shopper
            // then pays on the same order, it has to be reserved again so the
            // paid order is never oversold.
            $needsStock = $wasCancelled
                || $locked->status === OrderStatus::Failed
                || $locked->payment_status === PaymentStatus::Failed;

            $locked->update([
                'payment_status' => PaymentStatus::Paid,
                'paid_total' => $locked->grand_total,
                'paid_at' => now(),
                'cancelled_at' => $wasCancelled ? null : $locked->cancelled_at,
                'transaction_id' => $reference ?: $locked->transaction_id,
                'status' => $needsStock || $locked->status === OrderStatus::Pending
                    ? OrderStatus::Processing
                    : $locked->status,
            ]);

            if ($needsStock) {
                $this->reserveForPaidOrder($locked);
            }

            OrderStatusHistory::query()->create([
                'order_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => $locked->status->value,
                'note' => match (true) {
                    $wasCancelled => 'Payment received via '.$gateway.' after cancellation. Order reopened.',
                    $needsStock => 'Payment received via '.$gateway.' after a failed attempt. Order reopened.',
                    default => 'Payment received via '.$gateway.'.',
                },
            ]);

            app(SubscriptionService::class)->activateForOrder($locked, $gateway, $gatewaySubscriptionId);

            return true;
        });

        if ($changed) {
            $fresh = $order->fresh();

            app(\App\Services\Marketplace\VendorSettlementService::class)->onOrderPaid($fresh);

            $this->sendOrderEmails($fresh);
        }
    }

    public function markFailed(Order $order, string $gateway, array $payload = []): void
    {
        DB::transaction(function () use ($order, $gateway, $payload): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || $locked->isPaid() || $locked->status === OrderStatus::Cancelled) {
                return;
            }

            if ($locked->payment_status === PaymentStatus::Failed) {
                return;
            }

            $pending = $locked->payments()
                ->where('gateway', $gateway)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($pending) {
                $pending->update(['status' => 'failed', 'payload' => $payload ?: $pending->payload]);
            } else {
                $locked->payments()->create([
                    'gateway' => $gateway,
                    'amount' => $locked->grand_total,
                    'currency' => $locked->currency,
                    'status' => 'failed',
                    'payload' => $payload,
                ]);
            }

            $fromStatus = $locked->status->value;
            $locked->update([
                'payment_status' => PaymentStatus::Failed,
                'status' => OrderStatus::Failed,
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => 'failed',
                'note' => 'Payment failed via '.$gateway.'.',
            ]);

            $this->restoreStock($locked, 'Payment failed');
        });
    }

    /**
     * A hosted checkout can be completed after the abandonment task cancelled
     * the order, or after a declined attempt returned the stock. The money was
     * captured, so put the stock back on hold where possible and leave a note
     * when it has since sold out.
     */
    private function reserveForPaidOrder(Order $order): void
    {
        $shortfalls = [];

        foreach ($order->items()->with('product')->get() as $item) {
            $product = $item->product;

            if (! $product?->manage_stock) {
                continue;
            }

            $quantity = (int) $item->quantity;

            if (! $this->takeStock($order, $product->id, $item->variant_id, $quantity, 'Re-reserved after a late payment for '.$order->number)) {
                $shortfalls[] = $item->product_name.' × '.$quantity;
            }
        }

        if ($shortfalls !== []) {
            OrderNote::query()->create([
                'order_id' => $order->id,
                'user_id' => null,
                'note' => 'Payment arrived after cancellation and stock could not be re-reserved for: '.implode(', ', $shortfalls).'. Fulfil manually or refund.',
                'is_customer_visible' => false,
            ]);

            Log::warning('Late payment reopened a cancelled order without enough stock', [
                'order' => $order->number,
                'items' => $shortfalls,
            ]);
        }
    }

    public function cancel(Order $order, ?User $actor = null, string $reason = 'Order cancelled.'): void
    {
        if (! $order->canBeCancelled()) {
            throw ValidationException::withMessages(['order' => 'This order can no longer be cancelled.']);
        }

        DB::transaction(function () use ($order, $actor, $reason) {
            // Re-read under a lock: two simultaneous cancellations would
            // otherwise both pass the check above and restore stock twice.
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->canBeCancelled()) {
                throw ValidationException::withMessages(['order' => 'This order has already been cancelled or completed.']);
            }

            $fromStatus = $order->status->value;

            $this->restoreStock($order, 'Order cancelled');
            $this->releaseCoupon($order);

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'user_id' => $actor?->id,
                'from_status' => $fromStatus,
                'to_status' => 'cancelled',
                'note' => $reason,
            ]);
        });
    }

    public function updateStatus(Order $order, OrderStatus $status, ?User $actor = null, ?string $note = null): void
    {
        $fromStatus = $order->status->value;

        if ($status === OrderStatus::Cancelled && $fromStatus !== OrderStatus::Cancelled) {
            $this->cancel($order, $actor, $note ?: 'Order cancelled.');

            return;
        }

        $order->update(['status' => $status]);

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'user_id' => $actor?->id,
            'from_status' => $fromStatus,
            'to_status' => $status->value,
            'note' => $note,
        ]);
    }

    public function addNote(Order $order, string $note, bool $customerVisible = false, ?User $actor = null): OrderNote
    {
        return $order->notes()->create([
            'user_id' => $actor?->id,
            'note' => $note,
            'is_customer_visible' => $customerVisible,
        ]);
    }

    private function reserveStock(Order $order, CartItem $item): void
    {
        $product = $item->product;

        if (! $product?->manage_stock) {
            return;
        }

        if (! $this->takeStock($order, $product->id, $item->variant_id, (int) $item->quantity, 'Reserved for '.$order->number)) {
            throw ValidationException::withMessages([
                'cart' => $product->name.' sold out while you were checking out. Reduce the quantity and try again.',
            ]);
        }
    }

    /**
     * Check and decrement in the same statement. Two simultaneous checkouts
     * for the last unit would both pass a read-then-write check, so the WHERE
     * clause is what keeps stock from ever going negative.
     */
    private function takeStock(Order $order, ?int $productId, ?int $variantId, int $quantity, string $note): bool
    {
        $affected = $variantId
            ? ProductVariant::query()
                ->whereKey($variantId)
                ->where('stock_quantity', '>=', $quantity)
                ->decrement('stock_quantity', $quantity)
            : Product::query()
                ->whereKey($productId)
                ->where('stock_quantity', '>=', $quantity)
                ->decrement('stock_quantity', $quantity);

        if ($affected === 0) {
            return false;
        }

        InventoryMovement::query()->create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'user_id' => Auth::id(),
            'order_id' => $order->id,
            'type' => 'sale',
            'quantity' => -$quantity,
            'note' => $note,
        ]);

        return true;
    }

    private function restoreStock(Order $order, string $reason): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            if (! $product?->manage_stock) {
                continue;
            }

            if ($variant) {
                $variant->increment('stock_quantity', $item->quantity);
            } else {
                $product->increment('stock_quantity', $item->quantity);
            }

            InventoryMovement::query()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'order_id' => $order->id,
                'type' => 'return',
                'quantity' => $item->quantity,
                'note' => $reason,
            ]);
        }
    }

    private function releaseCoupon(Order $order): void
    {
        if (! $order->coupon_id) {
            return;
        }

        $usage = CouponUsage::query()->where('order_id', $order->id)->first();
        if ($usage) {
            $usage->delete();
            Coupon::query()->whereKey($order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
        }
    }

    private function assertItemStock(CartItem $item): void
    {
        $product = $item->product;

        if (! $product || ! $product->manage_stock) {
            return;
        }

        if ($product->stockFor($item->variant) < $item->quantity) {
            throw ValidationException::withMessages([
                'cart' => $product->name.' no longer has enough stock. Reduce the quantity and try again.',
            ]);
        }
    }

    private function nextNumber(): string
    {
        $prefix = strtoupper((string) nexus_setting('checkout.order_prefix', 'NC'));

        do {
            $number = $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }

    private function sendOrderEmails(Order $order): void
    {
        try {
            Mail::to($order->customer_email)->send(new OrderConfirmation($order));

            $adminEmail = (string) nexus_setting('store.email', '');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new AdminNewOrder($order));
            }
        } catch (Throwable) {
            // A mail outage must not roll back a successful order.
        }
    }
}
