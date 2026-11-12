<?php

namespace App\Services\Payments;

use App\Services\Payments\Gateways\ManualGateway;
use App\Services\Payments\Gateways\PayPalGateway;
use App\Services\Payments\Gateways\StripeGateway;
use InvalidArgumentException;

class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $gateways;

    public function __construct()
    {
        $this->gateways = [
            'stripe' => new StripeGateway,
            'paypal' => new PayPalGateway,
            'manual' => new ManualGateway,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function available(bool $subscriptionsOnly = false): array
    {
        $available = [];

        foreach ($this->gateways as $code => $gateway) {
            if (! $gateway->isEnabled()) {
                continue;
            }

            if ($subscriptionsOnly && ! $gateway->supportsSubscriptions()) {
                continue;
            }

            $available[$code] = $gateway->label();
        }

        return $available;
    }

    public function get(string $code): PaymentGateway
    {
        if (! isset($this->gateways[$code])) {
            throw new InvalidArgumentException('Unknown payment gateway: '.$code);
        }

        $gateway = $this->gateways[$code];

        if (! $gateway->isEnabled()) {
            throw new PaymentException('This payment method is not available.');
        }

        return $gateway;
    }

    /**
     * Refunds must still work after a gateway is disabled for new orders.
     */
    public function gatewayForRefund(string $code): PaymentGateway
    {
        if (! isset($this->gateways[$code])) {
            throw new InvalidArgumentException('Unknown payment gateway: '.$code);
        }

        return $this->gateways[$code];
    }

    public function gatewayForWebhook(string $code): PaymentGateway
    {
        if (! isset($this->gateways[$code])) {
            throw new InvalidArgumentException('Unknown payment gateway: '.$code);
        }

        return $this->gateways[$code];
    }
}
