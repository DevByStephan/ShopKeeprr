<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function code(): string;

    public function label(): string;

    public function isEnabled(): bool;

    public function supportsSubscriptions(): bool;

    public function start(Order $order): PaymentResult;

    /**
     * Complete a redirect-based payment after the customer returns from the gateway.
     *
     * @param  array<string, mixed>  $payload
     */
    public function complete(Order $order, array $payload = []): PaymentResult;

    public function refund(Order $order, Payment $payment, float $amount, ?string $idempotencyKey = null): RefundResult;

    public function verifyWebhook(Request $request): bool;

    public function handleWebhook(Request $request): void;
}
