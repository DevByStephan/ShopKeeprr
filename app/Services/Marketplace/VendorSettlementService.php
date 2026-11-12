<?php

namespace App\Services\Marketplace;

use App\Enums\StoreMode;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSplit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VendorSettlementService
{
    public function __construct(
        private readonly VendorSplitService $splits,
        private readonly StripeConnect $stripe,
        private readonly PayPalPayouts $paypal,
    ) {
    }

    /**
     * Called after a payment is captured. Payment success must never be rolled
     * back because a seller's payout is not ready, so every provider error is
     * recorded on the split and reported in the admin payout screen.
     */
    public function onOrderPaid(Order $order): void
    {
        if ($order->mode !== StoreMode::MultiVendor) {
            return;
        }

        try {
            if (! $order->vendorSplits()->exists()) {
                $this->splits->createForOrder($order);
            }

            $this->splits->releaseOnPayment($order);

            if ((bool) nexus_setting('marketplace.auto_payout', true)) {
                $this->transferStripeSplitsForOrder($order);
            }
        } catch (Throwable $exception) {
            Log::error('Vendor settlement failed after payment', [
                'order' => $order->number,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Credit every connected Stripe seller for this order.
     */
    public function transferStripeSplitsForOrder(Order $order): int
    {
        if ($order->payment_method !== 'stripe' && ! $order->payments()->where('gateway', 'stripe')->where('status', 'succeeded')->exists()) {
            return 0;
        }

        $splits = $order->vendorSplits()
            ->whereNull('stripe_transfer_id')
            ->whereIn('status', [VendorSplit::STATUS_HELD, VendorSplit::STATUS_PENDING])
            ->with('vendor')
            ->get();

        if ($splits->isEmpty()) {
            return 0;
        }

        $chargeId = $this->stripe->chargeIdForOrder($order);

        if (! $chargeId) {
            $splits->each(fn (VendorSplit $split) => $split->update([
                'failure_reason' => 'Waiting for the Stripe charge to become available.',
            ]));

            return 0;
        }

        $transferred = 0;

        foreach ($splits as $split) {
            if (! $split->vendor?->canReceiveStripeTransfers()) {
                continue;
            }

            try {
                $transfer = $this->stripe->createTransfer($split, $chargeId);
                $split->update([
                    'stripe_transfer_id' => $transfer['id'] ?? null,
                    'status' => VendorSplit::STATUS_PAID,
                    'paid_at' => now(),
                    'failure_reason' => null,
                ]);
                $transferred++;
            } catch (Throwable $exception) {
                $split->update(['failure_reason' => $exception->getMessage()]);
                Log::warning('Stripe seller transfer failed', [
                    'split' => $split->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $transferred;
    }

    /**
     * Pay every available split for one seller using their chosen method.
     *
     * @return array{paid: int, amount: float, reference: string|null}
     */
    public function payVendor(Vendor $vendor, ?User $actor = null, bool $force = false): array
    {
        if ($vendor->isSuspended() && ! $force) {
            throw new MarketplaceException('This seller is suspended. Reactivate the account or force a manual payout.');
        }

        $splits = VendorSplit::query()
            ->where('vendor_id', $vendor->id)
            ->payable()
            ->orderBy('available_at')
            ->get();

        if ($splits->isEmpty()) {
            throw new MarketplaceException('There is no released balance to pay out yet.');
        }

        $minimum = (float) nexus_setting('marketplace.minimum_payout', config('nexuscart.vendors.minimum_payout', 25));
        $total = round($splits->sum(fn (VendorSplit $split) => $split->netPayout()), 2);

        if ($total < $minimum) {
            throw new MarketplaceException('The balance is '.nexus_money($total).'; the minimum payout is '.nexus_money($minimum).'.');
        }

        return match ($vendor->payout_method) {
            'stripe' => $this->payWithStripe($vendor, $splits),
            'paypal' => $this->payWithPayPal($vendor, $splits),
            default => $this->payManually($vendor, $splits, $actor),
        };
    }

    /**
     * @param  Collection<int, VendorSplit>  $splits
     * @return array{paid: int, amount: float, reference: string|null}
     */
    private function payWithStripe(Vendor $vendor, Collection $splits): array
    {
        if (! $vendor->stripe_account_id || ! $vendor->stripe_payouts_enabled) {
            throw new MarketplaceException('This seller has not finished Stripe onboarding, so Stripe cannot receive a transfer.');
        }

        $paid = 0;
        $amount = 0.0;

        foreach ($splits as $split) {
            try {
                if (! $split->stripe_transfer_id) {
                    $chargeId = $this->stripe->chargeIdForOrder($split->order);

                    if (! $chargeId) {
                        throw new MarketplaceException('The original Stripe charge is not available for split '.$split->id.'.');
                    }

                    $transfer = $this->stripe->createTransfer($split, $chargeId);
                    $split->stripe_transfer_id = $transfer['id'] ?? null;
                }

                $split->update([
                    'status' => VendorSplit::STATUS_PAID,
                    'paid_at' => now(),
                    'failure_reason' => null,
                ]);

                $paid++;
                $amount += $split->netPayout();
            } catch (Throwable $exception) {
                $split->update(['failure_reason' => $exception->getMessage()]);
            }
        }

        if ($paid === 0) {
            throw new MarketplaceException('Stripe rejected every transfer. Check the seller account and the failure notes.');
        }

        return ['paid' => $paid, 'amount' => round($amount, 2), 'reference' => null];
    }

    /**
     * @param  Collection<int, VendorSplit>  $splits
     * @return array{paid: int, amount: float, reference: string|null}
     */
    private function payWithPayPal(Vendor $vendor, Collection $splits): array
    {
        $batchId = $this->paypal->send($vendor, $splits);

        foreach ($splits as $split) {
            $split->update([
                'status' => VendorSplit::STATUS_PAID,
                'paid_at' => now(),
                'paypal_batch_id' => $batchId,
                'failure_reason' => null,
            ]);
        }

        return [
            'paid' => $splits->count(),
            'amount' => round($splits->sum(fn (VendorSplit $split) => $split->netPayout()), 2),
            'reference' => $batchId,
        ];
    }

    /**
     * @param  Collection<int, VendorSplit>  $splits
     * @return array{paid: int, amount: float, reference: string|null}
     */
    private function payManually(Vendor $vendor, Collection $splits, ?User $actor): array
    {
        DB::transaction(function () use ($splits, $actor): void {
            foreach ($splits as $split) {
                $split->update([
                    'status' => VendorSplit::STATUS_PAID,
                    'paid_at' => now(),
                    'failure_reason' => null,
                ]);
            }

            if ($splits->isNotEmpty()) {
                \App\Models\AuditLog::query()->create([
                    'user_id' => $actor?->id,
                    'action' => 'vendor.payout.manual',
                    'model_type' => Vendor::class,
                    'model_id' => $splits->first()->vendor_id,
                    'new_values' => ['splits' => $splits->pluck('id')->all()],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'url' => request()->fullUrl(),
                    'method' => 'POST',
                    'created_at' => now(),
                ]);
            }
        });

        return [
            'paid' => $splits->count(),
            'amount' => round($splits->sum(fn (VendorSplit $split) => $split->netPayout()), 2),
            'reference' => 'manual',
        ];
    }

    /**
     * Reverse the seller's share of a customer refund. Stripe transfers are
     * reversed through the API; PayPal payouts cannot be clawed back, so those
     * are flagged for manual recovery instead of silently losing the money.
     */
    public function onOrderRefunded(Order $order, float $amount): void
    {
        if ($order->mode !== StoreMode::MultiVendor) {
            return;
        }

        $clawbacks = $this->splits->recordRefund($order, $amount);

        foreach ($clawbacks as $splitId => $clawback) {
            $split = VendorSplit::query()->find($splitId);

            if (! $split || $clawback <= 0) {
                continue;
            }

            if ($split->stripe_transfer_id) {
                $reversed = $this->stripe->reverseTransfer($split, $clawback);

                if ($reversed !== null) {
                    $split->update([
                        'stripe_transfer_reversed' => round((float) $split->stripe_transfer_reversed + $clawback, 2),
                        'failure_reason' => null,
                    ]);
                }
            } elseif ($split->paypal_batch_id) {
                $split->update([
                    'failure_reason' => 'Refund of '.nexus_money($clawback).' happened after a PayPal payout. Recover this balance from the seller.',
                ]);
            }
        }
    }
}
