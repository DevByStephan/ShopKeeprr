<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'variant_id', 'vendor_id', 'subscription_plan_id', 'product_name',
        'variant_name', 'sku', 'quantity', 'unit_price', 'line_subtotal', 'discount_amount',
        'tax_amount', 'line_total', 'commission_amount', 'vendor_total', 'options',
        'is_subscription', 'subscription_interval', 'subscription_interval_count',
        'subscription_trial_days',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'float',
            'line_subtotal' => 'float',
            'discount_amount' => 'float',
            'tax_amount' => 'float',
            'line_total' => 'float',
            'options' => 'array',
            'commission_amount' => 'float',
            'vendor_total' => 'float',
            'is_subscription' => 'boolean',
            'subscription_interval_count' => 'integer',
            'subscription_trial_days' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
