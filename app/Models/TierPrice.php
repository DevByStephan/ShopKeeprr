<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierPrice extends Model
{
    protected $fillable = ['product_id', 'variant_id', 'tier_id', 'price', 'min_quantity'];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'min_quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(CustomerTier::class, 'tier_id');
    }
}
