<?php

namespace App\Services;

use RuntimeException;

class StoreModeSwitchException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(public readonly array $report, string $message)
    {
        parent::__construct($message);
    }
}
