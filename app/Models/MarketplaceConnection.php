<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceConnection extends Model
{
    protected $fillable = [
        'store_url',
        'site_id',
        'site_name',
        'account_name',
        'account_email',
        'access_token',
        'token_last_four',
        'connected_at',
        'last_synced_at',
        'meta',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }

    public function isConnected(): bool
    {
        return trim((string) $this->access_token) !== '';
    }

    public function maskedToken(): string
    {
        return $this->token_last_four ? '••••'.$this->token_last_four : '••••';
    }
}
