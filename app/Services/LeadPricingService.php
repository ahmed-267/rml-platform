<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\PricingRule;
use App\Models\Zone;

class LeadPricingService
{
    /**
     * Calculate selling price using zone price × size/m².
     *
     * @return array{
     *     selling_price: float|null,
     *     price_per_m2: float|null,
     *     size_m2: float|null,
     *     zone_code: string|null,
     *     formula: string,
     *     breakdown: array<string, float|string|null>
     * }
     */
    public function calculate(Lead $lead, ?PricingRule $rule = null): array
    {
        $size = $lead->size_m2 !== null ? (float) $lead->size_m2 : null;
        $zone = $lead->zone ?? ($lead->zone_id ? Zone::query()->find($lead->zone_id) : null);

        $rule ??= $this->resolveRule($lead->scheme_id, $lead->zone_id);

        if ($rule === null || $size === null || $size <= 0) {
            return [
                'selling_price' => null,
                'price_per_m2' => $rule ? (float) $rule->price_per_m2 : null,
                'size_m2' => $size,
                'zone_code' => $zone?->code,
                'formula' => 'size_m2 × price_per_m2',
                'breakdown' => [
                    'error' => 'Missing zone pricing rule or size_m2',
                ],
            ];
        }

        $pricePerM2 = (float) $rule->price_per_m2;
        $sellingPrice = round($size * $pricePerM2, 2);

        return [
            'selling_price' => $sellingPrice,
            'price_per_m2' => $pricePerM2,
            'size_m2' => $size,
            'zone_code' => $zone?->code,
            'formula' => 'size_m2 × price_per_m2',
            'breakdown' => [
                'size_m2' => $size,
                'price_per_m2' => $pricePerM2,
                'basic_price' => $rule->basic_price !== null ? (float) $rule->basic_price : null,
                'zone_factor' => $rule->zone_factor !== null ? (float) $rule->zone_factor : null,
                'size_factor' => $rule->size_factor !== null ? (float) $rule->size_factor : null,
                'distance_factor' => $rule->distance_factor !== null ? (float) $rule->distance_factor : null,
                'selling_price' => $sellingPrice,
            ],
        ];
    }

    public function resolveRule(?int $schemeId, ?int $zoneId): ?PricingRule
    {
        if (! $schemeId) {
            return null;
        }

        $query = PricingRule::query()
            ->where('scheme_id', $schemeId)
            ->where('active', true)
            ->orderByDesc('id');

        if ($zoneId) {
            $zoneRule = (clone $query)->where('zone_id', $zoneId)->first();
            if ($zoneRule) {
                return $zoneRule;
            }
        }

        return $query->whereNull('zone_id')->first();
    }
}
