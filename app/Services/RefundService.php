<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        private readonly PaymentManager $payments,
    ) {
    }

    /**
     * Refund a captured payment, reserving the order row for the provider
     * call so two simultaneous refunds cannot spend the same balance.
     */
    public function refund(Order $order, float $amount, ?string $reason = null, ?User $actor = null, ?string $requestId = null): Refund
    {
        $amount = round($amount, 2);
        $requestId = $requestId ?: (string) Str::uuid();

        if ($amount <= 0) {
            throw ValidationException::withMessages(['refund' => 'Enter a refund amount greater than zero.']);
        }

        $refund = DB::transaction(function () use ($order, $amount, $reason, $actor, $requestId): Refund {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $existing = $locked->refunds()->where('request_id', $requestId)->first();

            if ($existing) {
                return $existing;
            }

            if (! $locked->isPaid()) {
                throw ValidationException::withMessages(['refund' => 'This order has no captured payment to refund.']);
            }

            $payment = $locked->payments()->where('status', 'succeeded')->latest()->first();

            if (! $payment) {
                throw ValidationException::withMessages(['refund' => 'There is no captured payment to refund.']);
            }

            $remaining = round((float) $locked->paid_total - (float) $locked->refunded_total, 2);

            if ($remaining <= 0) {
                throw ValidationException::withMessages(['refund' => 'This order has already been fully refunded.']);
            }

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'refund' => 'Only '.nexus_money($remaining, $locked->currency).' remains refundable on this order.',
                ]);
            }

            $gateway = $this->payments->gatewayForRefund($payment->gateway);
            $result = $gateway->refund($locked, $payment, $amount, 'nexus-refund-'.$requestId);

            if (! $result->success) {
                throw new PaymentException($result->message ?: 'The payment provider rejected this refund.');
            }

            $refund = $locked->refunds()->create([
                'payment_id' => $payment->id,
                'request_id' => $requestId,
                'user_id' => $actor?->id,
                'amount' => $amount,
                'reason' => $reason,
                'gateway_refund_id' => $result->reference,
                'status' => 'completed',
            ]);

            $refunded = round((float) $locked->refunded_total + $amount, 2);
            $fullyRefunded = $refunded >= (float) $locked->paid_total;
            $fromStatus = $locked->status->value;

            $locked->update([
                'refunded_total' => $refunded,
                'payment_status' => $fullyRefunded ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
                'status' => $fullyRefunded ? OrderStatus::Refunded : $locked->status,
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $locked->id,
                'user_id' => $actor?->id,
                'from_status' => $fromStatus,
                'to_status' => $locked->status->value,
                'note' => 'Refund of '.nexus_money($amount, $locked->currency).' recorded via '.$payment->gateway.'.',
            ]);

            return $refund;
        });

        app(\App\Services\Marketplace\VendorSettlementService::class)
            ->onOrderRefunded($order->fresh(), $amount);

        return $refund;
    }

    /**
     * Reconcile a refund that was issued outside ShopKeeprr (Stripe dashboard,
     * PayPal dispute, bank chargeback). Only the difference between the amount
     * the provider says is refunded and what is already on the order is
     * recorded, so repeated webhooks are safe.
     */
    public function recordExternalRefund(Order $order, ?Payment $payment, float $totalRefunded, string $source): ?Refund
    {
        $totalRefunded = round($totalRefunded, 2);

        if ($totalRefunded <= 0) {
            return null;
        }

        $refund = DB::transaction(function () use ($order, $payment, $totalRefunded, $source): ?Refund {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isPaid()) {
                return null;
            }

            $already = round((float) $locked->refunded_total, 2);
            $delta = round(min($totalRefunded, (float) $locked->paid_total) - $already, 2);

            if ($delta <= 0) {
                return null;
            }

            $payment ??= $locked->payments()->where('status', 'succeeded')->latest()->first();

            $refund = $locked->refunds()->create([
                'payment_id' => $payment?->id,
                'user_id' => null,
                'amount' => $delta,
                'reason' => 'Recorded automatically from '.$source.'.',
                'gateway_refund_id' => null,
                'status' => 'completed',
            ]);

            $refunded = round($already + $delta, 2);
            $fullyRefunded = $refunded >= (float) $locked->paid_total;
            $fromStatus = $locked->status->value;

            $locked->update([
                'refunded_total' => $refunded,
                'payment_status' => $fullyRefunded ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
                'status' => $fullyRefunded ? OrderStatus::Refunded : $locked->status,
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => $locked->status->value,
                'note' => 'Refund of '.nexus_money($delta, $locked->currency).' received from '.$source.'.',
            ]);

            return $refund;
        });

        if ($refund !== null) {
            app(\App\Services\Marketplace\VendorSettlementService::class)
                ->onOrderRefunded($order->fresh(), (float) $refund->amount);
        }

        return $refund;
    }
}
