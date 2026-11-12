<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Delivered = 'delivered';
    case Returned = 'returned';

    public function label(): string
    {
        return str_replace('_', ' ', ucfirst($this->value));
    }
}
