<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WooCommerceConnection extends Model
{
    protected $table = 'woocommerce_connections';

    protected $fillable = [
        'user_id', 'name', 'base_url', 'consumer_key', 'consumer_secret',
        'status', 'last_tested_at', 'last_error', 'metadata',
    ];

    protected $hidden = [
        'consumer_key',
        'consumer_secret',
    ];

    protected function casts(): array
    {
        return [
            'consumer_key' => 'encrypted',
            'consumer_secret' => 'encrypted',
            'last_tested_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function migrations(): HasMany
    {
        return $this->hasMany(WooCommerceMigration::class, 'connection_id');
    }

    public function apiBaseUrl(): string
    {
        $url = rtrim(trim((string) $this->base_url), '/');

        if (str_ends_with($url, '/wp-json/wc/v3')) {
            return $url;
        }

        if (str_ends_with($url, '/wp-json')) {
            return $url.'/wc/v3';
        }

        return $url.'/wp-json/wc/v3';
    }

    public function statusLabel(): string
    {
        return ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function counts(): array
    {
        return (array) data_get($this->metadata, 'counts', []);
    }
}
