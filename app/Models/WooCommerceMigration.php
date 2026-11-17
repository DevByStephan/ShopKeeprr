<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooCommerceMigration extends Model
{
    protected $table = 'woocommerce_migrations';

    protected $fillable = [
        'user_id', 'connection_id', 'source', 'original_filename', 'disk_path', 'extracted_path',
        'status', 'mode', 'files', 'options', 'stats', 'remote_state', 'progress',
        'error_report_path', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'options' => 'array',
            'stats' => 'array',
            'remote_state' => 'array',
            'progress' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WooCommerceConnection::class, 'connection_id');
    }

    public function isApi(): bool
    {
        return $this->source === 'api';
    }

    public function statusLabel(): string
    {
        return ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function typeCount(string $type): int
    {
        return collect($this->files ?? [])->where('type', $type)->count();
    }

    public function detectedSummary(): string
    {
        if ($this->isApi()) {
            return $this->connection?->name ? 'API: '.$this->connection->name : 'WooCommerce API';
        }

        $parts = [];

        foreach (['products', 'customers', 'orders', 'taxes'] as $type) {
            $count = $this->typeCount($type);

            if ($count > 0) {
                $parts[] = $count.' '.$type;
            }
        }

        return $parts ? implode(' · ', $parts) : 'No recognised files';
    }
}
