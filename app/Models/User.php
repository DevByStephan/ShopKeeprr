<?php

namespace App\Models;

use App\Enums\WholesaleStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'store_id',
        'name',
        'email',
        'password',
        'role',
        'tier_id',
        'wholesale_status',
        'company_name',
        'tax_id',
        'phone',
        'wholesale_applied_at',
        'wholesale_approved_at',
        'admin_notes',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wholesale_status' => WholesaleStatus::class,
            'wholesale_applied_at' => 'datetime',
            'wholesale_approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'tier_id' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(CustomerTier::class, 'tier_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function vendorProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'staff'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isWholesaleApproved(): bool
    {
        return $this->wholesale_status === WholesaleStatus::Approved;
    }

    public function hasWholesaleAccess(): bool
    {
        return $this->isAdmin() || $this->isWholesaleApproved();
    }

    public function firstName(): string
    {
        return str((string) $this->name)->before(' ')->toString();
    }

    public function initials(): string
    {
        return str($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part) => strtoupper($part[0] ?? ''))
            ->implode('');
    }
}
