<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return str_replace('_', ' ', ucfirst($this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'emerald',
            self::Processing => 'blue',
            self::Pending => 'amber',
            self::OnHold => 'violet',
            self::Cancelled, self::Failed => 'rose',
            self::Refunded => 'slate',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
