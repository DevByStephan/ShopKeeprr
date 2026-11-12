<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingMethod extends Model
{
    protected $fillable = [
        'zone_id', 'name', 'type', 'cost', 'per_kg', 'free_threshold', 'min_weight', 'max_weight',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'float',
            'per_kg' => 'float',
            'free_threshold' => 'float',
            'min_weight' => 'float',
            'max_weight' => 'float',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }

    public function supportsWeight(float $weight): bool
    {
        if ($this->min_weight !== null && $weight < $this->min_weight) {
            return false;
        }

        if ($this->max_weight !== null && $weight > $this->max_weight) {
            return false;
        }

        return true;
    }

    public function rateFor(float $subtotal, float $weight): float
    {
        if ($this->free_threshold !== null && $subtotal >= $this->free_threshold) {
            return 0.0;
        }

        $rate = $this->cost;

        if ($this->type === 'weight_based' && $this->per_kg) {
            $rate += $weight * $this->per_kg;
        }

        return round($rate, 2);
    }
}
