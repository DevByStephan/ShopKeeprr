<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return str_replace('_', ' ', ucfirst($this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'emerald',
            self::Authorized => 'blue',
            self::Pending => 'amber',
            self::PartiallyRefunded => 'violet',
            self::Refunded => 'slate',
            self::Failed => 'rose',
        };
    }
}
