<?php

namespace App\Services\Payments;

class PaymentResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $reference = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function redirect(string $url, ?string $reference = null): self
    {
        return new self('redirect', $url, $reference);
    }

    public static function pending(?string $message = null, ?string $reference = null): self
    {
        return new self('pending', null, $reference, $message);
    }

    public static function paid(?string $reference = null, ?string $message = null): self
    {
        return new self('paid', null, $reference, $message);
    }

    public function isRedirect(): bool
    {
        return $this->status === 'redirect' && $this->redirectUrl !== null;
    }
}
