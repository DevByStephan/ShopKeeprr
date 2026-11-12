<?php

namespace Modules\Bundles\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bundle extends Model
{
    protected $table = 'product_bundles';

    protected $fillable = ['name', 'slug', 'description', 'discount_type', 'discount_value', 'is_active'];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_bundle_items')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function subtotal(): float
    {
        return round((float) $this->products->sum(fn (Product $product) => (float) $product->price * (int) $product->pivot->quantity), 2);
    }

    public function discount(): float
    {
        $subtotal = $this->subtotal();

        return round(min($subtotal, match ($this->discount_type) {
            'fixed' => (float) $this->discount_value,
            default => $subtotal * ((float) $this->discount_value / 100),
        }), 2);
    }

    public function bundlePrice(): float
    {
        return round(max(0, $this->subtotal() - $this->discount()), 2);
    }

    public function isAvailable(): bool
    {
        return $this->products->every(fn (Product $product) => $product->is_active
            && (! $product->manage_stock || $product->stock_quantity >= (int) $product->pivot->quantity));
    }

    public function discountLabel(): string
    {
        return $this->discount_type === 'fixed'
            ? nexus_money((float) $this->discount_value).' off'
            : rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.').'% off';
    }
}
