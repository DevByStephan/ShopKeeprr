<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $fillable = [
        'name', 'slug', 'domain', 'email', 'phone', 'currency', 'currency_symbol',
        'country', 'logo', 'is_default', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function default(): ?self
    {
        static $store = false;

        if ($store === false) {
            $store = self::query()->where('is_default', true)->first()
                ?? self::query()->first();
        }

        return $store;
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? asset($this->logo) : null;
    }
}
