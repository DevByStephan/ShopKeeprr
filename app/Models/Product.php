<?php

namespace App\Models;

use App\Enums\StoreMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'store_id', 'category_id', 'vendor_id', 'name', 'slug', 'sku', 'type', 'short_description',
        'description', 'price', 'compare_at_price', 'cost_price', 'manage_stock',
        'stock_quantity', 'low_stock_threshold', 'min_order_quantity', 'weight',
        'dimensions', 'is_active', 'is_featured', 'is_wholesale_only',
        'requires_shipping', 'tax_class', 'meta_title', 'meta_description', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'compare_at_price' => 'float',
            'cost_price' => 'float',
            'manage_stock' => 'boolean',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'min_order_quantity' => 'integer',
            'weight' => 'float',
            'dimensions' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_wholesale_only' => 'boolean',
            'requires_shipping' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function tierPrices(): HasMany
    {
        return $this->hasMany(TierPrice::class);
    }

    public function subscriptionPlans(): HasMany
    {
        return $this->hasMany(SubscriptionPlan::class)->orderBy('sort_order');
    }

    public function activeSubscriptionPlans(): HasMany
    {
        return $this->subscriptionPlans()->where('is_active', true);
    }

    public function modeStates(): HasMany
    {
        return $this->hasMany(ProductModeState::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            // A suspended or unapproved seller must disappear from the
            // storefront immediately, even if their products stay active.
            ->where(function (Builder $q): void {
                $q->whereNull('vendor_id')
                    ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('status', Vendor::STATUS_APPROVED));
            });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Products that can actually be sold in the supplied business model.
     *
     * This is the query-level guard used by the storefront, cart, checkout
     * and APIs. Hiding navigation alone is never enough.
     */
    public function scopeAvailableInMode(Builder $query, StoreMode|string $mode): Builder
    {
        $mode = $mode instanceof StoreMode ? $mode : StoreMode::from($mode);

        $query->whereDoesntHave('modeStates', function (Builder $state) use ($mode): void {
            $state->where('mode', $mode->value)->where('is_visible', false);
        });

        $query->where(function (Builder $q): void {
            $q->whereNull('vendor_id')
                ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('status', Vendor::STATUS_APPROVED));
        });

        return match ($mode) {
            StoreMode::Retail => $query->whereNull('vendor_id')
                ->where('is_wholesale_only', false)
                ->whereDoesntHave('activeSubscriptionPlans'),
            StoreMode::B2B => $query->whereNull('vendor_id')
                ->whereDoesntHave('activeSubscriptionPlans'),
            StoreMode::Subscription => $query->whereNull('vendor_id')
                ->where('is_wholesale_only', false),
            StoreMode::MultiVendor => $query->where('is_wholesale_only', false)
                ->whereDoesntHave('activeSubscriptionPlans'),
        };
    }

    /**
     * Published products that are visible in the active model. Pass null to
     * only apply the model-state filter (useful in admin lists).
     */
    public function scopeVisibleInMode(Builder $query, ?StoreMode $mode = null): Builder
    {
        $mode ??= nexus_mode();

        $query->visible();

        if ($mode) {
            $query->availableInMode($mode);
        }

        return $query;
    }

    public function hasActiveSubscriptionPlans(): bool
    {
        if (array_key_exists('active_subscription_plans_count', $this->attributes)) {
            return (int) $this->attributes['active_subscription_plans_count'] > 0;
        }

        return $this->relationLoaded('activeSubscriptionPlans')
            ? $this->activeSubscriptionPlans->isNotEmpty()
            : $this->activeSubscriptionPlans()->exists();
    }

    public function supportsMode(StoreMode $mode): bool
    {
        if ($this->modeConflicts() !== []) {
            return false;
        }

        $hasPlans = $this->hasActiveSubscriptionPlans();
        $isVendorProduct = $this->vendor_id !== null;

        return match ($mode) {
            StoreMode::Retail => ! $isVendorProduct && ! $this->is_wholesale_only && ! $hasPlans,
            StoreMode::B2B => ! $isVendorProduct && ! $hasPlans,
            StoreMode::Subscription => ! $isVendorProduct && ! $this->is_wholesale_only,
            StoreMode::MultiVendor => ! $this->is_wholesale_only && ! $hasPlans,
        };
    }

    public function modeUnavailableReason(StoreMode $mode): ?string
    {
        $conflicts = $this->modeConflicts();

        if ($conflicts !== []) {
            return implode(' ', $conflicts);
        }

        $hasPlans = $this->hasActiveSubscriptionPlans();

        if ($this->vendor_id !== null && $mode !== StoreMode::MultiVendor) {
            return 'This product belongs to a seller and can only be sold in Multi-Vendor mode.';
        }

        if ($this->is_wholesale_only && in_array($mode, [StoreMode::Retail, StoreMode::Subscription], true)) {
            return 'Wholesale-only products need B2B or Multi-Vendor mode.';
        }

        if ($mode !== StoreMode::Subscription && $hasPlans) {
            return 'Active subscription plans need Subscription mode.';
        }

        return null;
    }

    /**
     * Contradictory attributes that no business model can sell safely.
     *
     * @return list<string>
     */
    public function modeConflicts(): array
    {
        $conflicts = [];

        if ($this->vendor_id !== null && $this->is_wholesale_only) {
            $conflicts[] = 'A seller product cannot also be wholesale-only.';
        }

        if ($this->vendor_id !== null && $this->hasActiveSubscriptionPlans()) {
            $conflicts[] = 'A seller product cannot use subscription plans.';
        }

        if ($this->is_wholesale_only && $this->hasActiveSubscriptionPlans()) {
            $conflicts[] = 'A wholesale-only product cannot use subscription plans.';
        }

        return $conflicts;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $like = '%'.$term.'%';
            $q->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('short_description', 'like', $like);
        });
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images->first();
    }

    public function imageUrl(): ?string
    {
        $path = $this->primaryImage()?->path;

        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset($path);
    }

    public function variantPrice(?ProductVariant $variant): float
    {
        return (float) ($variant?->price ?? $this->price);
    }

    /**
     * Resolve the price for the current shopper, including tier price breaks.
     */
    public function priceFor(?User $user = null, ?ProductVariant $variant = null, int $quantity = 1, ?StoreMode $mode = null): float
    {
        $base = $this->variantPrice($variant);
        $mode ??= nexus_mode();

        if ($mode !== StoreMode::B2B) {
            return round($base, 2);
        }

        $tier = $user?->tier;

        if (! $tier || ! $user?->hasWholesaleAccess()) {
            return round($base, 2);
        }

        $prices = $this->tierPrices
            ->where('tier_id', $tier->id)
            ->where('min_quantity', '<=', max(1, $quantity))
            ->filter(fn (TierPrice $tierPrice) => $variant
                ? in_array($tierPrice->variant_id, [$variant->id, null], true)
                : $tierPrice->variant_id === null)
            ->sortByDesc(fn (TierPrice $tierPrice) => ($tierPrice->min_quantity * 10) + ($tierPrice->variant_id ? 1 : 0));

        if ($prices->isNotEmpty()) {
            return round((float) $prices->first()->price, 2);
        }

        if ((float) $tier->discount_percent > 0) {
            return round($base * (1 - ((float) $tier->discount_percent / 100)), 2);
        }

        return round($base, 2);
    }

    public function compareAtPrice(?ProductVariant $variant = null): ?float
    {
        $price = $variant?->compare_at_price ?? $this->compare_at_price;

        return $price ? (float) $price : null;
    }

    public function stockFor(?ProductVariant $variant = null): int
    {
        if ($variant) {
            return (int) $variant->stock_quantity;
        }

        return (int) $this->stock_quantity;
    }

    public function isInStock(?ProductVariant $variant = null): bool
    {
        if (! $this->manage_stock) {
            return true;
        }

        return $this->stockFor($variant) > 0;
    }

    public function isVariable(): bool
    {
        return $this->type === 'variable' && $this->activeVariants->isNotEmpty();
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    public function discountPercent(): ?int
    {
        if (! $this->isOnSale()) {
            return null;
        }

        return (int) round((1 - ($this->price / $this->compare_at_price)) * 100);
    }

    public function subscriptionPlansEnabled(): bool
    {
        return $this->activeSubscriptionPlans->isNotEmpty();
    }
}
