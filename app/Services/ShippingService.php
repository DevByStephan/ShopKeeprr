<?php

namespace App\Services;

use App\Models\ShippingMethod;
use App\Models\ShippingZone;

class ShippingService
{
    /**
     * @return array<int, array{id: int, name: string, cost: float, zone: string}>
     */
    public function options(?string $country, ?string $state, float $subtotal, float $weight): array
    {
        if (! $country) {
            return [];
        }

        $zones = ShippingZone::query()
            ->where('is_active', true)
            ->with(['methods' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        $options = [];

        foreach ($zones as $zone) {
            if (! $zone->covers($country, $state)) {
                continue;
            }

            foreach ($zone->methods as $method) {
                if (! $method->supportsWeight($weight)) {
                    continue;
                }

                $options[] = [
                    'id' => $method->id,
                    'name' => $method->name,
                    'cost' => $method->rateFor($subtotal, $weight),
                    'zone' => $zone->name,
                ];
            }
        }

        return $options;
    }

    public function find(int $methodId): ?ShippingMethod
    {
        return ShippingMethod::query()->where('is_active', true)->find($methodId);
    }

    public function fallbackCost(float $subtotal, float $weight): float
    {
        $threshold = (float) nexus_setting('shipping.free_threshold', 0);

        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }

        return 0.0;
    }

    public function rateFor(?int $methodId, ?string $country, ?string $state, float $subtotal, float $weight): array
    {
        $options = $this->options($country, $state, $subtotal, $weight);

        if ($methodId) {
            foreach ($options as $option) {
                if ((int) $option['id'] === $methodId) {
                    return $option;
                }
            }
        }

        if ($options) {
            return $options[0];
        }

        return ['id' => null, 'name' => 'Shipping', 'cost' => $this->fallbackCost($subtotal, $weight), 'zone' => ''];
    }
}
