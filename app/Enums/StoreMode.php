<?php

namespace App\Enums;

enum StoreMode: string
{
    case Retail = 'retail';
    case B2B = 'b2b';
    case Subscription = 'subscription';
    case MultiVendor = 'multivendor';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Retail',
            self::B2B => 'B2B Wholesale',
            self::Subscription => 'Subscription Box',
            self::MultiVendor => 'Multi-Vendor Marketplace',
        };
    }

    public function tagline(): string
    {
        return match ($this) {
            self::Retail => 'A focused storefront for everyday shoppers.',
            self::B2B => 'Tiered wholesale pricing, minimum order quantities and approved accounts.',
            self::Subscription => 'Recurring boxes and plans with automated renewal workflows.',
            self::MultiVendor => 'Many independent sellers, one checkout and automatic commission splits.',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::B2B => 'Show customer-specific pricing, enforce MOQ rules and approve trade accounts before they can order.',
            self::Subscription => 'Sell recurring products on weekly, monthly or annual billing cycles with self-service management.',
            self::MultiVendor => 'Give every seller their own portal and payout account. ShopKeeprr splits each order using a percentage, a fixed fee or both.',
            default => 'Sell products to the public with variants, inventory, coupons and a streamlined checkout.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Retail => 'shopping-bag',
            self::B2B => 'building-storefront',
            self::Subscription => 'arrow-path',
            self::MultiVendor => 'users',
        };
    }

    public function allowsVendors(): bool
    {
        return $this === self::MultiVendor;
    }
}
