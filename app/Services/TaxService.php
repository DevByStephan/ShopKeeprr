<?php

namespace App\Services;

use App\Models\TaxRate;

class TaxService
{
    public function rateFor(?string $country, ?string $state): float
    {
        if (! $country) {
            return 0.0;
        }

        $rate = TaxRate::query()
            ->where('is_active', true)
            ->where('country', strtoupper($country))
            ->where(function ($query) use ($state) {
                $query->whereNull('state');
                if ($state) {
                    $query->orWhere('state', strtoupper($state));
                }
            })
            ->orderByDesc('priority')
            ->orderByRaw('CASE WHEN state IS NULL THEN 1 ELSE 0 END')
            ->first();

        return $rate ? (float) $rate->rate : 0.0;
    }

    public function calculate(float $taxable, float $rate, bool $inclusive = false): float
    {
        if ($taxable <= 0 || $rate <= 0) {
            return 0.0;
        }

        if ($inclusive) {
            return round($taxable - ($taxable / (1 + ($rate / 100))), 2);
        }

        return round($taxable * ($rate / 100), 2);
    }
}
