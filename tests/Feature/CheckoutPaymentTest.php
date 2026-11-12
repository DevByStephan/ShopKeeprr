<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->setMany([
            'store.name' => 'Test Store',
            'store.email' => 'owner@example.test',
            'store.live' => true,
            'store.mode' => 'retail',
            'store.setup_completed' => true,
            'payments.manual.enabled' => true,
            'payments.stripe.enabled' => false,
            'payments.paypal.enabled' => false,
            'tax.prices_include_tax' => false,
            'tax.shipping_taxable' => false,
        ]);

        $this->product = Product::query()->create([
            'name' => 'Test Mug',
            'slug' => 'test-mug',
            'type' => 'simple',
            'price' => 25,
            'manage_stock' => false,
            'stock_quantity' => 500,
            'is_active' => true,
            'requires_shipping' => true,
        ]);
    }

    public function test_checkout_page_lists_only_the_enabled_gateways(): void
    {
        $this->addToCart(2)->assertRedirect();

        $response = $this->get(route('storefront.checkout'));

        $response->assertOk();
        $response->assertSee('Pay by invoice');
        $response->assertSee('value="manual"', false);
        $response->assertDontSee('value="stripe"', false);
        $response->assertDontSee('value="paypal"', false);
    }

    public function test_manual_checkout_creates_a_pending_order_and_payment(): void
    {
        $this->addToCart(2)->assertRedirect();

        $response = $this->post(route('storefront.checkout.place'), $this->payload());

        $order = Order::query()->firstOrFail();

        $response->assertRedirect($order->confirmationUrl());
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame('manual', $order->payment_method);
        $this->assertSame(50.0, (float) $order->grand_total);
        $this->assertSame('test@example.test', $order->customer_email);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway' => 'manual',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_stripe_checkout_redirects_to_the_provider_session(): void
    {
        app(SettingsService::class)->setMany([
            'payments.stripe.enabled' => true,
            'payments.stripe.secret_key' => 'sk_test_example',
        ]);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            ]),
        ]);

        $this->addToCart(1)->assertRedirect();

        $this->post(route('storefront.checkout.place'), $this->payload(['payment_method' => 'stripe']))
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

        $order = Order::query()->firstOrFail();

        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway' => 'stripe',
            'gateway_reference' => 'cs_test_123',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request['cancel_url'], '/checkout')
            && str_contains($request['success_url'], '/checkout/confirmation/'.$order->number));
    }

    public function test_paypal_checkout_redirects_to_the_approval_link(): void
    {
        app(SettingsService::class)->setMany([
            'payments.paypal.enabled' => true,
            'payments.paypal.client_id' => 'paypal-client',
            'payments.paypal.client_secret' => 'paypal-secret',
            'payments.paypal.mode' => 'sandbox',
        ]);

        Cache::flush();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21-fake', 'expires_in' => 3600]),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAY-123',
                'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAY-123']],
            ]),
        ]);

        $this->addToCart(1)->assertRedirect();

        $this->post(route('storefront.checkout.place'), $this->payload(['payment_method' => 'paypal']))
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PAY-123');

        $order = Order::query()->firstOrFail();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway' => 'paypal',
            'gateway_reference' => 'PAY-123',
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
            && $request['application_context']['cancel_url'] === url('/checkout'));
    }

    public function test_stripe_completion_marks_a_matching_session_paid(): void
    {
        app(SettingsService::class)->setMany([
            'payments.manual.enabled' => false,
            'payments.stripe.enabled' => true,
            'payments.stripe.secret_key' => 'sk_test_example',
        ]);

        $order = null;

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_stripe_ok',
                'url' => 'https://checkout.stripe.com/c/pay/cs_stripe_ok',
            ]),
            'api.stripe.com/v1/checkout/sessions/cs_stripe_ok' => function () use (&$order) {
                return Http::response([
                    'id' => 'cs_stripe_ok',
                    'payment_status' => 'paid',
                    'status' => 'complete',
                    'amount_total' => (int) round($order->grand_total * 100),
                    'currency' => strtolower($order->currency),
                    'metadata' => ['order_id' => (string) $order->id],
                    'payment_intent' => 'pi_stripe_ok',
                ]);
            },
        ]);

        $this->addToCart(1)->assertRedirect();
        $this->post(route('storefront.checkout.place'), $this->payload(['payment_method' => 'stripe']))
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_stripe_ok');

        $order = Order::query()->latest('id')->firstOrFail();

        $this->get($order->confirmationUrl().'&gateway=stripe&session_id=cs_stripe_ok')->assertOk();

        $order->refresh();

        $this->assertTrue($order->isPaid());
        $this->assertSame('pi_stripe_ok', $order->payments()->where('gateway', 'stripe')->first()->gateway_payment_id);
    }

    public function test_stripe_completion_rejects_a_session_with_the_wrong_amount(): void
    {
        app(SettingsService::class)->setMany([
            'payments.manual.enabled' => false,
            'payments.stripe.enabled' => true,
            'payments.stripe.secret_key' => 'sk_test_example',
        ]);

        $order = null;

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_stripe_wrong',
                'url' => 'https://checkout.stripe.com/c/pay/cs_stripe_wrong',
            ]),
            'api.stripe.com/v1/checkout/sessions/cs_stripe_wrong' => function () use (&$order) {
                return Http::response([
                    'id' => 'cs_stripe_wrong',
                    'payment_status' => 'paid',
                    'status' => 'complete',
                    'amount_total' => max(1, (int) round($order->grand_total * 100) - 100),
                    'currency' => strtolower($order->currency),
                    'metadata' => ['order_id' => (string) $order->id],
                    'payment_intent' => 'pi_stripe_wrong',
                ]);
            },
        ]);

        $this->addToCart(1)->assertRedirect();
        $this->post(route('storefront.checkout.place'), $this->payload(['payment_method' => 'stripe']))
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_stripe_wrong');

        $order = Order::query()->latest('id')->firstOrFail();

        $this->get($order->confirmationUrl().'&gateway=stripe&session_id=cs_stripe_wrong')->assertOk();

        $order->refresh();

        $this->assertFalse($order->isPaid());
        $this->assertSame(0.0, (float) $order->paid_total);
    }

    public function test_paypal_capture_rejects_a_tampered_amount(): void
    {
        app(SettingsService::class)->setMany([
            'payments.paypal.enabled' => true,
            'payments.paypal.client_id' => 'paypal-client',
            'payments.paypal.client_secret' => 'paypal-secret',
            'payments.paypal.mode' => 'sandbox',
        ]);

        Cache::flush();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21-fake', 'expires_in' => 3600]),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-WRONG',
                'status' => 'CREATED',
                'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-WRONG']],
            ]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-WRONG/capture' => Http::response([
                'id' => 'PAYPAL-WRONG',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'amount' => ['value' => '1.00', 'currency_code' => 'USD'],
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAPTURE-WRONG',
                            'status' => 'COMPLETED',
                            'amount' => ['value' => '1.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ], 201),
        ]);

        $this->addToCart(1)->assertRedirect();
        $this->post(route('storefront.checkout.place'), $this->payload(['payment_method' => 'paypal']))
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-WRONG');

        $order = Order::query()->latest('id')->firstOrFail();

        $this->get($order->confirmationUrl().'&gateway=paypal&token=PAYPAL-WRONG')->assertOk();

        $order->refresh();

        $this->assertFalse($order->isPaid());
        $this->assertSame(0.0, (float) $order->paid_total);
    }

    public function test_paypal_return_captures_the_order_and_marks_it_paid(): void
    {
        app(SettingsService::class)->setMany([
            'payments.paypal.enabled' => true,
            'payments.paypal.client_id' => 'paypal-client',
            'payments.paypal.client_secret' => 'paypal-secret',
            'payments.paypal.mode' => 'sandbox',
        ]);

        Cache::flush();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21-fake', 'expires_in' => 3600]),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER',
                'status' => 'CREATED',
                'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER']],
            ]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER/capture' => Http::response([
                'id' => 'PAYPAL-ORDER',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAPTURE-123',
                            'status' => 'COMPLETED',
                            'amount' => ['value' => '25.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ], 201),
        ]);

        $this->addToCart(1)->assertRedirect();

        $this->post(route('storefront.checkout.place'), $this->payload(['payment_method' => 'paypal']))
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER');

        $order = Order::query()->firstOrFail();

        $this->get($order->confirmationUrl().'&gateway=paypal&token=PAYPAL-ORDER')->assertOk();

        $order->refresh();

        $this->assertTrue($order->isPaid());
        $this->assertSame('CAPTURE-123', $order->payments()->where('gateway', 'paypal')->first()->gateway_payment_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/checkout/orders/PAYPAL-ORDER/capture')
            && $request->body() === '{}');
    }

    public function test_checkout_rejects_a_gateway_that_is_not_enabled(): void
    {
        $this->addToCart(1)->assertRedirect();

        $this->post(route('storefront.checkout.place'), $this->payload(['payment_method' => 'stripe']))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_requires_terms_acceptance(): void
    {
        $this->addToCart(1)->assertRedirect();

        $this->post(route('storefront.checkout.place'), $this->payload(['terms' => '']))
            ->assertSessionHasErrors('terms');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_confirmation_page_is_only_visible_with_the_order_token(): void
    {
        $this->addToCart(1)->assertRedirect();
        $this->post(route('storefront.checkout.place'), $this->payload());

        $order = Order::query()->firstOrFail();

        $this->get($order->confirmationUrl())
            ->assertOk()
            ->assertSee($order->number);

        $this->get(route('storefront.checkout.confirmation', [
            'order' => $order->number,
            'access' => 'not-the-real-token',
        ]))->assertForbidden();
    }

    public function test_an_admin_can_mark_a_manual_order_as_paid(): void
    {
        $this->addToCart(1)->assertRedirect();
        $this->post(route('storefront.checkout.place'), $this->payload());

        $order = Order::query()->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.orders.paid', $order))
            ->assertRedirect();

        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(25.0, (float) $order->paid_total);
        $this->assertNotNull($order->paid_at);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway' => 'manual',
            'status' => 'succeeded',
        ]);
    }

    public function test_an_admin_refund_updates_the_order_totals(): void
    {
        $this->addToCart(1)->assertRedirect();
        $this->post(route('storefront.checkout.place'), $this->payload());

        $order = Order::query()->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.orders.paid', $order));
        $this->actingAs($admin)
            ->post(route('admin.orders.refund', $order), [
                'amount' => 10,
                'reason' => 'Damaged item',
                'refund_token' => 'checkout-refund-token',
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame(10.0, (float) $order->refunded_total);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->payment_status);
    }

    public function test_subscription_checkout_activates_a_subscription_after_payment(): void
    {
        app(SettingsService::class)->setMany([
            'store.mode' => 'subscription',
            'theme.preset' => 'editorial',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'product_id' => $this->product->id,
            'name' => 'Monthly roast',
            'interval' => 'month',
            'interval_count' => 1,
            'price' => 25,
            'trial_days' => 7,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        $this->post(route('storefront.cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'subscription_plan_id' => $plan->id,
        ])->assertRedirect();

        $this->actingAs($customer)->post(route('storefront.checkout.place'), $this->payload());

        $order = Order::query()->firstOrFail();
        $this->assertTrue((bool) $order->is_subscription);
        $this->assertSame($customer->id, $order->user_id);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.orders.paid', $order))->assertRedirect();

        $subscription = Subscription::query()->firstOrFail();

        $this->assertSame($order->id, $subscription->order_id);
        $this->assertSame('month', $subscription->interval);
        $this->assertSame('manual', $subscription->gateway);
        $this->assertNotNull($subscription->trial_ends_at);
    }

    public function test_guest_subscription_checkout_is_rejected_with_a_clear_message(): void
    {
        app(SettingsService::class)->setMany([
            'store.mode' => 'subscription',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'product_id' => $this->product->id,
            'name' => 'Monthly roast',
            'interval' => 'month',
            'interval_count' => 1,
            'price' => 25,
            'is_active' => true,
        ]);

        $this->post(route('storefront.cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'subscription_plan_id' => $plan->id,
        ])->assertRedirect();

        $this->post(route('storefront.checkout.place'), $this->payload())
            ->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_fully_discounted_order_is_settled_without_contacting_a_gateway(): void
    {
        Http::fake();

        $free = Product::query()->create([
            'name' => 'Free Sample',
            'slug' => 'free-sample',
            'type' => 'simple',
            'price' => 0,
            'manage_stock' => false,
            'stock_quantity' => 0,
            'is_active' => true,
            'requires_shipping' => false,
        ]);

        $this->post(route('storefront.cart.add'), [
            'product_id' => $free->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->post(route('storefront.checkout.place'), $this->payload(['payment_method' => 'manual']))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame(0.0, (float) $order->grand_total);
        $this->assertTrue($order->isPaid());
        $this->assertSame(0.0, (float) $order->paid_total);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway' => 'free',
            'status' => 'succeeded',
        ]);

        Http::assertNothingSent();
    }

    public function test_a_refund_requires_an_idempotency_token(): void
    {
        $this->addToCart(1)->assertRedirect();
        $this->post(route('storefront.checkout.place'), $this->payload());

        $order = Order::query()->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.orders.paid', $order));

        $this->actingAs($admin)
            ->post(route('admin.orders.refund', $order), ['amount' => 5])
            ->assertSessionHasErrors('refund_token');

        $order->refresh();

        $this->assertSame(0.0, (float) $order->refunded_total);
        $this->assertCount(0, $order->refunds);
    }

    public function test_payment_settings_show_both_webhook_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['section' => 'payments']))
            ->assertOk()
            ->assertSee(url('/webhooks/stripe'))
            ->assertSee(url('/webhooks/paypal'))
            ->assertSee('Webhook endpoints');
    }

    public function test_webhooks_reject_unknown_gateways_and_bad_signatures(): void
    {
        $this->post('/webhooks/square', [])->assertNotFound();
        $this->post('/webhooks/stripe', [])->assertStatus(400);
        $this->post('/webhooks/paypal', [])->assertStatus(400);
    }

    public function test_b2b_checkout_blocks_guests_when_prices_are_hidden(): void
    {
        app(SettingsService::class)->setMany([
            'store.mode' => 'b2b',
            'b2b.hide_prices_guests' => true,
        ]);

        $this->get(route('storefront.checkout'))->assertRedirect(route('storefront.cart'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_sold_out_product_cannot_be_ordered_again(): void
    {
        $product = $this->stockedProduct(1);

        $this->post(route('storefront.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->post(route('storefront.checkout.place'), $this->payload())->assertRedirect();

        $this->assertSame(0, (int) $product->fresh()->stock_quantity);
        $this->assertSame(1, Order::query()->count());

        $this->flushSession();

        $this->post(route('storefront.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, (int) $product->fresh()->stock_quantity);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_checkout_rolls_back_when_a_competing_order_takes_the_last_unit(): void
    {
        $product = $this->stockedProduct(1);

        $this->post(route('storefront.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();

        // Simulate another shopper buying the last unit after our cart was
        // validated but before this order reserves its stock.
        $listener = function () use ($product) {
            Product::query()->whereKey($product->id)->update(['stock_quantity' => 0]);
        };

        Event::listen('eloquent.created: '.Order::class, $listener);

        try {
            $this->post(route('storefront.checkout.place'), $this->payload())
                ->assertSessionHasErrors('checkout');
        } finally {
            Event::forget('eloquent.created: '.Order::class);
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(1, (int) $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    private function stockedProduct(int $stock): Product
    {
        return Product::query()->create([
            'name' => 'Limited Item',
            'slug' => 'limited-item-'.Str::lower(Str::random(6)),
            'type' => 'simple',
            'price' => 10,
            'manage_stock' => true,
            'stock_quantity' => $stock,
            'is_active' => true,
            'requires_shipping' => true,
        ]);
    }

    private function addToCart(int $quantity)
    {
        return $this->post(route('storefront.cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => $quantity,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Test Buyer',
            'customer_email' => 'test@example.test',
            'line1' => '12 Test Street',
            'city' => 'Austin',
            'state' => 'TX',
            'postcode' => '73301',
            'country' => 'US',
            'payment_method' => 'manual',
            'terms' => '1',
        ], $overrides);
    }
}
