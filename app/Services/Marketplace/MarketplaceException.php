<?php

namespace App\Services\Marketplace;

use RuntimeException;

class MarketplaceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }

    public static function unavailable(string $message = 'The ShopKeeprr Store could not be reached. Try again shortly.'): self
    {
        return new self($message);
    }
}
