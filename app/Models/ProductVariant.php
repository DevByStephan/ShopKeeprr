<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'name', 'sku', 'price', 'compare_at_price', 'stock_quantity',
        'weight', 'options', 'image', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'compare_at_price' => 'float',
            'stock_quantity' => 'integer',
            'weight' => 'float',
            'options' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function displayName(): string
    {
        if ($this->options) {
            return collect($this->options)->map(fn ($value, $key) => "$key: $value")->implode(' / ');
        }

        return $this->name;
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset($this->image) : null;
    }
}
