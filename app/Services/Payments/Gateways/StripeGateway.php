<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeGateway implements PaymentGateway
{
    private const API = 'https://api.stripe.com/v1';

    public function code(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Card via Stripe';
    }

    public function isEnabled(): bool
    {
        return (bool) nexus_setting('payments.stripe.enabled', false)
            && (string) nexus_setting('payments.stripe.secret_key', '') !== '';
    }

    public function supportsSubscriptions(): bool
    {
        return true;
    }

    public function start(Order $order): PaymentResult
    {
        $secret = (string) nexus_setting('payments.stripe.secret_key', '');

        if ($secret === '') {
            throw new PaymentException('Stripe is not configured yet.');
        }

        $reused = $this->reuseOpenSession($order, $secret);

        if ($reused !== null) {
            return $reused;
        }

        $order->loadMissing('items.plan', 'items.product');
        $payload = [
            'mode' => $order->is_subscription ? 'subscription' : 'payment',
            'success_url' => $this->successUrl($order),
            'cancel_url' => route('storefront.checkout'),
            'metadata[order_id]' => $order->id,
            'billing_address_collection' => 'auto',
        ];

        if ($order->is_subscription) {
            if ($order->items->contains(fn ($item) => ! $item->is_subscription || ! $item->plan)) {
                throw new PaymentException('Stripe subscriptions cannot be mixed with one-time products in the same checkout.');
            }

            foreach ($order->items->values() as $index => $item) {
                $plan = $item->plan;
                $payload["line_items[{$index}][quantity]"] = $item->quantity;
                $payload["line_items[{$index}][price_data][currency]"] = strtolower($order->currency);
                $payload["line_items[{$index}][price_data][unit_amount]"] = (int) round($item->unit_price * 100);
                $payload["line_items[{$index}][price_data][product_data][name]"] = $item->product_name.' — '.$plan->name;
                $payload["line_items[{$index}][price_data][recurring][interval]"] = $plan->interval;
                $payload["line_items[{$index}][price_data][recurring][interval_count]"] = $plan->interval_count;
            }

            $payload['subscription_data[metadata][order_id]'] = $order->id;
        } else {
            $payload['line_items[0][quantity]'] = 1;
            $payload['line_items[0][price_data][currency]'] = strtolower($order->currency);
            $payload['line_items[0][price_data][unit_amount]'] = (int) round($order->grand_total * 100);
            $payload['line_items[0][price_data][product_data][name]'] = 'Order '.$order->number;
            $payload['line_items[0][price_data][product_data][description]'] = $order->items->sum('quantity').' item(s) from '.nexus_setting('store.name', 'ShopKeeprr');
            $payload['payment_intent_data[metadata][order_id]'] = $order->id;
        }

        if ($order->customer_email) {
            $payload['customer_email'] = $order->customer_email;
        }

        $response = Http::withToken($secret)
            ->asForm()
            ->acceptJson()
            ->post(self::API.'/checkout/sessions', $payload);

        if (! $response->successful()) {
            throw new PaymentException($this->errorMessage($response));
        }

        $session = $response->json();

        $order->payments()->create([
            'gateway' => $this->code(),
            'gateway_reference' => $session['id'] ?? null,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => 'pending',
            'payload' => ['session_id' => $session['id'] ?? null],
        ]);

        if (! empty($session['url'])) {
            return PaymentResult::redirect($session['url'], $session['id'] ?? null);
        }

        throw new PaymentException('Stripe did not return a checkout URL.');
    }

    public function complete(Order $order, array $payload = []): PaymentResult
    {
        $sessionId = (string) ($payload['session_id'] ?? '');

        if ($sessionId === '') {
            return PaymentResult::pending('Stripe did not return a checkout session.');
        }

        $payment = $order->payments()->where('gateway', $this->code())->where('gateway_reference', $sessionId)->first();

        if (! $payment) {
            throw new PaymentException('This payment does not belong to the order.');
        }

        $secret = (string) nexus_setting('payments.stripe.secret_key', '');
        $response = Http::withToken($secret)->acceptJson()->get(self::API.'/checkout/sessions/'.$sessionId);

        if (! $response->successful()) {
            return PaymentResult::pending('Stripe is still confirming this payment.');
        }

        $session = $response->json();
        $paymentStatus = (string) ($session['payment_status'] ?? '');
        $subscriptionId = $session['subscription'] ?? null;
        $paid = $paymentStatus === 'paid'
            || (! empty($subscriptionId) && $paymentStatus === 'no_payment_required');

        if (! $paid) {
            return PaymentResult::pending('Stripe is still confirming this payment.', $sessionId);
        }

        if (! $this->sessionMatchesOrder($order, $session, $paymentStatus === 'no_payment_required')) {
            Log::warning('Stripe session does not match the order', [
                'order' => $order->number,
                'session' => $sessionId,
                'amount_total' => $session['amount_total'] ?? null,
                'currency' => $session['currency'] ?? null,
            ]);

            return PaymentResult::pending('Stripe returned a payment that does not match this order.', $sessionId);
        }

        app(OrderService::class)->markPaid($order, $this->code(), $session['payment_intent'] ?? $sessionId, $session, $subscriptionId);

        if ($subscriptionId) {
            $order->subscriptions()->update(['gateway_subscription_id' => $subscriptionId]);
        }

        return PaymentResult::paid($session['payment_intent'] ?? $sessionId);
    }

    public function refund(Order $order, Payment $payment, float $amount, ?string $idempotencyKey = null): RefundResult
    {
        $secret = (string) nexus_setting('payments.stripe.secret_key', '');
        $intent = $payment->gateway_payment_id;

        if (! $intent) {
            return new RefundResult(false, null, 'Stripe did not record a payment intent for this order.');
        }

        $request = Http::withToken($secret)->asForm()->acceptJson();

        if ($idempotencyKey) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $request->post(self::API.'/refunds', [
            'payment_intent' => $intent,
            'amount' => (int) round($amount * 100),
        ]);

        if (! $response->successful()) {
            return new RefundResult(false, null, $this->errorMessage($response));
        }

        return new RefundResult(true, $response->json('id'));
    }

    public function cancelSubscription(Subscription $subscription, bool $immediately = false): void
    {
        $secret = (string) nexus_setting('payments.stripe.secret_key', '');

        if (! $subscription->gateway_subscription_id) {
            throw new PaymentException('This subscription is not linked to a Stripe subscription yet.');
        }

        $url = self::API.'/subscriptions/'.$subscription->gateway_subscription_id;
        $response = $immediately
            ? Http::withToken($secret)->acceptJson()->delete($url)
            : Http::withToken($secret)->asForm()->acceptJson()->post($url, ['cancel_at_period_end' => 'true']);

        if (! $response->successful()) {
            throw new PaymentException($this->errorMessage($response));
        }
    }

    public function pauseSubscription(Subscription $subscription): void
    {
        $secret = (string) nexus_setting('payments.stripe.secret_key', '');

        if (! $subscription->gateway_subscription_id) {
            throw new PaymentException('This subscription is not linked to a Stripe subscription yet.');
        }

        $response = Http::withToken($secret)->asForm()->acceptJson()->post(self::API.'/subscriptions/'.$subscription->gateway_subscription_id, [
            'pause_collection[behavior]' => 'void',
        ]);

        if (! $response->successful()) {
            throw new PaymentException($this->errorMessage($response));
        }
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        $secret = (string) nexus_setting('payments.stripe.secret_key', '');

        if (! $subscription->gateway_subscription_id) {
            throw new PaymentException('This subscription is not linked to a Stripe subscription yet.');
        }

        $response = Http::withToken($secret)->asForm()->acceptJson()->post(self::API.'/subscriptions/'.$subscription->gateway_subscription_id, [
            'pause_collection' => '',
            'cancel_at_period_end' => 'false',
        ]);

        if (! $response->successful()) {
            throw new PaymentException($this->errorMessage($response));
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) nexus_setting('payments.stripe.webhook_secret', '');
        $header = (string) $request->header('Stripe-Signature', '');

        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || ! $signatures || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
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
            ['event_type' => $payload['type'] ?? 'unknown', 'payload' => $payload]
        );

        if ($event->processed_at) {
            return;
        }

        $type = $payload['type'] ?? '';
        $object = $payload['data']['object'] ?? [];

        // A duplicate delivery can arrive while the first one is still being
        // processed. Serialize on the event row so side effects happen once.
        DB::transaction(function () use ($event, $type, $object): void {
            $locked = WebhookEvent::query()->whereKey($event->id)->lockForUpdate()->first();

            if (! $locked || $locked->processed_at) {
                return;
            }

            match ($type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($object),
                'invoice.paid' => $this->handleInvoicePaid($object),
                'invoice.payment_failed' => $this->handleInvoiceFailed($object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
                'charge.refunded' => $this->handleChargeRefunded($object),
                default => null,
            };

            $locked->update(['processed_at' => now()]);
        });
    }

    private function handleCheckoutCompleted(array $session): void
    {
        $orderId = $session['metadata']['order_id'] ?? null;
        if (! $orderId) {
            return;
        }

        $order = Order::query()->find($orderId);
        if (! $order) {
            return;
        }

        $paymentStatus = (string) ($session['payment_status'] ?? '');
        $subscriptionId = $session['subscription'] ?? null;
        $paid = $paymentStatus === 'paid'
            || (! empty($subscriptionId) && $paymentStatus === 'no_payment_required');

        if (! $paid || ! $this->sessionMatchesOrder($order, $session, $paymentStatus === 'no_payment_required')) {
            Log::warning('Stripe webhook session does not match the order', [
                'order' => $order->number,
                'payment_status' => $paymentStatus,
                'amount_total' => $session['amount_total'] ?? null,
                'currency' => $session['currency'] ?? null,
            ]);

            return;
        }

        app(OrderService::class)->markPaid(
            $order,
            $this->code(),
            $session['payment_intent'] ?? ($session['id'] ?? null),
            $session,
            $subscriptionId
        );

        if ($subscriptionId) {
            $order->subscriptions()->update(['gateway_subscription_id' => $subscriptionId]);
        }
    }

    private function handleInvoicePaid(array $invoice): void
    {
        $subscriptionId = $invoice['subscription'] ?? null;
        if (! $subscriptionId) {
            return;
        }

        $subscription = app(SubscriptionService::class)->findForGateway($this->code(), $subscriptionId);
        if (! $subscription) {
            return;
        }

        $periodEnd = $invoice['lines']['data'][0]['period']['end'] ?? null;
        $subscription->update([
            'status' => 'active',
            'failed_payments_count' => 0,
            'current_period_end' => $periodEnd ? now()->setTimestamp((int) $periodEnd) : $subscription->current_period_end,
            'next_billing_at' => $periodEnd ? now()->setTimestamp((int) $periodEnd) : $subscription->next_billing_at,
        ]);

        app(SubscriptionService::class)->event($subscription, 'invoice_paid', ['invoice' => $invoice['id'] ?? null]);
    }

    private function handleInvoiceFailed(array $invoice): void
    {
        $subscriptionId = $invoice['subscription'] ?? null;
        if (! $subscriptionId) {
            return;
        }

        $subscription = app(SubscriptionService::class)->findForGateway($this->code(), $subscriptionId);
        if ($subscription) {
            app(SubscriptionService::class)->markPastDue($subscription);
        }
    }

    private function handleSubscriptionDeleted(array $object): void
    {
        $subscription = app(SubscriptionService::class)->findForGateway($this->code(), (string) ($object['id'] ?? ''));
        if ($subscription) {
            app(SubscriptionService::class)->cancel($subscription, true);
        }
    }

    private function handleChargeRefunded(array $charge): void
    {
        $order = null;
        $payment = null;
        $orderId = $charge['metadata']['order_id'] ?? null;

        if ($orderId) {
            $order = Order::query()->find($orderId);
        }

        // PaymentIntent metadata is not always copied onto the Charge object.
        if (! empty($charge['payment_intent'])) {
            $payment = Payment::query()
                ->where('gateway', $this->code())
                ->where('gateway_payment_id', $charge['payment_intent'])
                ->first();

            $order ??= $payment?->order;
        }

        if (! $order) {
            return;
        }

        // Refunds started in the Stripe dashboard never pass through
        // ShopKeeprr. Record the provider total so order totals, the refund
        // list and CSV exports stay accurate.
        app(RefundService::class)->recordExternalRefund(
            $order,
            $payment,
            ((int) ($charge['amount_refunded'] ?? 0)) / 100,
            'Stripe'
        );
    }

    /**
     * A shopper who reloads checkout, or opens it in two tabs, should not end
     * up with two live Stripe sessions for the same order. Reuse the existing
     * open session when the amount and currency still match.
     */
    private function reuseOpenSession(Order $order, string $secret): ?PaymentResult
    {
        $pending = $order->payments()
            ->where('gateway', $this->code())
            ->where('status', 'pending')
            ->whereNotNull('gateway_reference')
            ->latest()
            ->first();

        if (! $pending || ! $pending->created_at?->gt(now()->subHours(23))) {
            return null;
        }

        $response = Http::withToken($secret)->acceptJson()->get(self::API.'/checkout/sessions/'.$pending->gateway_reference);

        if (! $response->successful()) {
            return null;
        }

        $session = $response->json();
        $status = (string) ($session['status'] ?? '');

        if ($status === 'complete') {
            // The shopper already paid; send them to the confirmation page so
            // it can verify the session instead of charging them twice.
            return PaymentResult::redirect($order->confirmationUrl().'&gateway=stripe&session_id='.urlencode((string) $pending->gateway_reference));
        }

        $amount = $session['amount_total'] ?? null;

        if ($status !== 'open'
            || ! is_numeric($amount)
            || (int) $amount !== (int) round($order->grand_total * 100)
            || strtolower((string) ($session['currency'] ?? '')) !== strtolower($order->currency)
            || empty($session['url'])) {
            if ($status !== 'open') {
                $pending->update(['status' => 'expired']);
            }

            return null;
        }

        return PaymentResult::redirect($session['url'], $pending->gateway_reference);
    }

    private function sessionMatchesOrder(Order $order, array $session, bool $allowZero = false): bool
    {
        $metadataOrder = $session['metadata']['order_id'] ?? null;

        if ($metadataOrder !== null && (string) $metadataOrder !== (string) $order->id) {
            return false;
        }

        if (strtolower((string) ($session['currency'] ?? '')) !== strtolower($order->currency)) {
            return false;
        }

        if ($allowZero) {
            return true;
        }

        $amount = $session['amount_total'] ?? null;

        return $amount !== null && (int) $amount === (int) round($order->grand_total * 100);
    }

    private function successUrl(Order $order): string
    {
        $base = route('storefront.checkout.confirmation', ['order' => $order->number, 'access' => $order->access_token]);

        return $base.(str_contains($base, '?') ? '&' : '?').'gateway=stripe&session_id={CHECKOUT_SESSION_ID}';
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        return $message ?: 'Stripe returned an error ('.$response->status().').';
    }
}
