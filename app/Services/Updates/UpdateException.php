<?php

namespace App\Services\Updates;

use App\Models\UpdateRun;
use RuntimeException;

class UpdateException extends RuntimeException
{
    public function __construct(string $message, public ?UpdateRun $run = null)
    {
        parent::__construct($message);
    }
}
