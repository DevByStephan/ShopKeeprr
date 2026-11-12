<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'store_id', 'code', 'type', 'value', 'minimum_subtotal', 'maximum_discount',
        'usage_limit', 'usage_limit_per_customer', 'used_count', 'starts_at', 'expires_at',
        'applies_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'float',
            'minimum_subtotal' => 'float',
            'maximum_discount' => 'float',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'applies_to' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isValidFor(float $subtotal, ?User $user = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->minimum_subtotal !== null && $subtotal < $this->minimum_subtotal) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($user && $this->usage_limit_per_customer !== null) {
            $used = $this->usages()->where('user_id', $user->id)->count();
            if ($used >= $this->usage_limit_per_customer) {
                return false;
            }
        }

        return true;
    }

    public function discountFor(float $subtotal, float $shipping = 0): float
    {
        $discount = match ($this->type) {
            DiscountType::Percentage => $subtotal * ($this->value / 100),
            DiscountType::Fixed => min($subtotal, $this->value),
            DiscountType::FreeShipping => $shipping,
        };

        if ($this->maximum_discount !== null) {
            $discount = min($discount, $this->maximum_discount);
        }

        return round(max(0, $discount), 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
