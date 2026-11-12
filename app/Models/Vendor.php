<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'store_id', 'user_id', 'name', 'slug', 'email', 'phone', 'country', 'description', 'status',
        'commission_type', 'commission_percent', 'commission_fixed', 'payout_method',
        'paypal_email', 'stripe_account_id', 'stripe_charges_enabled', 'stripe_payouts_enabled',
        'stripe_details_submitted', 'payout_hold_days', 'admin_notes', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_percent' => 'float',
            'commission_fixed' => 'float',
            'stripe_charges_enabled' => 'boolean',
            'stripe_payouts_enabled' => 'boolean',
            'stripe_details_submitted' => 'boolean',
            'payout_hold_days' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(VendorSplit::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * The vendor's rule wins only when it sets a type; otherwise the platform
     * rule is used. This is how "inherit" stays the safe default.
     *
     * @return array{type: string, percent: float, fixed: float}
     */
    public function commissionRule(): array
    {
        $type = in_array($this->commission_type, ['percentage', 'fixed', 'combined'], true)
            ? $this->commission_type
            : (string) nexus_setting('marketplace.commission_type', config('nexuscart.vendors.commission_type', 'percentage'));

        return [
            'type' => in_array($type, ['percentage', 'fixed', 'combined'], true) ? $type : 'percentage',
            'percent' => $this->commission_percent !== null
                ? (float) $this->commission_percent
                : (float) nexus_setting('marketplace.commission_percent', config('nexuscart.vendors.commission_percent', 10)),
            'fixed' => $this->commission_fixed !== null
                ? (float) $this->commission_fixed
                : (float) nexus_setting('marketplace.commission_fixed', config('nexuscart.vendors.commission_fixed', 0)),
        ];
    }

    public function payoutHoldDays(): int
    {
        if ($this->payout_hold_days !== null) {
            return max(0, (int) $this->payout_hold_days);
        }

        return max(0, (int) nexus_setting('marketplace.payout_hold_days', config('nexuscart.vendors.payout_hold_days', 7)));
    }

    public function canReceiveStripeTransfers(): bool
    {
        return $this->payout_method === 'stripe'
            && $this->stripe_account_id
            && $this->stripe_payouts_enabled;
    }

    public function payoutMethodLabel(): string
    {
        return match ($this->payout_method) {
            'stripe' => 'Stripe Connect',
            'paypal' => 'PayPal Payouts',
            default => 'Manual payout',
        };
    }

    public function initials(): string
    {
        return str((string) $this->name)->explode(' ')->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
    }
}
