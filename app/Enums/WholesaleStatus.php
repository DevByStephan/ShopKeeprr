<?php

namespace App\Enums;

enum WholesaleStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
