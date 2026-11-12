<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSplit extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_HELD = 'held';

    public const STATUS_PAID = 'paid';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'order_id', 'vendor_id', 'status', 'currency', 'subtotal', 'discount_total',
        'commission_total', 'payout_total', 'refunded_total', 'payout_method',
        'stripe_transfer_id', 'stripe_transfer_reversed', 'paypal_batch_id',
        'failure_reason', 'available_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'float',
            'discount_total' => 'float',
            'commission_total' => 'float',
            'payout_total' => 'float',
            'refunded_total' => 'float',
            'stripe_transfer_reversed' => 'float',
            'available_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scopePayable(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_HELD])
            ->where('payout_total', '>', 0)
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now());
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function netPayout(): float
    {
        return round(max(0, (float) $this->payout_total - (float) $this->refunded_total), 2);
    }
}
