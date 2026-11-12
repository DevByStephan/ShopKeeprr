<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = ['store_id', 'name', 'countries', 'states', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'states' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class, 'zone_id')->orderBy('sort_order');
    }

    public function covers(string $country, ?string $state = null): bool
    {
        $countries = array_map('strtoupper', $this->countries ?? []);
        if (! in_array(strtoupper($country), $countries, true)) {
            return false;
        }

        if ($this->states && $state) {
            return in_array(strtoupper($state), array_map('strtoupper', $this->states), true);
        }

        return true;
    }
}
