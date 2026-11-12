<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'store_id', 'user_id', 'order_id', 'product_id', 'variant_id', 'subscription_plan_id',
        'gateway', 'gateway_subscription_id', 'status', 'quantity', 'price', 'currency',
        'interval', 'interval_count', 'trial_ends_at', 'current_period_start', 'current_period_end',
        'next_billing_at', 'cancel_at_period_end', 'cancelled_at', 'paused_at', 'ends_at',
        'failed_payments_count', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'quantity' => 'integer',
            'price' => 'float',
            'interval_count' => 'integer',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'next_billing_at' => 'datetime',
            'cancel_at_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'paused_at' => 'datetime',
            'ends_at' => 'datetime',
            'failed_payments_count' => 'integer',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->latest();
    }

    public function isActive(): bool
    {
        return $this->status->isUsable();
    }

    public function intervalLabel(): string
    {
        $unit = $this->interval_count > 1 ? $this->interval.'s' : $this->interval;

        return 'Every '.$this->interval_count.' '.$unit;
    }
}
