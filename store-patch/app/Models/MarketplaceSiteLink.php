<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSiteLink extends Model
{
    protected $fillable = [
        'user_id',
        'site_id',
        'site_url',
        'site_name',
        'platform_version',
        'device_code_hash',
        'user_code',
        'expires_at',
        'approved_at',
        'token',
        'token_hash',
        'last_used_at',
        'revoked_at',
        'meta',
    ];

    protected $hidden = [
        'token',
        'token_hash',
        'device_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->approved_at !== null
            && $this->revoked_at === null
            && $this->token_hash !== null;
    }

    public function accountLabel(): string
    {
        return $this->user?->name ?: $this->user?->email ?: 'ShopKeeprr account';
    }
}
