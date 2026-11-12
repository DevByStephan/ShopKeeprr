<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'store_id', 'user_id', 'number', 'access_token', 'status', 'payment_status', 'fulfillment_status',
        'currency', 'subtotal', 'discount_total', 'shipping_total', 'tax_total',
        'grand_total', 'paid_total', 'refunded_total', 'coupon_id', 'coupon_code',
        'customer_email', 'customer_name', 'customer_phone', 'shipping_address',
        'billing_address', 'shipping_method', 'shipping_method_title', 'payment_method',
        'transaction_id', 'customer_note', 'admin_note', 'ip_address', 'user_agent',
        'mode', 'mode_revision', 'is_subscription', 'placed_at', 'paid_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'mode' => StoreMode::class,
            'mode_revision' => 'integer',
            'subtotal' => 'float',
            'discount_total' => 'float',
            'shipping_total' => 'float',
            'tax_total' => 'float',
            'grand_total' => 'float',
            'paid_total' => 'float',
            'refunded_total' => 'float',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'is_subscription' => 'boolean',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function vendorSplits(): HasMany
    {
        return $this->hasMany(VendorSplit::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(fn (Builder $q) => $q->where('number', 'like', $like)
            ->orWhere('customer_email', 'like', $like)
            ->orWhere('customer_name', 'like', $like));
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true);
    }

    public function balanceDue(): float
    {
        return max(0, round($this->grand_total - $this->paid_total, 2));
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [OrderStatus::Pending, OrderStatus::Processing, OrderStatus::OnHold], true);
    }

    public function confirmationUrl(): string
    {
        return route('storefront.checkout.confirmation', ['order' => $this->number, 'access' => $this->access_token]);
    }

    public function shippingAddressLine(): string
    {
        return $this->formatAddress($this->shipping_address);
    }

    public function billingAddressLine(): string
    {
        return $this->formatAddress($this->billing_address ?: $this->shipping_address);
    }

    private function formatAddress(?array $address): string
    {
        if (! $address) {
            return '—';
        }

        return collect([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            trim(($address['city'] ?? '').' '.($address['state'] ?? '').' '.($address['postcode'] ?? '')),
            $address['country'] ?? null,
        ])->filter()->implode(', ');
    }
}
