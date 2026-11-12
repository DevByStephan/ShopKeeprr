<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = ['store_id', 'name', 'country', 'state', 'rate', 'is_active', 'priority'];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function appliesTo(string $country, ?string $state): bool
    {
        if (! $this->is_active || strtoupper($this->country) !== strtoupper($country)) {
            return false;
        }

        return ! $this->state || strtoupper($this->state) === strtoupper((string) $state);
    }
}
