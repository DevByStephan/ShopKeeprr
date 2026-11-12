<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Mail\PaymentFailed;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Services\Payments\PaymentManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SubscriptionService
{
    public function activateForOrder(Order $order, string $gateway, ?string $gatewaySubscriptionId = null): void
    {
        if (! $order->is_subscription) {
            return;
        }

        $order->loadMissing('items.plan', 'items.product');

        DB::transaction(function () use ($order, $gateway, $gatewaySubscriptionId) {
            foreach ($order->items as $item) {
                if (! $item->subscription_plan_id || ! $item->plan) {
                    continue;
                }

                $existing = Subscription::query()
                    ->where('order_id', $order->id)
                    ->where('subscription_plan_id', $item->subscription_plan_id)
                    ->first();

                if ($existing) {
                    continue;
                }

                $plan = $item->plan;
                $trialDays = (int) $plan->trial_days;
                $start = now();
                $next = $this->nextDate($start, $plan->interval, $plan->interval_count);
                $trialEnds = $trialDays > 0 ? $start->copy()->addDays($trialDays) : null;

                if ($trialEnds) {
                    $next = $trialEnds->copy();
                }

                $subscription = Subscription::query()->create([
                    'store_id' => $order->store_id,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'subscription_plan_id' => $plan->id,
                    'gateway' => $gateway,
                    'gateway_subscription_id' => $gatewaySubscriptionId,
                    'status' => $trialEnds ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                    'quantity' => $item->quantity,
                    'price' => $item->unit_price,
                    'currency' => $order->currency,
                    'interval' => $plan->interval,
                    'interval_count' => $plan->interval_count,
                    'trial_ends_at' => $trialEnds,
                    'current_period_start' => $start,
                    'current_period_end' => $next,
                    'next_billing_at' => $next,
                    'meta' => ['order_number' => $order->number],
                ]);

                $this->event($subscription, 'created', [
                    'order' => $order->number,
                    'gateway' => $gateway,
                ]);
            }
        });
    }

    public function attachGatewayId(Subscription $subscription, ?string $gatewayId): void
    {
        if (! $gatewayId || $subscription->gateway_subscription_id === $gatewayId) {
            return;
        }

        $subscription->update(['gateway_subscription_id' => $gatewayId]);
        $this->event($subscription, 'gateway_attached', ['gateway_subscription_id' => $gatewayId]);
    }

    public function markPastDue(Subscription $subscription): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::PastDue,
            'failed_payments_count' => $subscription->failed_payments_count + 1,
        ]);

        $this->event($subscription, 'payment_failed');

        try {
            if ($subscription->order) {
                Mail::to($subscription->user->email)->send(new PaymentFailed($subscription->order));
            }
        } catch (Throwable) {
            // Keep the lifecycle transition even when mail is unavailable.
        }
    }

    public function cancelWithGateway(Subscription $subscription, bool $immediately = false): void
    {
        $gateway = app(PaymentManager::class)->gatewayForWebhook($subscription->gateway);

        if (method_exists($gateway, 'cancelSubscription')) {
            $gateway->cancelSubscription($subscription, $immediately);
        }

        $this->cancel($subscription, $immediately);
    }

    public function pauseWithGateway(Subscription $subscription): void
    {
        $gateway = app(PaymentManager::class)->gatewayForWebhook($subscription->gateway);

        if (method_exists($gateway, 'pauseSubscription')) {
            $gateway->pauseSubscription($subscription);
        }

        $this->pause($subscription);
    }

    public function resumeWithGateway(Subscription $subscription): void
    {
        $gateway = app(PaymentManager::class)->gatewayForWebhook($subscription->gateway);

        if (method_exists($gateway, 'resumeSubscription')) {
            $gateway->resumeSubscription($subscription);
        }

        $this->resume($subscription);
    }

    public function cancel(Subscription $subscription, bool $immediately = false): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_at_period_end' => $immediately ? null : ($subscription->current_period_end ?? now()),
            'ends_at' => $immediately ? now() : ($subscription->current_period_end ?? now()),
        ]);

        $this->event($subscription, 'cancelled', ['immediate' => $immediately]);
    }

    public function pause(Subscription $subscription): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::Paused,
            'paused_at' => now(),
        ]);

        $this->event($subscription, 'paused');
    }

    public function resume(Subscription $subscription): void
    {
        $next = $subscription->next_billing_at && $subscription->next_billing_at->isFuture()
            ? $subscription->next_billing_at
            : $this->nextDate(now(), $subscription->interval, $subscription->interval_count);

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'paused_at' => null,
            'next_billing_at' => $next,
        ]);

        $this->event($subscription, 'resumed');
    }

    public function expire(Subscription $subscription): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::Expired,
            'ends_at' => now(),
        ]);

        $this->event($subscription, 'expired');
    }

    public function nextDate(Carbon $from, string $interval, int $count): Carbon
    {
        $count = max(1, $count);

        return match ($interval) {
            'day' => $from->copy()->addDays($count),
            'week' => $from->copy()->addWeeks($count),
            'year' => $from->copy()->addYearsNoOverflow($count),
            default => $from->copy()->addMonthsNoOverflow($count),
        };
    }

    public function event(Subscription $subscription, string $type, array $data = []): SubscriptionEvent
    {
        return $subscription->events()->create([
            'type' => $type,
            'data' => $data,
        ]);
    }

    public function findForGateway(string $gateway, string $gatewayId): ?Subscription
    {
        return Subscription::query()
            ->where('gateway', $gateway)
            ->where('gateway_subscription_id', $gatewayId)
            ->first();
    }
}
