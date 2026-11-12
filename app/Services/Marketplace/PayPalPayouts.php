<?php

namespace App\Services\Marketplace;

use App\Models\Vendor;
use App\Models\VendorSplit;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * PayPal does not allow a standard merchant checkout to split a single
 * payment between several receivers. ShopKeeprr therefore collects the order
 * on the store's PayPal account and pays sellers out through the official
 * PayPal Payouts API after the hold period. This keeps the checkout inside
 * PayPal's consumer rules and gives the merchant a normal Payouts audit trail.
 *
 * The store's PayPal app must have the Payouts capability approved. Errors
 * from PayPal are surfaced in the payout screen instead of being hidden.
 */
class PayPalPayouts
{
    public function isConfigured(): bool
    {
        return (string) nexus_setting('payments.paypal.client_id', '') !== ''
            && (string) nexus_setting('payments.paypal.client_secret', '') !== '';
    }

    /**
     * @param  Collection<int, VendorSplit>  $splits
     */
    public function send(Vendor $vendor, Collection $splits): string
    {
        if (! $this->isConfigured()) {
            throw new MarketplaceException('PayPal Payouts is not configured. Add the PayPal client ID and secret in Settings → Payments.');
        }

        if (! filter_var($vendor->paypal_email, FILTER_VALIDATE_EMAIL)) {
            throw new MarketplaceException('Add a valid PayPal email to this seller before paying out.');
        }

        $token = $this->token();
        $batchId = 'nexus-'.Str::lower(Str::random(12));

        $items = $splits->values()->map(function (VendorSplit $split, int $index) use ($vendor): array {
            return [
                'recipient_type' => 'EMAIL',
                'amount' => [
                    'value' => number_format($split->netPayout(), 2, '.', ''),
                    'currency' => strtoupper($split->currency ?: 'USD'),
                ],
                'receiver' => $vendor->paypal_email,
                'note' => 'ShopKeeprr seller payout for order #'.$split->order_id,
                'sender_item_id' => 'split-'.$split->id,
                'notification_language' => 'en-US',
            ];
        })->all();

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(45)
            ->post($this->endpoint().'/v1/payments/payouts', [
                'sender_batch_header' => [
                    'sender_batch_id' => $batchId,
                    'email_subject' => 'You have a payout from '.nexus_setting('store.name', 'our store'),
                    'email_message' => 'Thanks for selling with us. Your payout is on its way.',
                ],
                'items' => $items,
            ]);

        $this->throwUnlessSuccessful($response, 'PayPal rejected the payout.');

        return (string) ($response->json('batch_header.payout_batch_id') ?? $batchId);
    }

    private function token(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(
                (string) nexus_setting('payments.paypal.client_id', ''),
                (string) nexus_setting('payments.paypal.client_secret', '')
            )
            ->acceptJson()
            ->timeout(30)
            ->post($this->endpoint().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        $this->throwUnlessSuccessful($response, 'PayPal authentication failed.');

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new MarketplaceException('PayPal did not return an access token.');
        }

        return $token;
    }

    private function endpoint(): string
    {
        return nexus_setting('payments.paypal.mode', 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function throwUnlessSuccessful(Response $response, string $message): void
    {
        if ($response->successful()) {
            return;
        }

        $detail = $response->json('message')
            ?? $response->json('details.0.issue')
            ?? $response->json('error_description');

        throw new MarketplaceException($message.' '.(is_string($detail) && $detail !== '' ? $detail : 'HTTP '.$response->status().'.'));
    }
}
