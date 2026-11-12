<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontPage extends Model
{
    protected $fillable = [
        'key',
        'title',
        'blocks',
        'published_blocks',
        'is_enabled',
        'published_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'published_blocks' => 'array',
            'is_enabled' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->is_enabled && is_array($this->published_blocks) && $this->published_blocks !== [];
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->blocks !== $this->published_blocks;
    }
}
