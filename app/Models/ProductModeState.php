<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModeState extends Model
{
    public const ORIGIN_SYSTEM = 'system';

    public const ORIGIN_MANUAL = 'manual';

    protected $fillable = [
        'product_id', 'mode', 'is_visible', 'origin', 'reason', 'archived_at', 'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'archived_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isSystemArchived(): bool
    {
        return ! $this->is_visible && $this->origin === self::ORIGIN_SYSTEM;
    }
}
