<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'store_id', 'parent_id', 'name', 'slug', 'description', 'image',
        'sort_order', 'is_active', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset($this->image) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
