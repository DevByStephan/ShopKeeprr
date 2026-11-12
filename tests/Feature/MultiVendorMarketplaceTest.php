<?php

namespace Tests\Feature;

use App\Enums\StoreMode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSplit;
use App\Services\Marketplace\CommissionCalculator;
use App\Services\Marketplace\PayPalPayouts;
use App\Services\Marketplace\StripeConnect;
use App\Services\Marketplace\VendorSettlementService;
use App\Services\Marketplace\VendorSplitService;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MultiVendorMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configure();
    }

    public function test_the_four_business_models_are_retail_b2b_subscription_and_multi_vendor(): void
    {
        $values = array_map(fn (StoreMode $mode) => $mode->value, StoreMode::cases());

        $this->assertSame(['retail', 'b2b', 'subscription', 'multivendor'], $values);
        $this->assertTrue(StoreMode::MultiVendor->allowsVendors());
        $this->assertFalse(StoreMode::Retail->allowsVendors());
    }

    public function test_commission_calculator_handles_percentage_fixed_combined_and_the_subtotal_cap(): void
    {
        $calculator = app(CommissionCalculator::class);

        $this->assertSame(1000, $calculator->calculate(10000, ['type' => 'percentage', 'percent' => 10]));
        $this->assertSame(500, $calculator->calculate(10000, ['type' => 'fixed', 'fixed' => 5]));
        $this->assertSame(1500, $calculator->calculate(10000, ['type' => 'combined', 'percent' => 10, 'fixed' => 5]));
        $this->assertSame(10000, $calculator->calculate(10000, ['type' => 'fixed', 'fixed' => 500]));
        $this->assertSame(0, $calculator->calculate(10000, ['type' => 'percentage', 'percent' => -5]));
        $this->assertSame(0, $calculator->calculate(0, ['type' => 'combined', 'percent' => 10, 'fixed' => 5]));
    }

    public function test_an_order_creates_one_split_per_vendor_with_discounts_and_commission_applied(): void
    {
        $first = $this->vendor(['name' => 'Alpha Goods', 'commission_type' => 'percentage', 'commission_percent' => 10]);
        $second = $this->vendor(['name' => 'Beta Studio']);

        app(SettingsService::class)->setMany([
            'marketplace.commission_type' => 'combined',
            'marketplace.commission_percent' => 5,
            'marketplace.commission_fixed' => 2,
        ]);

        $order = $this->order([
            $this->product($first, 'Alpha item', 100),
            $this->product($second, 'Beta item', 200),
        ], [
            'discount_total' => 30,
            'shipping_total' => 15,
            'tax_total' => 10,
        ]);

        $splits = app(VendorSplitService::class)->createForOrder($order);

        $this->assertCount(2, $splits);

        $alpha = $splits->firstWhere('vendor_id', $first->id);
        $beta = $splits->firstWhere('vendor_id', $second->id);

        $this->assertSame(100.0, (float) $alpha->subtotal);
        $this->assertSame(10.0, (float) $alpha->discount_total);
        $this->assertSame(9.0, (float) $alpha->commission_total);
        $this->assertSame(81.0, (float) $alpha->payout_total);

        $this->assertSame(200.0, (float) $beta->subtotal);
        $this->assertSame(20.0, (float) $beta->discount_total);
        $this->assertSame(11.0, (float) $beta->commission_total);
        $this->assertSame(169.0, (float) $beta->payout_total);

        $this->assertSame(250.0, round($splits->sum('payout_total'), 2));

        $alphaItem = $order->items()->where('product_id', $alpha->order->items->firstWhere('vendor_id', $first->id)->product_id)->first();
        $this->assertSame(9.0, (float) $alphaItem->commission_amount);
        $this->assertSame(81.0, (float) $alphaItem->vendor_total);
    }

    public function test_a_mixed_cart_allocates_the_discount_to_store_and_seller_lines_fairly(): void
    {
        $vendor = $this->vendor();
        $vendorProduct = $this->product($vendor, 'Seller item', 100);
        $storeProduct = Product::query()->create([
            'name' => 'Store item',
            'slug' => 'store-item',
            'type' => 'simple',
            'price' => 100,
            'manage_stock' => true,
            'stock_quantity' => 10,
            'is_active' => true,
            'requires_shipping' => true,
            'published_at' => now(),
        ]);

        $order = $this->order([$vendorProduct, $storeProduct], ['discount_total' => 20]);
        $split = app(VendorSplitService::class)->createForOrder($order)->firstOrFail();

        $this->assertSame(100.0, (float) $split->subtotal);
        $this->assertSame(10.0, (float) $split->discount_total);
        $this->assertSame(9.0, (float) $split->commission_total);
        $this->assertSame(81.0, (float) $split->payout_total);
        $this->assertSame(10.0, (float) $order->items()->where('product_id', $storeProduct->id)->first()->discount_amount);
    }

    public function test_non_marketplace_orders_do_not_create_vendor_splits(): void
    {
        app(SettingsService::class)->set('store.mode', 'retail');
        $vendor = $this->vendor();

        $order = $this->order([$this->product($vendor, 'Retail item', 40)]);
        $order->update(['mode' => StoreMode::Retail]);

        $this->assertCount(0, app(VendorSplitService::class)->createForOrder($order->fresh()));
        $this->assertSame(0, VendorSplit::query()->count());
    }

    public function test_paying_an_order_holds_seller_balances_for_the_hold_period(): void
    {
        Mail::fake();

        $vendor = $this->vendor(['payout_hold_days' => 5]);
        $order = $this->order([$this->product($vendor, 'Item', 100)]);

        app(OrderService::class)->markPaid($order, 'manual', 'bank-ref-1');

        $split = VendorSplit::query()->firstOrFail();

        $this->assertSame(VendorSplit::STATUS_HELD, $split->status);
        $this->assertNull($split->stripe_transfer_id);
        $this->assertTrue($split->available_at->between(now()->addDays(4)->subHour(), now()->addDays(5)->addHour()));
    }

    public function test_stripe_connect_transfers_each_seller_their_split(): void
    {
        Mail::fake();

        app(SettingsService::class)->setMany([
            'payments.stripe.secret_key' => 'sk_test_vendor',
            'marketplace.auto_payout' => true,
        ]);

        Http::fake(function (ClientRequest $request) {
            $url = $request->url();

            if (str_contains($url, '/payment_intents/')) {
                return Http::response(['id' => 'pi_vendor', 'latest_charge' => 'ch_vendor']);
            }

            if (str_contains($url, '/reversals')) {
                return Http::response(['id' => 'trr_vendor']);
            }

            if (str_contains($url, '/transfers')) {
                return Http::response(['id' => 'tr_'.md5($request->data()['destination'] ?? 'none')]);
            }

            if (str_contains($url, '/refunds')) {
                return Http::response(['id' => 're_vendor']);
            }

            return Http::response([], 404);
        });

        $vendor = $this->vendor(['payout_method' => 'stripe', 'stripe_account_id' => 'acct_seller', 'stripe_payouts_enabled' => true]);
        $order = $this->order([$this->product($vendor, 'Seller item', 100)]);

        app(OrderService::class)->markPaid($order, 'stripe', 'pi_vendor');

        $split = VendorSplit::query()->firstOrFail();

        $this->assertSame(VendorSplit::STATUS_PAID, $split->status);
        $this->assertNotNull($split->stripe_transfer_id);
        $this->assertNotNull($split->paid_at);

        Http::assertSent(function (ClientRequest $request) {
            return str_ends_with($request->url(), '/transfers')
                && $request['destination'] === 'acct_seller'
                && $request['source_transaction'] === 'ch_vendor'
                && $request['amount'] === 9000;
        });
    }

    public function test_a_duplicate_payment_callback_does_not_pay_a_seller_twice(): void
    {
        Mail::fake();

        app(SettingsService::class)->setMany([
            'payments.stripe.secret_key' => 'sk_test_vendor',
            'marketplace.auto_payout' => true,
        ]);

        Http::fake(function (ClientRequest $request) {
            $url = $request->url();

            if (str_contains($url, '/payment_intents/')) {
                return Http::response(['id' => 'pi_duplicate', 'latest_charge' => 'ch_duplicate']);
            }

            if (str_contains($url, '/transfers')) {
                return Http::response(['id' => 'tr_duplicate']);
            }

            return Http::response([], 404);
        });

        $vendor = $this->vendor(['payout_method' => 'stripe', 'stripe_account_id' => 'acct_duplicate', 'stripe_payouts_enabled' => true]);
        $order = $this->order([$this->product($vendor, 'Duplicate item', 100)]);

        app(OrderService::class)->markPaid($order, 'stripe', 'pi_duplicate');
        app(OrderService::class)->markPaid($order, 'stripe', 'pi_duplicate');

        $this->assertSame(1, VendorSplit::query()->count());
        $this->assertSame(1, Payment::query()->where('status', 'succeeded')->count());

        $transfers = Http::recorded(fn (ClientRequest $request) => str_ends_with($request->url(), '/transfers'));

        $this->assertCount(1, $transfers);
    }

    public function test_a_refund_reverses_the_seller_transfer_through_stripe(): void
    {
        Mail::fake();

        app(SettingsService::class)->setMany([
            'payments.stripe.secret_key' => 'sk_test_vendor',
            'marketplace.auto_payout' => true,
        ]);

        Http::fake(function (ClientRequest $request) {
            $url = $request->url();

            if (str_contains($url, '/payment_intents/')) {
                return Http::response(['id' => 'pi_vendor', 'latest_charge' => 'ch_vendor']);
            }

            if (str_contains($url, '/reversals')) {
                return Http::response(['id' => 'trr_vendor']);
            }

            if (str_contains($url, '/transfers')) {
                return Http::response(['id' => 'tr_vendor']);
            }

            if (str_contains($url, '/refunds')) {
                return Http::response(['id' => 're_vendor']);
            }

            return Http::response([], 404);
        });

        $vendor = $this->vendor(['payout_method' => 'stripe', 'stripe_account_id' => 'acct_seller', 'stripe_payouts_enabled' => true]);
        $order = $this->order([$this->product($vendor, 'Seller item', 100)]);

        app(OrderService::class)->markPaid($order, 'stripe', 'pi_vendor');

        $split = VendorSplit::query()->firstOrFail();
        $this->assertSame(90.0, (float) $split->payout_total);

        // A $55 refund on a $100 item order leaves the seller bearing $55 of
        // the item value; the platform keeps the shipping and tax share.
        app(RefundService::class)->refund($order->fresh(), 55);

        $split->refresh();

        $this->assertSame(55.0, (float) $split->refunded_total);
        $this->assertSame(55.0, (float) $split->stripe_transfer_reversed);
        $this->assertSame(VendorSplit::STATUS_PAID, $split->status);
        $this->assertSame(35.0, $split->netPayout());

        Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), '/transfers/tr_vendor/reversals')
            && $request['amount'] === 5500);
    }

    public function test_paypal_payouts_sends_a_batch_and_marks_the_splits_paid(): void
    {
        app(SettingsService::class)->setMany([
            'payments.paypal.client_id' => 'paypal-client',
            'payments.paypal.client_secret' => 'paypal-secret',
            'payments.paypal.mode' => 'sandbox',
            'marketplace.minimum_payout' => 0,
        ]);

        Http::fake(function (ClientRequest $request) {
            if (str_contains($request->url(), '/oauth2/token')) {
                return Http::response(['access_token' => 'paypal-token']);
            }

            if (str_contains($request->url(), '/payments/payouts')) {
                return Http::response(['batch_header' => ['payout_batch_id' => 'PAYOUT-1']], 201);
            }

            return Http::response([], 404);
        });

        $vendor = $this->vendor(['payout_method' => 'paypal', 'paypal_email' => 'seller@example.test']);
        $order = $this->order([$this->product($vendor, 'Item', 100)]);
        $split = app(VendorSplitService::class)->createForOrder($order)->firstOrFail();
        $split->update(['status' => VendorSplit::STATUS_HELD, 'available_at' => now()->subDay()]);

        $result = app(VendorSettlementService::class)->payVendor($vendor->fresh());

        $this->assertSame(1, $result['paid']);
        $this->assertSame('PAYOUT-1', $result['reference']);
        $this->assertSame(VendorSplit::STATUS_PAID, $split->fresh()->status);
        $this->assertSame('PAYOUT-1', $split->fresh()->paypal_batch_id);

        Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), '/payments/payouts')
            && $request['items'][0]['receiver'] === 'seller@example.test'
            && $request['items'][0]['amount']['value'] === '90.00');
    }

    public function test_stripe_connect_onboarding_creates_an_express_account_and_link(): void
    {
        app(SettingsService::class)->set('payments.stripe.secret_key', 'sk_test_connect');

        Http::fake([
            'api.stripe.com/v1/accounts' => Http::response(['id' => 'acct_new']),
            'api.stripe.com/v1/account_links' => Http::response(['url' => 'https://connect.stripe.com/setup/abc']),
            'api.stripe.com/v1/accounts/acct_new' => Http::response([
                'id' => 'acct_new',
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'details_submitted' => true,
            ]),
        ]);

        $vendor = $this->vendor(['payout_method' => 'stripe']);
        $stripe = app(StripeConnect::class);

        $vendor->update(['stripe_account_id' => $stripe->createExpressAccount($vendor)]);

        $this->assertSame('acct_new', $vendor->fresh()->stripe_account_id);
        $this->assertSame('https://connect.stripe.com/setup/abc', $stripe->accountLink($vendor->fresh(), 'https://store.test/refresh', 'https://store.test/return'));

        $stripe->refreshAccount($vendor->fresh());
        $vendor->refresh();

        $this->assertTrue($vendor->stripe_charges_enabled);
        $this->assertTrue($vendor->stripe_payouts_enabled);
        $this->assertTrue($vendor->stripe_details_submitted);
        $this->assertSame('stripe', $vendor->payout_method);
    }

    public function test_vendor_portal_is_limited_to_the_assigned_approved_seller(): void
    {
        $vendorUser = User::factory()->create(['role' => 'customer']);
        $otherUser = User::factory()->create(['role' => 'customer']);
        $vendor = $this->vendor(['user_id' => $vendorUser->id]);
        $otherVendor = $this->vendor(['name' => 'Other seller', 'slug' => 'other-seller']);
        $product = $this->product($vendor, 'Mine', 10);
        $otherProduct = $this->product($otherVendor, 'Theirs', 10);

        $this->actingAs($vendorUser)->get(route('vendor.dashboard'))->assertOk();
        $this->actingAs($vendorUser)->get(route('vendor.products.edit', $product))->assertOk();
        $this->actingAs($vendorUser)->get(route('vendor.products.edit', $otherProduct))->assertNotFound();
        $this->actingAs($otherUser)->get(route('vendor.dashboard'))->assertForbidden();

        $vendor->update(['status' => Vendor::STATUS_SUSPENDED]);
        $this->actingAs($vendorUser)->get(route('vendor.dashboard'))->assertForbidden();
    }

    public function test_storefront_vendor_directory_and_product_attribution_respect_seller_status(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, 'Handmade bowl', 45);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => 'images/placeholder.svg', 'sort_order' => 0]);

        $this->get(route('storefront.vendors.index'))->assertOk()->assertSee('Alpha Goods');
        $this->get(route('storefront.vendors.show', $vendor->slug))->assertOk()->assertSee('Handmade bowl');
        $this->get(route('storefront.product', $product->slug))->assertOk()->assertSee('Sold by Alpha Goods');

        $vendor->update(['status' => Vendor::STATUS_SUSPENDED]);

        $this->get(route('storefront.vendors.show', $vendor->slug))->assertNotFound();
        $this->get(route('storefront.product', $product->slug))->assertNotFound();
        $this->post(route('storefront.cart.add'), ['product_id' => $product->id])->assertNotFound();
    }

    public function test_the_public_vendor_directory_is_hidden_in_other_modes(): void
    {
        app(SettingsService::class)->set('store.mode', 'retail');

        $this->get(route('storefront.vendors.index'))->assertNotFound();
    }

    public function test_an_admin_can_create_a_seller_and_set_a_custom_commission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['role' => 'customer', 'email' => 'seller@example.test']);

        $this->actingAs($admin)->post(route('admin.vendors.store'), [
            'name' => 'North Star Prints',
            'email' => 'hello@northstar.test',
            'user_id' => $seller->id,
            'status' => 'approved',
            'commission_type' => 'combined',
            'commission_percent' => 8,
            'commission_fixed' => 1.5,
            'payout_method' => 'manual',
        ])->assertRedirect();

        $vendor = Vendor::query()->where('slug', 'north-star-prints')->firstOrFail();

        $this->assertSame('seller@example.test', $vendor->email);
        $this->assertSame('approved', $vendor->status);
        $this->assertSame('combined', $vendor->commissionRule()['type']);
        $this->assertSame(8.0, $vendor->commissionRule()['percent']);
        $this->assertSame(1.5, $vendor->commissionRule()['fixed']);

        $this->actingAs($admin)->get(route('admin.vendors.show', $vendor))->assertOk()->assertSee('North Star Prints');

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('admin.vendors.index'))->assertForbidden();
    }

    public function test_a_storefront_checkout_creates_the_vendor_split_in_the_same_order(): void
    {
        app(SettingsService::class)->setMany([
            'payments.manual.enabled' => true,
            'payments.stripe.enabled' => false,
            'payments.paypal.enabled' => false,
            'tax.prices_include_tax' => false,
            'tax.shipping_taxable' => false,
            'shipping.free_threshold' => 0,
        ]);

        $vendor = $this->vendor();
        $product = $this->product($vendor, 'Checkout item', 40);

        $this->post(route('storefront.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();

        $this->post(route('storefront.checkout.place'), $this->checkoutPayload())->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame(StoreMode::MultiVendor, $order->mode);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'vendor_id' => $vendor->id]);
        $this->assertDatabaseHas('vendor_splits', [
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorSplit::STATUS_PENDING,
            'payout_total' => 36.0,
        ]);
    }

    public function test_an_admin_can_assign_a_product_to_a_seller_and_save_marketplace_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = $this->vendor();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Seller product',
            'slug' => 'seller-product',
            'price' => 30,
            'vendor_id' => $vendor->id,
            'is_active' => '1',
            'manage_stock' => '1',
            'stock_quantity' => 5,
            'requires_shipping' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['slug' => 'seller-product', 'vendor_id' => $vendor->id]);

        $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'section' => 'marketplace',
            'commission_type' => 'combined',
            'commission_percent' => 12.5,
            'commission_fixed' => 2.5,
            'payout_hold_days' => 10,
            'minimum_payout' => 50,
            'auto_payout' => '1',
        ])->assertRedirect();

        app(SettingsService::class)->flushCache();

        $this->assertSame('combined', nexus_setting('marketplace.commission_type'));
        $this->assertSame(12.5, (float) nexus_setting('marketplace.commission_percent'));
        $this->assertSame(2.5, (float) nexus_setting('marketplace.commission_fixed'));
        $this->assertSame(10, (int) nexus_setting('marketplace.payout_hold_days'));
        $this->assertSame(50.0, (float) nexus_setting('marketplace.minimum_payout'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vendor(array $attributes = []): Vendor
    {
        static $counter = 0;
        $counter++;

        return Vendor::query()->create(array_merge([
            'name' => 'Alpha Goods',
            'slug' => 'alpha-goods-'.$counter,
            'email' => 'alpha'.$counter.'@example.test',
            'status' => Vendor::STATUS_APPROVED,
            'payout_method' => 'manual',
        ], $attributes));
    }

    private function product(Vendor $vendor, string $name, float $price): Product
    {
        return Product::query()->create([
            'vendor_id' => $vendor->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(6),
            'type' => 'simple',
            'price' => $price,
            'manage_stock' => true,
            'stock_quantity' => 100,
            'is_active' => true,
            'requires_shipping' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * @param  list<Product>  $products
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $products, array $attributes = []): Order
    {
        $subtotal = collect($products)->sum('price');
        $discount = (float) ($attributes['discount_total'] ?? 0);
        $shipping = (float) ($attributes['shipping_total'] ?? 0);
        $tax = (float) ($attributes['tax_total'] ?? 0);

        $order = Order::query()->create(array_merge([
            'number' => 'MV-'.str()->upper(str()->random(8)),
            'access_token' => str()->random(32),
            'status' => 'pending',
            'payment_status' => 'pending',
            'currency' => 'USD',
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'shipping_total' => $shipping,
            'tax_total' => $tax,
            'grand_total' => $subtotal - $discount + $shipping + $tax,
            'customer_email' => 'buyer@example.test',
            'customer_name' => 'Sam Buyer',
            'shipping_address' => [
                'name' => 'Sam Buyer',
                'line1' => '1 High Street',
                'city' => 'Springfield',
                'postcode' => '12345',
                'country' => 'US',
            ],
            'mode' => StoreMode::MultiVendor,
            'placed_at' => now(),
        ], $attributes));

        foreach ($products as $product) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'vendor_id' => $product->vendor_id,
                'product_name' => $product->name,
                'quantity' => 1,
                'unit_price' => $product->price,
                'line_subtotal' => $product->price,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => $product->price,
            ]);
        }

        return $order->load('items.product.vendor');
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Sam Buyer',
            'customer_email' => 'sam@example.test',
            'line1' => '1 High Street',
            'city' => 'Springfield',
            'state' => 'IL',
            'postcode' => '62701',
            'country' => 'US',
            'payment_method' => 'manual',
            'terms' => '1',
        ], $overrides);
    }

    private function configure(array $extra = []): void
    {
        app(SettingsService::class)->setMany(array_merge([
            'store.name' => 'Market Test',
            'store.live' => true,
            'store.mode' => 'multivendor',
            'store.setup_completed' => true,
            'store.currency' => 'USD',
            'store.currency_symbol' => '$',
            'marketplace.commission_type' => 'percentage',
            'marketplace.commission_percent' => 10,
            'marketplace.commission_fixed' => 0,
            'marketplace.payout_hold_days' => 7,
            'marketplace.minimum_payout' => 25,
            'marketplace.auto_payout' => true,
        ], $extra));

        app(SettingsService::class)->flushCache();
    }
}
