<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\WebhookEvent;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_webhooks_reject_bad_signatures_and_process_events_once(): void
    {
        app(SettingsService::class)->set('payments.stripe.webhook_secret', 'whsec_test', 'payments', true);
        app(SettingsService::class)->flushCache();

        $payload = json_encode([
            'id' => 'evt_stripe_test_1',
            'type' => 'charge.refunded',
            'data' => ['object' => ['id' => 'ch_test', 'amount_refunded' => 0]],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $bad = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1=not-the-signature',
        ], $payload);

        $bad->assertStatus(400);
        $this->assertDatabaseCount('webhook_events', 0);

        $good = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload);

        $good->assertOk();
        $this->assertDatabaseCount('webhook_events', 1);

        $event = WebhookEvent::query()->firstOrFail();
        $this->assertNotNull($event->processed_at);

        // Stripe retries deliveries; the second one must be a no-op.
        $replay = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload);

        $replay->assertOk();
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_paypal_webhooks_verify_signatures_and_ignore_replays(): void
    {
        app(SettingsService::class)->setMany([
            'payments.paypal.client_id' => 'paypal-client',
            'payments.paypal.client_secret' => 'paypal-secret',
            'payments.paypal.mode' => 'sandbox',
            'payments.paypal.webhook_id' => 'WH-TEST',
        ]);
        app(SettingsService::class)->flushCache();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21-fake', 'expires_in' => 3600]),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);

        $payload = json_encode([
            'id' => 'WH-EVENT-1',
            'event_type' => 'PAYMENT.SALE.COMPLETED',
            'resource' => ['id' => 'SALE-1'],
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'transmission-1',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
            'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/cert.pem',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'signature',
        ];

        $missing = $this->call('POST', '/webhooks/paypal', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $missing->assertStatus(400);

        $good = $this->call('POST', '/webhooks/paypal', [], [], [], $headers, $payload);
        $good->assertOk();
        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertNotNull(WebhookEvent::query()->firstOrFail()->processed_at);

        $replay = $this->call('POST', '/webhooks/paypal', [], [], [], $headers, $payload);
        $replay->assertOk();
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_a_signed_stripe_checkout_event_marks_the_order_paid_only_once(): void
    {
        $this->configureStripeWebhook();
        $order = $this->order();

        $session = [
            'id' => 'cs_paid_once',
            'payment_status' => 'paid',
            'amount_total' => 2500,
            'currency' => 'usd',
            'metadata' => ['order_id' => (string) $order->id],
            'payment_intent' => 'pi_paid_once',
        ];

        $this->stripeEvent('evt_paid_once', 'checkout.session.completed', $session)->assertOk();

        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(25.0, (float) $order->paid_total);
        $this->assertSame(1, $order->payments()->where('status', 'succeeded')->count());

        // A retry that arrives with a fresh event id must not capture twice.
        $this->stripeEvent('evt_paid_once_retry', 'checkout.session.completed', $session)->assertOk();

        $order->refresh();

        $this->assertSame(25.0, (float) $order->paid_total);
        $this->assertSame(1, $order->payments()->where('status', 'succeeded')->count());
    }

    public function test_stripe_events_with_a_wrong_amount_currency_or_order_never_mark_it_paid(): void
    {
        $this->configureStripeWebhook();
        $order = $this->order();

        $attempts = [
            'evt_wrong_amount' => ['amount_total' => 100, 'currency' => 'usd'],
            'evt_wrong_currency' => ['amount_total' => 2500, 'currency' => 'eur'],
            'evt_wrong_order' => ['amount_total' => 2500, 'currency' => 'usd', 'metadata' => ['order_id' => '999999']],
        ];

        foreach ($attempts as $id => $overrides) {
            $this->stripeEvent($id, 'checkout.session.completed', array_merge([
                'id' => 'cs_'.$id,
                'payment_status' => 'paid',
                'metadata' => ['order_id' => (string) $order->id],
                'payment_intent' => 'pi_'.$id,
            ], $overrides))->assertOk();
        }

        $order->refresh();

        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(0.0, (float) $order->paid_total);
        $this->assertSame(0, $order->payments()->where('status', 'succeeded')->count());
    }

    public function test_stripe_dashboard_refunds_are_recorded_once_and_update_order_totals(): void
    {
        $this->configureStripeWebhook();
        $order = $this->paidOrder();

        $charge = [
            'id' => 'ch_refund',
            'payment_intent' => 'pi_refund',
            'amount_refunded' => 1000,
            'metadata' => ['order_id' => (string) $order->id],
        ];

        $this->stripeEvent('evt_refund_1', 'charge.refunded', $charge)->assertOk();

        $order->refresh();

        $this->assertSame(10.0, (float) $order->refunded_total);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->payment_status);
        $this->assertSame(1, $order->refunds()->count());

        // The same delivery, and a new event carrying the same total, must not
        // refund the order twice.
        $this->stripeEvent('evt_refund_1', 'charge.refunded', $charge)->assertOk();
        $this->stripeEvent('evt_refund_2', 'charge.refunded', $charge)->assertOk();

        $order->refresh();

        $this->assertSame(10.0, (float) $order->refunded_total);
        $this->assertSame(1, $order->refunds()->count());

        $charge['amount_refunded'] = 2500;
        $this->stripeEvent('evt_refund_3', 'charge.refunded', $charge)->assertOk();

        $order->refresh();

        $this->assertSame(25.0, (float) $order->refunded_total);
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertSame(2, $order->refunds()->count());
    }

    public function test_a_payment_that_arrives_after_cancellation_reopens_the_order_and_re_reserves_stock(): void
    {
        $this->configureStripeWebhook();

        $product = Product::query()->create([
            'name' => 'Late Payment Mug',
            'slug' => 'late-payment-mug',
            'type' => 'simple',
            'price' => 25,
            'manage_stock' => true,
            'stock_quantity' => 5,
            'is_active' => true,
            'requires_shipping' => true,
        ]);

        $order = $this->order(['payment_method' => 'stripe']);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 25,
            'line_subtotal' => 25,
            'line_total' => 25,
        ]);

        // Simulate the abandonment task: stock already returned, order cancelled.
        $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

        $this->stripeEvent('evt_late_payment', 'checkout.session.completed', [
            'id' => 'cs_late_payment',
            'payment_status' => 'paid',
            'amount_total' => 2500,
            'currency' => 'usd',
            'metadata' => ['order_id' => (string) $order->id],
            'payment_intent' => 'pi_late_payment',
        ])->assertOk();

        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertNull($order->cancelled_at);
        $this->assertSame(4, (int) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'note' => 'Payment received via stripe after cancellation. Order reopened.',
        ]);
    }

    public function test_a_payment_after_a_declined_attempt_reserves_stock_again(): void
    {
        $this->configureStripeWebhook();

        $product = Product::query()->create([
            'name' => 'Retry Payment Mug',
            'slug' => 'retry-payment-mug',
            'type' => 'simple',
            'price' => 25,
            'manage_stock' => true,
            'stock_quantity' => 5,
            'is_active' => true,
            'requires_shipping' => true,
        ]);

        $order = $this->order(['payment_method' => 'stripe']);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 12.50,
            'line_subtotal' => 25,
            'line_total' => 25,
        ]);

        // A decline returns the stock to the shelf.
        $order->update(['status' => OrderStatus::Failed, 'payment_status' => PaymentStatus::Failed]);

        $this->stripeEvent('evt_retry_payment', 'checkout.session.completed', [
            'id' => 'cs_retry_payment',
            'payment_status' => 'paid',
            'amount_total' => 2500,
            'currency' => 'usd',
            'metadata' => ['order_id' => (string) $order->id],
            'payment_intent' => 'pi_retry_payment',
        ])->assertOk();

        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'note' => 'Payment received via stripe after a failed attempt. Order reopened.',
        ]);
    }

    public function test_paypal_dashboard_refunds_are_reconciled_once(): void
    {
        app(SettingsService::class)->setMany([
            'payments.paypal.client_id' => 'paypal-client',
            'payments.paypal.client_secret' => 'paypal-secret',
            'payments.paypal.mode' => 'sandbox',
            'payments.paypal.webhook_id' => 'WH-TEST',
        ]);
        app(SettingsService::class)->flushCache();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21-fake', 'expires_in' => 3600]),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);

        $order = $this->paidOrder('paypal');

        $resource = [
            'id' => 'REFUND-PAYPAL-1',
            'amount' => ['value' => '5.00', 'currency_code' => 'USD'],
            'seller_payable_breakdown' => ['total_refunded_amount' => ['value' => '5.00', 'currency_code' => 'USD']],
            'supplementary_data' => ['related_ids' => ['capture_id' => 'CAPTURE-REFUND']],
        ];

        $this->paypalEvent('WH-REFUND-1', 'PAYMENT.CAPTURE.REFUNDED', $resource)->assertOk();
        $this->paypalEvent('WH-REFUND-1', 'PAYMENT.CAPTURE.REFUNDED', $resource)->assertOk();

        $order->refresh();

        $this->assertSame(5.0, (float) $order->refunded_total);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->payment_status);
        $this->assertSame(1, $order->refunds()->count());
    }

    private function configureStripeWebhook(): void
    {
        app(SettingsService::class)->set('payments.stripe.webhook_secret', 'whsec_test', 'payments', true);
        app(SettingsService::class)->flushCache();
    }

    private function stripeEvent(string $id, string $type, array $object)
    {
        $payload = json_encode(['id' => $id, 'type' => $type, 'data' => ['object' => $object]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        return $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload);
    }

    private function paypalEvent(string $id, string $type, array $resource)
    {
        $payload = json_encode(['id' => $id, 'event_type' => $type, 'resource' => $resource], JSON_THROW_ON_ERROR);

        return $this->call('POST', '/webhooks/paypal', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'transmission-'.$id,
            'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
            'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/cert.pem',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'signature',
        ], $payload);
    }

    private function order(array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'number' => 'NC-'.Str::upper(Str::random(8)),
            'access_token' => Str::random(48),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'USD',
            'subtotal' => 25,
            'grand_total' => 25,
            'paid_total' => 0,
            'refunded_total' => 0,
            'customer_email' => 'webhook@example.test',
            'customer_name' => 'Webhook Buyer',
            'shipping_address' => [
                'line1' => '1 Webhook Street',
                'city' => 'Austin',
                'state' => 'TX',
                'postcode' => '73301',
                'country' => 'US',
            ],
            'payment_method' => 'stripe',
            'mode' => 'retail',
            'placed_at' => now(),
        ], $overrides));
    }

    private function paidOrder(string $gateway = 'stripe'): Order
    {
        $order = $this->order([
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'paid_total' => 25,
            'paid_at' => now(),
            'payment_method' => $gateway,
        ]);

        $order->payments()->create([
            'gateway' => $gateway,
            'gateway_reference' => $gateway === 'stripe' ? 'cs_refund' : 'PAYPAL-REFUND',
            'gateway_payment_id' => $gateway === 'stripe' ? 'pi_refund' : 'CAPTURE-REFUND',
            'amount' => 25,
            'currency' => 'USD',
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);

        return $order;
    }
}
