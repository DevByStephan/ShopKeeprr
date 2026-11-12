<?php

namespace App\Services\Marketplace;

/**
 * Commission math lives in one place and always works in integer cents.
 *
 * Supported rules:
 *   percentage — percent of the vendor's discounted item subtotal
 *   fixed      — a flat amount per vendor order
 *   combined   — percent plus the flat amount
 *
 * A commission can never be negative or larger than the amount being split.
 */
class CommissionCalculator
{
    /**
     * @param  array{type?: string, percent?: float|int|string, fixed?: float|int|string}  $rule
     */
    public function calculate(int $basisCents, array $rule): int
    {
        $basisCents = max(0, $basisCents);
        $type = (string) ($rule['type'] ?? 'percentage');
        $percent = max(0.0, min(100.0, (float) ($rule['percent'] ?? 0)));
        $fixedCents = max(0, (int) round(((float) ($rule['fixed'] ?? 0)) * 100));

        $commission = match ($type) {
            'fixed' => $fixedCents,
            'combined' => (int) round($basisCents * $percent / 100) + $fixedCents,
            default => (int) round($basisCents * $percent / 100),
        };

        return max(0, min($basisCents, $commission));
    }

    public function cents(float|int|string|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    public function money(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
