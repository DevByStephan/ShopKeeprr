<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'product_id', 'name', 'interval', 'interval_count', 'price', 'trial_days', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'interval_count' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function billingLabel(): string
    {
        $unit = $this->interval_count > 1 ? $this->interval.'s' : $this->interval;

        return $this->interval_count.' '.$unit;
    }
}
