<?php

namespace App\Services\Marketplace;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Vendor;
use App\Models\VendorSplit;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Connect helpers for Express sellers.
 *
 * ShopKeeprr never stores bank details. Sellers complete onboarding and KYC on
 * Stripe's hosted flow; this application only stores the connected account id
 * and the capability flags Stripe reports.
 *
 * Marketplace orders use separate charges and transfers: the customer is
 * charged in full on the platform account, then each seller receives a
 * transfer. This is the supported pattern for carts that contain more than
 * one seller, and it lets the platform reverse a transfer when a refund is
 * issued. Transfers are tagged with the order and split so reconciliation is
 * possible from either side.
 */
class StripeConnect
{
    private const API = 'https://api.stripe.com/v1';

    public function isConfigured(): bool
    {
        return (string) nexus_setting('payments.stripe.secret_key', '') !== '';
    }

    public function createExpressAccount(Vendor $vendor): string
    {
        $response = $this->request()->post(self::API.'/accounts', [
            'type' => 'express',
            'email' => $vendor->email,
            // Stripe requires the seller's own country. The platform country
            // is only a fallback for sellers created before this field existed.
            'country' => strtoupper((string) ($vendor->country ?: nexus_setting('store.country', 'US'))),
            'metadata[vendor_id]' => $vendor->id,
            'metadata[vendor_slug]' => $vendor->slug,
            'capabilities[card_payments][requested]' => 'true',
            'capabilities[transfers][requested]' => 'true',
        ]);

        $this->throwUnlessSuccessful($response, 'Stripe could not create the seller account.');

        return (string) $response->json('id');
    }

    public function accountLink(Vendor $vendor, string $refreshUrl, string $returnUrl): string
    {
        if (! $vendor->stripe_account_id) {
            throw new MarketplaceException('Connect a Stripe account before creating an onboarding link.');
        }

        $response = $this->request()->post(self::API.'/account_links', [
            'account' => $vendor->stripe_account_id,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        $this->throwUnlessSuccessful($response, 'Stripe could not create the onboarding link.');

        return (string) $response->json('url');
    }

    /**
     * Refresh the capability flags from Stripe. Called when the seller returns
     * from onboarding and by the admin refresh action.
     *
     * @return array<string, mixed>
     */
    public function refreshAccount(Vendor $vendor): array
    {
        if (! $vendor->stripe_account_id) {
            throw new MarketplaceException('This seller does not have a Stripe account yet.');
        }

        $response = $this->request()->get(self::API.'/accounts/'.$vendor->stripe_account_id);
        $this->throwUnlessSuccessful($response, 'Stripe could not load the seller account.');

        $account = (array) $response->json();

        $vendor->update([
            'stripe_charges_enabled' => (bool) ($account['charges_enabled'] ?? false),
            'stripe_payouts_enabled' => (bool) ($account['payouts_enabled'] ?? false),
            'stripe_details_submitted' => (bool) ($account['details_submitted'] ?? false),
            'payout_method' => $vendor->payout_method === 'manual' && ($account['details_submitted'] ?? false)
                ? 'stripe'
                : $vendor->payout_method,
        ]);

        return $account;
    }

    public function createTransfer(VendorSplit $split, string $chargeId): array
    {
        $vendor = $split->vendor;

        if (! $vendor?->stripe_account_id) {
            throw new MarketplaceException('The seller has not connected a Stripe account.');
        }

        // Always transfer the net balance: a refund can land before a queued
        // seller transfer is created, and the seller must not be overpaid.
        $amount = (int) round($split->netPayout() * 100);

        if ($amount <= 0) {
            throw new MarketplaceException('This split has no seller balance to transfer.');
        }

        $response = $this->request()
            ->withHeaders(['Idempotency-Key' => 'nexus-vendor-split-'.$split->id])
            ->post(self::API.'/transfers', [
                'amount' => $amount,
                'currency' => strtolower($split->currency ?: 'usd'),
                'destination' => $vendor->stripe_account_id,
                'source_transaction' => $chargeId,
                'transfer_group' => 'order-'.$split->order_id,
                'metadata[order_id]' => $split->order_id,
                'metadata[vendor_id]' => $vendor->id,
                'metadata[vendor_split_id]' => $split->id,
            ]);

        $this->throwUnlessSuccessful($response, 'Stripe could not transfer the seller balance.');

        return (array) $response->json();
    }

    public function reverseTransfer(VendorSplit $split, float $amount): ?array
    {
        if (! $split->stripe_transfer_id || $amount <= 0) {
            return null;
        }

        $cents = (int) round($amount * 100);

        if ($cents <= 0) {
            return null;
        }

        $response = $this->request()
            ->withHeaders(['Idempotency-Key' => 'nexus-vendor-reversal-'.$split->id.'-'.$cents.'-'.(int) round(((float) $split->refunded_total) * 100)])
            ->post(self::API.'/transfers/'.$split->stripe_transfer_id.'/reversals', [
                'amount' => $cents,
                'metadata[vendor_split_id]' => $split->id,
                'metadata[order_id]' => $split->order_id,
                'metadata[reason]' => 'refund',
            ]);

        if (! $response->successful()) {
            Log::warning('Stripe transfer reversal failed', [
                'split' => $split->id,
                'error' => $this->errorMessage($response),
            ]);

            $split->update(['failure_reason' => 'Reversal failed: '.$this->errorMessage($response)]);

            return null;
        }

        return (array) $response->json();
    }

    /**
     * Find the charge created by the order's PaymentIntent. Transfers use
     * source_transaction so the funds are available without waiting for the
     * platform's normal payout schedule.
     */
    public function chargeIdForOrder(Order $order): ?string
    {
        $payment = $order->payments()
            ->where('gateway', 'stripe')
            ->where('status', 'succeeded')
            ->latest()
            ->first();

        if (! $payment?->gateway_payment_id) {
            return null;
        }

        $response = $this->request()->get(
            self::API.'/payment_intents/'.$payment->gateway_payment_id.'?expand[]=latest_charge'
        );

        if (! $response->successful()) {
            return null;
        }

        $charge = $response->json('latest_charge');

        return is_array($charge) ? ($charge['id'] ?? null) : (is_string($charge) ? $charge : null);
    }

    public function chargeIdForPayment(Payment $payment): ?string
    {
        if (! $payment->gateway_payment_id) {
            return null;
        }

        $response = $this->request()->get(
            self::API.'/payment_intents/'.$payment->gateway_payment_id.'?expand[]=latest_charge'
        );

        if (! $response->successful()) {
            return null;
        }

        $charge = $response->json('latest_charge');

        return is_array($charge) ? ($charge['id'] ?? null) : (is_string($charge) ? $charge : null);
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken((string) nexus_setting('payments.stripe.secret_key', ''))
            ->acceptJson()
            ->asForm()
            ->timeout(30);
    }

    private function throwUnlessSuccessful(Response $response, string $message): void
    {
        if (! $response->successful()) {
            throw new MarketplaceException($message.' '.$this->errorMessage($response));
        }
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) && $message !== '' ? $message : 'HTTP '.$response->status().'.';
    }
}
