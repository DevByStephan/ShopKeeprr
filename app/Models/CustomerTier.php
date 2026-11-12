<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerTier extends Model
{
    protected $fillable = [
        'store_id', 'name', 'slug', 'description', 'discount_percent', 'is_wholesale',
        'min_order_total', 'requires_approval', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'float',
            'is_wholesale' => 'boolean',
            'min_order_total' => 'float',
            'requires_approval' => 'boolean',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tier_id');
    }

    public function tierPrices(): HasMany
    {
        return $this->hasMany(TierPrice::class, 'tier_id');
    }
}
