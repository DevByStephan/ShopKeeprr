<?php

namespace App\Models;

use App\Enums\ModuleType;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = [
        'name', 'slug', 'version', 'type', 'provider', 'path', 'description', 'is_enabled', 'installed_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => ModuleType::class,
            'is_enabled' => 'boolean',
            'installed_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
