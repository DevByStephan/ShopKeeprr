<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\RefundResult;
use Illuminate\Http\Request;

class ManualGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Pay by invoice';
    }

    public function isEnabled(): bool
    {
        return (bool) nexus_setting('payments.manual.enabled', true);
    }

    public function supportsSubscriptions(): bool
    {
        return true;
    }

    public function start(Order $order): PaymentResult
    {
        $order->payments()->create([
            'gateway' => $this->code(),
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => 'pending',
        ]);

        return PaymentResult::pending((string) nexus_setting('payments.manual.instructions', 'We will email you payment instructions.'));
    }

    public function complete(Order $order, array $payload = []): PaymentResult
    {
        return PaymentResult::pending('This order is awaiting manual payment confirmation.');
    }

    public function refund(Order $order, Payment $payment, float $amount, ?string $idempotencyKey = null): RefundResult
    {
        return new RefundResult(true, null, 'Recorded as a manual refund. Complete the refund in your payment provider.');
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function handleWebhook(Request $request): void
    {
    }
}
