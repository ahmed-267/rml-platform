<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadPackage;
use Illuminate\Support\Collection;

class PackagePricingService
{
    public function __construct(
        private readonly LeadPricingService $leadPricingService = new LeadPricingService,
    ) {}

    /**
     * @return array{
     *     estimated_total: float,
     *     avg_price_per_m2: float|null,
     *     total_size_m2: float,
     *     lead_count: int,
     *     leads: list<array<string, mixed>>
     * }
     */
    public function calculate(LeadPackage $package, ?Collection $leads = null): array
    {
        $leads ??= $package->leads()->get();

        $leadBreakdown = [];
        $total = 0.0;
        $totalSize = 0.0;

        /** @var Lead $lead */
        foreach ($leads as $lead) {
            $price = $lead->selling_price !== null
                ? (float) $lead->selling_price
                : ($this->leadPricingService->calculate($lead)['selling_price'] ?? 0.0);

            $size = $lead->size_m2 !== null ? (float) $lead->size_m2 : 0.0;
            $total += (float) $price;
            $totalSize += $size;

            $leadBreakdown[] = [
                'lead_id' => $lead->id,
                'lead_reference' => $lead->lead_reference,
                'selling_price' => (float) $price,
                'size_m2' => $size,
            ];
        }

        $avg = $totalSize > 0 ? round($total / $totalSize, 2) : null;

        return [
            'estimated_total' => round($total, 2),
            'avg_price_per_m2' => $avg,
            'total_size_m2' => round($totalSize, 2),
            'lead_count' => $leads->count(),
            'leads' => $leadBreakdown,
            'zone_mix' => $package->zone_mix,
        ];
    }
}
