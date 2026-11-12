<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->setMany([
            'store.name' => 'Refund Test Store',
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
            'name' => 'Refundable Mug',
            'slug' => 'refundable-mug',
            'type' => 'simple',
            'price' => 25,
            'manage_stock' => false,
            'stock_quantity' => 100,
            'is_active' => true,
            'requires_shipping' => true,
        ]);

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_partial_refunds_cannot_exceed_the_remaining_balance_and_are_idempotent(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->admin)
            ->post(route('admin.orders.refund', $order), [
                'amount' => 10,
                'reason' => 'Customer return',
                'refund_token' => 'refund-token-one',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Refund recorded.');

        $order->refresh();

        $this->assertSame(10.0, (float) $order->refunded_total);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->payment_status);
        $this->assertCount(1, $order->refunds);

        // Double-clicking the same form must not refund twice.
        $this->actingAs($this->admin)
            ->post(route('admin.orders.refund', $order), [
                'amount' => 10,
                'reason' => 'Customer return',
                'refund_token' => 'refund-token-one',
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame(10.0, (float) $order->refunded_total);
        $this->assertCount(1, $order->refunds);

        // A new request is still capped at the remaining 15.00.
        $this->actingAs($this->admin)
            ->post(route('admin.orders.refund', $order), [
                'amount' => 20,
                'refund_token' => 'refund-token-two',
            ])
            ->assertSessionHasErrors('refund');

        $order->refresh();

        $this->assertSame(10.0, (float) $order->refunded_total);
        $this->assertCount(1, $order->refunds);

        $this->actingAs($this->admin)
            ->post(route('admin.orders.refund', $order), [
                'amount' => 15,
                'refund_token' => 'refund-token-three',
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame(25.0, (float) $order->refunded_total);
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertCount(2, $order->refunds);
    }

    public function test_refunds_still_work_after_the_gateway_is_disabled_for_new_orders(): void
    {
        $order = $this->paidOrder();

        app(SettingsService::class)->set('payments.manual.enabled', false, 'payments');
        app(SettingsService::class)->flushCache();

        $this->actingAs($this->admin)
            ->post(route('admin.orders.refund', $order), [
                'amount' => 5,
                'refund_token' => 'disabled-gateway-refund',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Refund recorded.');

        $order->refresh();

        $this->assertSame(5.0, (float) $order->refunded_total);
        $this->assertCount(1, $order->refunds);
    }

    private function paidOrder(): Order
    {
        $this->post(route('storefront.cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->post(route('storefront.checkout.place'), [
            'customer_name' => 'Refund Buyer',
            'customer_email' => 'refund@example.test',
            'line1' => '12 Refund Street',
            'city' => 'Austin',
            'state' => 'TX',
            'postcode' => '73301',
            'country' => 'US',
            'payment_method' => 'manual',
            'terms' => '1',
        ])->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.orders.paid', $order))
            ->assertRedirect();

        return $order->fresh();
    }
}
