<?php

namespace Tests\Feature;

use App\Enums\StoreMode;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerTier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductModeState;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\StoreModeSwitch;
use App\Models\TaxRate;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSplit;
use App\Services\CartService;
use App\Services\Marketplace\VendorSplitService;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Services\SettingsService;
use App\Services\StoreModeService;
use App\Services\StoreModeSwitchException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class StoreModeSwitchingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings()->setMany([
            'store.name' => 'Switch Test',
            'store.currency' => 'USD',
            'store.currency_symbol' => '$',
            'store.country' => 'US',
            'store.live' => true,
            'store.mode' => 'retail',
            'store.mode_revision' => 0,
            'store.setup_completed' => true,
            'payments.stripe.enabled' => false,
            'payments.paypal.enabled' => false,
            'payments.manual.enabled' => true,
            'subscriptions.auto_renew' => true,
            'marketplace.commission_type' => 'percentage',
            'marketplace.commission_percent' => 10,
            'marketplace.payout_hold_days' => 7,
        ]);

        $this->settings()->flushCache();
    }

    public function test_every_business_model_transition_is_atomic_and_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(StoreModeService::class);
        $revision = 0;

        foreach (StoreMode::cases() as $from) {
            foreach (StoreMode::cases() as $to) {
                if ($from === $to) {
                    continue;
                }

                $this->settings()->setMode($from);
                $this->settings()->flushCache();

                $switch = $service->switchTo($to, $admin, confirmed: true, live: true);
                $revision++;

                $this->assertSame($to, $this->settings()->mode());
                $this->assertSame($revision, $this->settings()->modeRevision());
                $this->assertSame($from->value, $switch->from_mode);
                $this->assertSame($to->value, $switch->to_mode);
                $this->assertSame($revision, $switch->mode_revision);
            }
        }

        $this->assertSame(12, StoreModeSwitch::query()->count());
        $this->assertSame(12, $this->settings()->modeRevision());
    }

    public function test_catalog_queries_enforce_product_compatibility(): void
    {
        $vendor = $this->vendor();
        $storeProduct = $this->product('Store item', 10);
        $wholesaleOnly = $this->product('Trade case', 50, ['is_wholesale_only' => true]);
        $subscription = $this->product('Coffee box', 25);
        $plan = $this->plan($subscription);
        $sellerProduct = $this->product('Seller item', 30, ['vendor_id' => $vendor->id]);

        $this->assertSame(
            [$storeProduct->id],
            Product::query()->availableInMode(StoreMode::Retail)->pluck('id')->all()
        );

        $this->assertEqualsCanonicalizing(
            [$storeProduct->id, $wholesaleOnly->id],
            Product::query()->availableInMode(StoreMode::B2B)->pluck('id')->all()
        );

        $this->assertEqualsCanonicalizing(
            [$storeProduct->id, $subscription->id],
            Product::query()->availableInMode(StoreMode::Subscription)->pluck('id')->all()
        );

        $this->assertEqualsCanonicalizing(
            [$storeProduct->id, $sellerProduct->id],
            Product::query()->availableInMode(StoreMode::MultiVendor)->pluck('id')->all()
        );

        $this->assertTrue($subscription->fresh()->supportsMode(StoreMode::Subscription));
        $this->assertFalse($subscription->fresh()->supportsMode(StoreMode::Retail));
        $this->assertFalse($sellerProduct->fresh()->supportsMode(StoreMode::Retail));
        $this->assertTrue($sellerProduct->fresh()->supportsMode(StoreMode::MultiVendor));
        $this->assertNotEmpty($plan->id);
    }

    public function test_cart_rejects_products_from_another_business_model(): void
    {
        $vendor = $this->vendor();
        $sellerProduct = $this->product('Seller item', 30, ['vendor_id' => $vendor->id]);

        $this->expectException(ValidationException::class);
        app(CartService::class)->add($sellerProduct, 1);
    }

    public function test_subscription_products_cannot_silently_become_one_time_purchases(): void
    {
        $this->settings()->setMode(StoreMode::Subscription);
        $this->settings()->flushCache();

        $product = $this->product('Coffee box', 25);
        $plan = $this->plan($product);

        try {
            app(CartService::class)->add($product, 1);
            $this->fail('A subscription product was added without a plan.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }

        $item = app(CartService::class)->add($product, 1, null, $plan);

        $this->assertSame($plan->id, $item->subscription_plan_id);
        $this->assertSame(25.0, (float) $item->unit_price);
    }

    public function test_wholesale_prices_only_apply_in_b2b_mode(): void
    {
        $tier = CustomerTier::query()->create([
            'name' => 'Trade A',
            'slug' => 'trade-a',
            'discount_percent' => 10,
            'is_wholesale' => true,
            'priority' => 1,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'customer',
            'tier_id' => $tier->id,
            'wholesale_status' => 'approved',
        ]);

        $product = $this->product('Trade item', 100);

        $this->assertSame(100.0, $product->priceFor($user, null, 6, StoreMode::Retail));
        $this->assertSame(90.0, $product->priceFor($user, null, 6, StoreMode::B2B));
        $this->assertSame(100.0, $product->priceFor($user, null, 6, StoreMode::MultiVendor));
    }

    public function test_empty_target_catalog_is_a_warning_not_a_blocker(): void
    {
        $report = app(StoreModeService::class)->report(StoreMode::B2B);

        $this->assertSame([], $report['blockers']);
        $this->assertContains('empty_catalog', array_column($report['warnings'], 'code'));
        $this->assertTrue($report['can_switch']);
    }

    public function test_failed_reconciliation_rolls_back_catalog_and_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $subscription = $this->product('Coffee box', 25);
        $this->plan($subscription);

        $this->mock(CartService::class, function ($mock): void {
            $mock->shouldReceive('reconcileAll')->once()->andThrow(new RuntimeException('Simulated cart failure'));
        });

        try {
            app(StoreModeService::class)->switchTo(StoreMode::B2B, $admin, confirmed: true, live: true);
            $this->fail('The simulated failure should have escaped the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated cart failure', $exception->getMessage());
        }

        $this->assertSame(StoreMode::Retail, $this->settings()->mode());
        $this->assertSame(0, $this->settings()->modeRevision());
        $this->assertDatabaseCount('store_mode_switches', 0);
        $this->assertDatabaseCount('product_mode_states', 0);
    }

    public function test_switch_archives_incompatible_products_and_preserves_manual_hidden_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(StoreModeService::class);
        $storeProduct = $this->product('Store item', 10);
        $subscription = $this->product('Coffee box', 25);
        $this->plan($subscription);

        $service->setManualVisibility($storeProduct, StoreMode::Retail, true);

        $service->switchTo(StoreMode::B2B, $admin, confirmed: true, live: true);

        $this->assertDatabaseHas('product_mode_states', [
            'product_id' => $subscription->id,
            'mode' => 'b2b',
            'is_visible' => false,
            'origin' => 'system',
        ]);
        $this->assertDatabaseHas('product_mode_states', [
            'product_id' => $storeProduct->id,
            'mode' => 'retail',
            'is_visible' => false,
            'origin' => 'manual',
        ]);

        $service->switchTo(StoreMode::Retail, $admin, confirmed: true, live: true);

        $state = ProductModeState::query()
            ->where('product_id', $storeProduct->id)
            ->where('mode', 'retail')
            ->firstOrFail();

        $this->assertFalse($state->is_visible);
        $this->assertSame(ProductModeState::ORIGIN_MANUAL, $state->origin);
        $this->assertSame(404, $this->get(route('storefront.product', $storeProduct->slug))->status());
    }

    public function test_stale_carts_are_repriced_and_incompatible_lines_are_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = $this->vendor();
        $sellerProduct = $this->product('Seller item', 30, ['vendor_id' => $vendor->id]);
        $storeProduct = $this->product('Store item', 10);

        $this->settings()->setMode(StoreMode::MultiVendor);
        $this->settings()->flushCache();

        $cart = app(CartService::class)->current();
        app(CartService::class)->add($sellerProduct, 1);
        app(CartService::class)->add($storeProduct, 1);

        $storeProduct->update(['price' => 20]);

        app(StoreModeService::class)->switchTo(StoreMode::B2B, $admin, confirmed: true, live: true);

        $cart->refresh();
        $cart->load('items.product');

        $this->assertSame('b2b', $cart->mode);
        $this->assertSame(1, $cart->mode_revision);
        $this->assertCount(1, $cart->items);
        $this->assertSame($storeProduct->id, $cart->items->first()->product_id);
        $this->assertSame(20.0, (float) $cart->items->first()->unit_price);
    }

    public function test_historical_orders_and_seller_obligations_survive_a_switch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = $this->vendor();
        $product = $this->product('Seller item', 100, ['vendor_id' => $vendor->id]);
        $order = $this->order(StoreMode::MultiVendor, $product, $vendor);
        $split = app(VendorSplitService::class)->createForOrder($order)->firstOrFail();

        $this->settings()->setMode(StoreMode::MultiVendor);
        $this->settings()->flushCache();

        app(StoreModeService::class)->switchTo(StoreMode::Retail, $admin, confirmed: true, live: true);

        $order->refresh();
        $split->refresh();

        $this->assertSame(StoreMode::MultiVendor, $order->mode);
        $this->assertSame(100.0, (float) $order->subtotal);
        $this->assertSame($vendor->id, $split->vendor_id);
        $this->assertSame(VendorSplit::STATUS_PENDING, $split->status);
        $this->assertSame(90.0, $split->netPayout());
    }

    public function test_subscription_orders_keep_product_shipping_and_configured_tax(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->product('Coffee box', 25, ['requires_shipping' => true, 'weight' => 1]);
        $plan = $this->plan($product);
        $zone = ShippingZone::query()->create([
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        ShippingMethod::query()->create([
            'zone_id' => $zone->id,
            'name' => 'Ground',
            'type' => 'flat_rate',
            'cost' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        TaxRate::query()->create([
            'name' => 'Sales tax',
            'country' => 'US',
            'rate' => 10,
            'is_active' => true,
            'priority' => 1,
        ]);

        $this->settings()->setMode(StoreMode::Subscription);
        $this->settings()->flushCache();
        $this->actingAs($customer);

        app(CartService::class)->add($product, 1, null, $plan);
        $cart = app(CartService::class)->current();
        $totals = app(CartService::class)->totals($cart, ['country' => 'US', 'state' => 'IL']);

        $this->assertSame(5.0, $totals['shipping']);
        $this->assertSame(2.5, $totals['tax']);
        $this->assertSame(32.5, $totals['total']);

        $order = app(OrderService::class)->createFromCart($cart, [
            'customer_name' => 'Sam Buyer',
            'customer_email' => 'sam@example.test',
            'shipping_address' => [
                'name' => 'Sam Buyer',
                'line1' => '1 High Street',
                'city' => 'Springfield',
                'state' => 'IL',
                'postcode' => '62701',
                'country' => 'US',
            ],
            'payment_method' => 'manual',
        ]);

        $this->assertSame(5.0, (float) $order->shipping_total);
        $this->assertSame(2.5, (float) $order->tax_total);
        $this->assertSame(32.5, (float) $order->grand_total);
    }

    public function test_refunds_and_seller_ledger_still_work_after_leaving_multi_vendor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = $this->vendor();
        $product = $this->product('Seller item', 100, ['vendor_id' => $vendor->id]);
        $order = $this->order(StoreMode::MultiVendor, $product, $vendor);
        $order->update([
            'status' => 'completed',
            'payment_status' => 'paid',
            'paid_total' => 100,
            'paid_at' => now(),
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'manual',
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);
        app(VendorSplitService::class)->createForOrder($order);

        $this->settings()->setMode(StoreMode::MultiVendor);
        $this->settings()->flushCache();
        app(StoreModeService::class)->switchTo(StoreMode::Retail, $admin, confirmed: true, live: true);

        app(RefundService::class)->refund($order->fresh(), 20, 'Return', $admin, 'mode-switch-refund');

        $order->refresh();
        $split = VendorSplit::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame(StoreMode::MultiVendor, $order->mode);
        $this->assertSame(20.0, (float) $order->refunded_total);
        $this->assertGreaterThan(0, (float) $split->refunded_total);
        $this->assertLessThan(100.0, $split->netPayout());
    }

    public function test_switch_is_blocked_when_active_subscriptions_have_no_renewal_gateway(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->product('Coffee box', 25);
        $plan = $this->plan($product);
        $order = $this->order(StoreMode::Subscription, $product);

        Subscription::query()->create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'subscription_plan_id' => $plan->id,
            'gateway' => 'stripe',
            'status' => 'active',
            'quantity' => 1,
            'price' => 25,
            'currency' => 'USD',
            'interval' => 'month',
            'interval_count' => 1,
            'next_billing_at' => now()->addMonth(),
        ]);

        $this->settings()->setMode(StoreMode::Subscription);
        $this->settings()->flushCache();

        try {
            app(StoreModeService::class)->switchTo(StoreMode::Retail, $admin, confirmed: true, live: true);
            $this->fail('The switch should have been blocked.');
        } catch (StoreModeSwitchException $exception) {
            $this->assertSame('subscription_gateway_missing', $exception->report['blockers'][0]['code']);
        }

        $this->assertSame(StoreMode::Subscription, $this->settings()->mode());
        $this->assertSame(0, $this->settings()->modeRevision());
        $this->assertDatabaseCount('store_mode_switches', 0);
    }

    public function test_switch_is_blocked_when_a_seller_has_no_payout_destination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = $this->vendor(['payout_method' => 'stripe']);
        $product = $this->product('Seller item', 100, ['vendor_id' => $vendor->id]);
        $order = $this->order(StoreMode::MultiVendor, $product, $vendor);
        app(VendorSplitService::class)->createForOrder($order);

        $this->settings()->setMode(StoreMode::MultiVendor);
        $this->settings()->flushCache();

        $this->expectException(StoreModeSwitchException::class);

        app(StoreModeService::class)->switchTo(StoreMode::Retail, $admin, confirmed: true, live: true);
    }

    public function test_direct_storefront_routes_reject_products_from_another_model(): void
    {
        $vendor = $this->vendor();
        $sellerProduct = $this->product('Seller item', 30, ['vendor_id' => $vendor->id]);
        $subscription = $this->product('Coffee box', 25);
        $plan = $this->plan($subscription);

        $this->get(route('storefront.product', $sellerProduct->slug))->assertNotFound();
        $this->get(route('storefront.product', $subscription->slug))->assertNotFound();

        $this->post(route('storefront.cart.add'), ['product_id' => $sellerProduct->id])
            ->assertNotFound();

        $this->post(route('storefront.cart.add'), [
            'product_id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
        ])->assertNotFound();

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_direct_checkout_with_a_forged_cross_model_cart_line_is_rejected(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $subscription = $this->product('Coffee box', 25);
        $plan = $this->plan($subscription);

        $cart = Cart::query()->create([
            'user_id' => $customer->id,
            'currency' => 'USD',
            'mode' => StoreMode::Retail->value,
            'mode_revision' => 0,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
            'quantity' => 1,
            'unit_price' => 25,
        ]);

        $this->actingAs($customer)
            ->from(route('storefront.cart'))
            ->post(route('storefront.checkout.place'), [
                'customer_name' => 'Sam Buyer',
                'customer_email' => 'sam@example.test',
                'line1' => '1 High Street',
                'city' => 'Springfield',
                'postcode' => '62701',
                'country' => 'US',
                'payment_method' => 'manual',
                'terms' => '1',
            ])
            ->assertRedirect(route('storefront.cart'))
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_reconcile_flags_a_stale_revision_and_reprices_before_payment(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->product('Store item', 10);

        $cart = Cart::query()->create([
            'user_id' => $customer->id,
            'currency' => 'USD',
            'mode' => StoreMode::B2B->value,
            'mode_revision' => 0,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10,
        ]);

        $product->update(['price' => 18]);
        $this->settings()->setMode(StoreMode::B2B);
        $this->settings()->setModeRevision(1);
        $this->settings()->flushCache();

        $summary = app(CartService::class)->reconcileCart($cart->fresh(), StoreMode::B2B, 1);

        $this->assertTrue($summary['stale']);
        $this->assertTrue($summary['repriced']);
        $this->assertTrue($summary['invalidated']);
        $this->assertSame(18.0, (float) $cart->fresh()->items()->first()->unit_price);
    }

    public function test_vendor_sale_commits_with_its_ledger_snapshot(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = $this->vendor();
        $product = $this->product('Seller item', 100, ['vendor_id' => $vendor->id]);

        $this->settings()->setMode(StoreMode::MultiVendor);
        $this->settings()->flushCache();
        $this->actingAs($customer);

        app(CartService::class)->add($product, 1);
        $cart = app(CartService::class)->current();

        $order = app(OrderService::class)->createFromCart($cart, [
            'customer_name' => 'Sam Buyer',
            'customer_email' => 'sam@example.test',
            'shipping_address' => [
                'name' => 'Sam Buyer',
                'line1' => '1 High Street',
                'city' => 'Springfield',
                'postcode' => '62701',
                'country' => 'US',
            ],
            'payment_method' => 'manual',
        ]);

        $split = VendorSplit::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame($vendor->id, $split->vendor_id);
        $this->assertSame(VendorSplit::STATUS_PENDING, $split->status);
        $this->assertSame(90.0, $split->netPayout());
        $this->assertSame($vendor->id, $order->items()->first()->vendor_id);
    }

    public function test_suspended_vendor_blocks_checkout_and_leaves_no_order(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = $this->vendor();
        $product = $this->product('Seller item', 100, ['vendor_id' => $vendor->id]);

        $this->settings()->setMode(StoreMode::MultiVendor);
        $this->settings()->flushCache();
        $this->actingAs($customer);

        app(CartService::class)->add($product, 1);
        $cart = app(CartService::class)->current();

        $vendor->update(['status' => Vendor::STATUS_SUSPENDED]);

        try {
            app(OrderService::class)->createFromCart($cart, [
                'customer_name' => 'Sam Buyer',
                'customer_email' => 'sam@example.test',
                'shipping_address' => [
                    'name' => 'Sam Buyer',
                    'line1' => '1 High Street',
                    'city' => 'Springfield',
                    'postcode' => '62701',
                    'country' => 'US',
                ],
                'payment_method' => 'manual',
            ]);

            $this->fail('A suspended seller product was ordered.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('vendor_splits', 0);
    }

    public function test_seller_history_and_payouts_stay_available_after_a_model_switch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['role' => 'customer']);
        $vendor = $this->vendor(['user_id' => $seller->id]);
        $product = $this->product('Seller item', 100, ['vendor_id' => $vendor->id]);
        $order = $this->order(StoreMode::MultiVendor, $product, $vendor);
        $split = app(VendorSplitService::class)->createForOrder($order)->firstOrFail();
        $split->update([
            'status' => VendorSplit::STATUS_HELD,
            'available_at' => now()->subDay(),
            'payout_total' => 90,
        ]);

        $this->settings()->setMode(StoreMode::MultiVendor);
        $this->settings()->flushCache();
        app(StoreModeService::class)->switchTo(StoreMode::Retail, $admin, confirmed: true, live: true);

        $this->actingAs($seller)
            ->get(route('vendor.orders.index'))
            ->assertOk();

        $this->actingAs($seller)
            ->get(route('vendor.payouts.index'))
            ->assertOk()
            ->assertSee('Payouts');

        $this->actingAs($admin)
            ->post(route('admin.vendors.payout', $vendor))
            ->assertRedirect();

        $this->assertSame(VendorSplit::STATUS_PAID, $split->fresh()->status);
        $this->assertNotNull($split->fresh()->paid_at);
    }

    public function test_admin_can_preview_and_switch_with_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->product('Store item', 10);

        $this->actingAs($admin)
            ->get(route('admin.store-mode.preflight', ['mode' => 'b2b', 'live' => 1]))
            ->assertOk()
            ->assertSee('Confirm switch')
            ->assertSee('Catalog impact');

        $this->actingAs($admin)
            ->post(route('admin.store-mode.switch'), ['mode' => 'b2b', 'live' => 1])
            ->assertSessionHasErrors('confirm');

        $this->actingAs($admin)
            ->post(route('admin.store-mode.switch'), [
                'mode' => 'b2b',
                'live' => 1,
                'confirm' => 1,
                'note' => 'Trade launch',
            ])
            ->assertRedirect(route('admin.settings.index', ['section' => 'mode']));

        $this->assertSame(StoreMode::B2B, $this->settings()->mode());
        $this->assertDatabaseHas('store_mode_switches', ['to_mode' => 'b2b', 'note' => 'Trade launch']);
    }

    private function settings(): SettingsService
    {
        return app(SettingsService::class);
    }

    private function vendor(array $attributes = []): Vendor
    {
        static $counter = 0;
        $counter++;

        return Vendor::query()->create(array_merge([
            'name' => 'Seller '.$counter,
            'slug' => 'seller-'.$counter,
            'email' => 'seller'.$counter.'@example.test',
            'status' => Vendor::STATUS_APPROVED,
            'payout_method' => 'manual',
        ], $attributes));
    }

    private function product(string $name, float $price, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(6),
            'type' => 'simple',
            'price' => $price,
            'manage_stock' => false,
            'stock_quantity' => 100,
            'is_active' => true,
            'requires_shipping' => false,
            'published_at' => now(),
        ], $attributes));
    }

    private function plan(Product $product): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create([
            'product_id' => $product->id,
            'name' => 'Monthly',
            'interval' => 'month',
            'interval_count' => 1,
            'price' => $product->price,
            'trial_days' => 0,
            'is_active' => true,
        ]);
    }

    private function order(StoreMode $mode, Product $product, ?Vendor $vendor = null): Order
    {
        $order = Order::query()->create([
            'number' => 'SW-'.str()->upper(str()->random(8)),
            'access_token' => str()->random(32),
            'status' => 'pending',
            'payment_status' => 'pending',
            'currency' => 'USD',
            'subtotal' => $product->price,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $product->price,
            'customer_email' => 'buyer@example.test',
            'customer_name' => 'Sam Buyer',
            'shipping_address' => [
                'name' => 'Sam Buyer',
                'line1' => '1 High Street',
                'city' => 'Springfield',
                'postcode' => '12345',
                'country' => 'US',
            ],
            'mode' => $mode,
            'mode_revision' => 0,
            'placed_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'vendor_id' => $vendor?->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $product->price,
            'line_subtotal' => $product->price,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => $product->price,
            'is_subscription' => $mode === StoreMode::Subscription,
        ]);

        return $order->load('items.product.vendor');
    }
}
