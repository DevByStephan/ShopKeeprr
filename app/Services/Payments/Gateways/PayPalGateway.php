<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\OrderService;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\RefundResult;
use App\Services\RefundService;
use App\Services\SubscriptionService;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function isEnabled(): bool
    {
        return (bool) nexus_setting('payments.paypal.enabled', false)
            && (string) nexus_setting('payments.paypal.client_id', '') !== ''
            && (string) nexus_setting('payments.paypal.client_secret', '') !== '';
    }

    public function supportsSubscriptions(): bool
    {
        return true;
    }

    public function start(Order $order): PaymentResult
    {
        return $order->is_subscription ? $this->startSubscription($order) : $this->startOrder($order);
    }

    public function complete(Order $order, array $payload = []): PaymentResult
    {
        $payment = $order->payments()->where('gateway', $this->code())->latest()->first();

        if (! $payment) {
            throw new PaymentException('This payment does not belong to the order.');
        }

        if (! empty($payload['subscription_id'])) {
            $subscriptionId = (string) $payload['subscription_id'];
            $response = Http::withToken($this->token())->acceptJson()->get($this->base().'/v1/billing/subscriptions/'.$subscriptionId);

            if ($response->successful() && $response->json('status') === 'ACTIVE') {
                app(OrderService::class)->markPaid($order, $this->code(), $subscriptionId, $response->json(), $subscriptionId);
                $order->subscriptions()->update(['gateway_subscription_id' => $subscriptionId]);

                return PaymentResult::paid($subscriptionId);
            }

            return PaymentResult::pending('PayPal is still activating this subscription.', $subscriptionId);
        }

        $paypalOrderId = (string) ($payload['token'] ?? $payment->gateway_reference ?? '');

        if ($paypalOrderId === '') {
            return PaymentResult::pending('PayPal did not return an order reference.');
        }

        // PayPal requires a JSON object on capture. An empty request body is
        // rejected with MALFORMED_REQUEST_JSON, leaving a paid order pending.
        $response = Http::withToken($this->token())
            ->acceptJson()
            ->withBody('{}', 'application/json')
            ->post($this->base().'/v2/checkout/orders/'.$paypalOrderId.'/capture');

        if (! $response->successful()) {
            return PaymentResult::pending($this->errorMessage($response));
        }

        $data = $response->json();

        if (($data['status'] ?? '') === 'COMPLETED') {
            if (! $this->captureMatchesOrder($order, $data)) {
                Log::warning('PayPal capture does not match the order', [
                    'order' => $order->number,
                    'paypal_order' => $paypalOrderId,
                    'amount' => $data['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? null,
                ]);

                return PaymentResult::pending('PayPal returned a payment that does not match this order.');
            }

            $captureId = $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? $paypalOrderId;
            app(OrderService::class)->markPaid($order, $this->code(), $captureId, $data);

            return PaymentResult::paid($captureId);
        }

        return PaymentResult::pending('PayPal has not completed this payment yet.');
    }

    public function refund(Order $order, Payment $payment, float $amount, ?string $idempotencyKey = null): RefundResult
    {
        $captureId = $payment->gateway_payment_id;

        if (! $captureId) {
            return new RefundResult(false, null, 'PayPal did not record a capture for this order.');
        }

        $request = Http::withToken($this->token())->acceptJson();

        if ($idempotencyKey) {
            $request = $request->withHeaders(['PayPal-Request-Id' => $idempotencyKey]);
        }

        $response = $request->post(
            $this->base().'/v2/payments/captures/'.$captureId.'/refund',
            [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => $order->currency,
                ],
            ]
        );

        if (! $response->successful()) {
            return new RefundResult(false, null, $this->errorMessage($response));
        }

        return new RefundResult(true, $response->json('id'));
    }

    public function cancelSubscription(Subscription $subscription, bool $immediately = false): void
    {
        if (! $subscription->gateway_subscription_id) {
            throw new PaymentException('This subscription is not linked to a PayPal subscription yet.');
        }

        $response = Http::withToken($this->token())->acceptJson()->post(
            $this->base().'/v1/billing/subscriptions/'.$subscription->gateway_subscription_id.'/cancel',
            ['reason' => 'Cancelled by merchant or customer']
        );

        if (! $response->successful() && $response->status() !== 204) {
            throw new PaymentException($this->errorMessage($response));
        }
    }

    public function pauseSubscription(Subscription $subscription): void
    {
        if (! $subscription->gateway_subscription_id) {
            throw new PaymentException('This subscription is not linked to a PayPal subscription yet.');
        }

        $response = Http::withToken($this->token())->acceptJson()->post(
            $this->base().'/v1/billing/subscriptions/'.$subscription->gateway_subscription_id.'/suspend',
            ['reason' => 'Paused by merchant or customer']
        );

        if (! $response->successful() && $response->status() !== 204) {
            throw new PaymentException($this->errorMessage($response));
        }
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        if (! $subscription->gateway_subscription_id) {
            throw new PaymentException('This subscription is not linked to a PayPal subscription yet.');
        }

        $response = Http::withToken($this->token())->acceptJson()->post(
            $this->base().'/v1/billing/subscriptions/'.$subscription->gateway_subscription_id.'/activate',
            ['reason' => 'Resumed by merchant or customer']
        );

        if (! $response->successful() && $response->status() !== 204) {
            throw new PaymentException($this->errorMessage($response));
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $webhookId = (string) nexus_setting('payments.paypal.webhook_id', '');

        if ($webhookId === '') {
            return false;
        }

        $required = [
            'paypal-transmission-id',
            'paypal-transmission-time',
            'paypal-cert-url',
            'paypal-auth-algo',
            'paypal-transmission-sig',
        ];

        foreach ($required as $header) {
            if (! $request->header($header)) {
                return false;
            }
        }

        $response = Http::withToken($this->token())->acceptJson()->post(
            $this->base().'/v1/notifications/verify-webhook-signature',
            [
                'auth_algo' => $request->header('paypal-auth-algo'),
                'cert_url' => $request->header('paypal-cert-url'),
                'transmission_id' => $request->header('paypal-transmission-id'),
                'transmission_sig' => $request->header('paypal-transmission-sig'),
                'transmission_time' => $request->header('paypal-transmission-time'),
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($request->getContent(), true),
            ]
        );

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    public function handleWebhook(Request $request): void
    {
        $payload = json_decode($request->getContent(), true);
        $eventId = $payload['id'] ?? null;

        if (! $eventId) {
            return;
        }

        $event = WebhookEvent::query()->firstOrCreate(
            ['gateway' => $this->code(), 'event_id' => $eventId],
            ['event_type' => $payload['event_type'] ?? 'unknown', 'payload' => $payload]
        );

        if ($event->processed_at) {
            return;
        }

        $type = $payload['event_type'] ?? '';
        $resource = $payload['resource'] ?? [];

        DB::transaction(function () use ($event, $type, $resource): void {
            $locked = WebhookEvent::query()->whereKey($event->id)->lockForUpdate()->first();

            if (! $locked || $locked->processed_at) {
                return;
            }

            match ($type) {
                'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleSubscriptionActivated($resource),
                'BILLING.SUBSCRIPTION.CANCELLED' => $this->handleSubscriptionCancelled($resource),
                'BILLING.SUBSCRIPTION.SUSPENDED', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => $this->handleSubscriptionSuspended($resource),
                'PAYMENT.SALE.COMPLETED' => $this->handleSaleCompleted($resource),
                'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($resource),
                'PAYMENT.CAPTURE.DENIED' => $this->handleCaptureDenied($resource),
                'PAYMENT.CAPTURE.REFUNDED' => $this->handleCaptureRefunded($resource),
                default => null,
            };

            $locked->update(['processed_at' => now()]);
        });
    }

    private function startOrder(Order $order): PaymentResult
    {
        $returnUrl = route('storefront.checkout.confirmation', [
            'order' => $order->number,
            'access' => $order->access_token,
            'gateway' => 'paypal',
        ]);

        $response = Http::withToken($this->token())->acceptJson()->post($this->base().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->number,
                'description' => 'Order '.$order->number,
                'amount' => [
                    'currency_code' => $order->currency,
                    'value' => number_format($order->grand_total, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name' => mb_substr((string) nexus_setting('store.name', 'ShopKeeprr'), 0, 127),
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => route('storefront.checkout'),
            ],
        ]);

        if (! $response->successful()) {
            throw new PaymentException($this->errorMessage($response));
        }

        $data = $response->json();
        $approve = collect($data['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        if (! $approve) {
            throw new PaymentException('PayPal did not return an approval link.');
        }

        $order->payments()->create([
            'gateway' => $this->code(),
            'gateway_reference' => $data['id'] ?? null,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => 'pending',
            'payload' => ['paypal_order_id' => $data['id'] ?? null],
        ]);

        return PaymentResult::redirect($approve, $data['id'] ?? null);
    }

    private function startSubscription(Order $order): PaymentResult
    {
        $items = $order->loadMissing('items.plan')->items->filter(fn ($item) => $item->plan);

        if ($items->count() !== 1 || $order->items->count() !== 1) {
            throw new PaymentException('PayPal supports one subscription plan per checkout. Use Stripe for mixed subscription carts.');
        }

        $item = $items->first();
        $plan = $item->plan;
        $productResponse = Http::withToken($this->token())->acceptJson()->post($this->base().'/v1/catalogs/products', [
            'name' => mb_substr($item->product_name, 0, 127),
            'description' => mb_substr(nexus_setting('store.name', 'ShopKeeprr').' subscription product', 0, 256),
            'type' => 'SERVICE',
        ]);

        if (! $productResponse->successful()) {
            throw new PaymentException($this->errorMessage($productResponse));
        }

        $productId = $productResponse->json('id');
        $interval = strtoupper(match ($plan->interval) {
            'day' => 'DAY',
            'week' => 'WEEK',
            'year' => 'YEAR',
            default => 'MONTH',
        });

        $planResponse = Http::withToken($this->token())->acceptJson()->post($this->base().'/v1/billing/plans', [
            'product_id' => $productId,
            'name' => mb_substr($item->product_name.' — '.$plan->name, 0, 127),
            'billing_cycles' => [[
                'frequency' => ['interval_unit' => $interval, 'interval_count' => max(1, $plan->interval_count)],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => number_format($item->unit_price, 2, '.', ''),
                        'currency_code' => $order->currency,
                    ],
                ],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'payment_failure_threshold' => 2,
            ],
        ]);

        if (! $planResponse->successful()) {
            throw new PaymentException($this->errorMessage($planResponse));
        }

        $returnUrl = route('storefront.checkout.confirmation', [
            'order' => $order->number,
            'access' => $order->access_token,
            'gateway' => 'paypal',
        ]);

        $subscriptionResponse = Http::withToken($this->token())->acceptJson()->post($this->base().'/v1/billing/subscriptions', [
            'plan_id' => $planResponse->json('id'),
            'quantity' => (string) $item->quantity,
            'custom_id' => (string) $order->id,
            'application_context' => [
                'brand_name' => mb_substr((string) nexus_setting('store.name', 'ShopKeeprr'), 0, 127),
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => route('storefront.checkout'),
            ],
        ]);

        if (! $subscriptionResponse->successful()) {
            throw new PaymentException($this->errorMessage($subscriptionResponse));
        }

        $data = $subscriptionResponse->json();
        $approve = collect($data['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        if (! $approve) {
            throw new PaymentException('PayPal did not return a subscription approval link.');
        }

        $order->payments()->create([
            'gateway' => $this->code(),
            'gateway_reference' => $data['id'] ?? null,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => 'pending',
            'payload' => ['subscription_id' => $data['id'] ?? null],
        ]);

        return PaymentResult::redirect($approve, $data['id'] ?? null);
    }

    private function handleSubscriptionActivated(array $resource): void
    {
        $subscriptionId = (string) ($resource['id'] ?? '');
        $orderId = $resource['custom_id'] ?? null;
        $order = $orderId ? Order::query()->find($orderId) : null;

        if ($order) {
            app(OrderService::class)->markPaid($order, $this->code(), $subscriptionId, $resource, $subscriptionId);
            $order->subscriptions()->update(['gateway_subscription_id' => $subscriptionId]);
        }

        $subscription = Subscription::query()->where('gateway_subscription_id', $subscriptionId)->first();
        if ($subscription) {
            $subscription->update(['status' => 'active']);
            app(SubscriptionService::class)->event($subscription, 'activated', ['provider' => 'paypal']);
        }
    }

    private function handleSubscriptionCancelled(array $resource): void
    {
        $subscription = Subscription::query()->where('gateway_subscription_id', (string) ($resource['id'] ?? ''))->first();
        if ($subscription) {
            app(SubscriptionService::class)->cancel($subscription, true);
        }
    }

    private function handleSubscriptionSuspended(array $resource): void
    {
        $subscription = Subscription::query()->where('gateway_subscription_id', (string) ($resource['id'] ?? ''))->first();
        if ($subscription) {
            $subscription->update(['status' => 'past_due']);
            app(SubscriptionService::class)->event($subscription, 'suspended', ['provider' => 'paypal']);
        }
    }

    private function handleCaptureCompleted(array $resource): void
    {
        $order = $this->orderForCapture($resource);

        if (! $order || $order->isPaid()) {
            return;
        }

        $value = (float) ($resource['amount']['value'] ?? 0);
        $currency = strtoupper((string) ($resource['amount']['currency_code'] ?? ''));

        if (abs($value - (float) $order->grand_total) > 0.005 || $currency !== strtoupper($order->currency)) {
            Log::warning('PayPal webhook capture does not match the order', [
                'order' => $order->number,
                'amount' => $value,
                'currency' => $currency,
            ]);

            return;
        }

        app(OrderService::class)->markPaid($order, $this->code(), $resource['id'] ?? null, $resource);
    }

    private function handleCaptureRefunded(array $resource): void
    {
        $payment = $this->paymentForRefund($resource);

        if (! $payment?->order) {
            return;
        }

        // total_refunded_amount covers every refund on the capture, so a
        // repeated or partial webhook only records the outstanding difference.
        $total = (float) ($resource['seller_payable_breakdown']['total_refunded_amount']['value']
            ?? $resource['amount']['value']
            ?? 0);

        app(RefundService::class)->recordExternalRefund($payment->order, $payment, $total, 'PayPal');
    }

    private function paymentForRefund(array $resource): ?Payment
    {
        $captureId = $resource['supplementary_data']['related_ids']['capture_id'] ?? null;

        if (! $captureId && ! empty($resource['links'])) {
            $up = collect($resource['links'])->firstWhere('rel', 'up')['href'] ?? null;
            $captureId = $up ? basename((string) parse_url($up, PHP_URL_PATH)) : null;
        }

        if (! $captureId) {
            return null;
        }

        return Payment::query()
            ->where('gateway', $this->code())
            ->where(fn ($query) => $query
                ->where('gateway_payment_id', $captureId)
                ->orWhere('gateway_reference', $captureId))
            ->latest()
            ->first();
    }

    private function handleCaptureDenied(array $resource): void
    {
        $order = $this->orderForCapture($resource);

        if ($order && ! $order->isPaid()) {
            app(OrderService::class)->markFailed($order, $this->code(), $resource);
        }
    }

    private function orderForCapture(array $resource): ?Order
    {
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        if (! $paypalOrderId) {
            return null;
        }

        $payment = Payment::query()
            ->where('gateway', $this->code())
            ->where('gateway_reference', $paypalOrderId)
            ->first();

        return $payment?->order;
    }

    private function captureMatchesOrder(Order $order, array $data): bool
    {
        $unit = $data['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? [];
        $value = (float) ($capture['amount']['value'] ?? 0);
        $currency = strtoupper((string) ($capture['amount']['currency_code'] ?? ''));
        $reference = (string) ($unit['reference_id'] ?? '');

        return abs($value - (float) $order->grand_total) < 0.005
            && $currency === strtoupper($order->currency)
            && ($reference === '' || $reference === $order->number);
    }

    private function handleSaleCompleted(array $resource): void
    {
        $subscription = Subscription::query()->where('gateway_subscription_id', (string) ($resource['billing_agreement_id'] ?? ''))->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'failed_payments_count' => 0,
            'current_period_end' => app(SubscriptionService::class)->nextDate(now(), $subscription->interval, $subscription->interval_count),
            'next_billing_at' => app(SubscriptionService::class)->nextDate(now(), $subscription->interval, $subscription->interval_count),
        ]);

        app(SubscriptionService::class)->event($subscription, 'sale_completed', ['sale_id' => $resource['id'] ?? null]);
    }

    private function token(): string
    {
        $clientId = (string) nexus_setting('payments.paypal.client_id', '');
        $secret = (string) nexus_setting('payments.paypal.client_secret', '');
        $key = 'paypal.token.'.sha1($clientId.$secret.nexus_setting('payments.paypal.mode', 'sandbox'));

        return Cache::remember($key, 3000, function () use ($clientId, $secret) {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $secret)
                ->acceptJson()
                ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if (! $response->successful()) {
                throw new PaymentException('PayPal authentication failed.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function base(): string
    {
        return nexus_setting('payments.paypal.mode', 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('message') ?: $response->json('details.0.description');

        return $message ?: 'PayPal returned an error ('.$response->status().').';
    }
}
