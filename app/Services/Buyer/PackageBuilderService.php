<?php

namespace App\Services\Buyer;

use App\Models\Lead;
use App\Models\Scheme;
use App\Models\User;
use App\Services\DistanceService;
use App\Services\LeadPricingService;
use App\Support\BuyerLeadPresenter;
use Illuminate\Support\Collection;

class PackageBuilderService
{
    /**
     * Default Mixed Zone Pack mix.
     *
     * @var array<string, int>
     */
    public const MIXED_ZONE_MIX = [
        'D1' => 3,
        'D2' => 2,
        'E1' => 3,
        'E2' => 2,
    ];

    public function __construct(
        private readonly LeadAvailabilityService $availabilityService = new LeadAvailabilityService,
        private readonly LeadPricingService $leadPricingService = new LeadPricingService,
        private readonly DistanceService $distanceService = new DistanceService,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    public function preview(User $buyer, array $criteria): array
    {
        if (! empty($criteria['lead_ids']) && is_array($criteria['lead_ids'])) {
            $leads = Lead::query()
                ->whereIn('id', $criteria['lead_ids'])
                ->with(['scheme', 'zone'])
                ->get()
                ->filter(fn (Lead $lead) => $this->availabilityService->isAvailable($lead))
                ->values();

            return $this->buildPreviewPayload($buyer, $leads, (int) ($criteria['lead_count'] ?? $leads->count()), $criteria);
        }

        $requested = max(1, (int) ($criteria['lead_count'] ?? 1));
        $filters = [
            'scheme_id' => $criteria['scheme_id'] ?? null,
            'zone_codes' => $criteria['zone_codes'] ?? [],
            'min_size' => $criteria['min_size'] ?? null,
            'max_size' => $criteria['max_size'] ?? null,
            'min_distance' => $criteria['min_distance'] ?? null,
            'max_distance' => $criteria['max_distance'] ?? null,
        ];

        $leads = $this->availabilityService
            ->findAvailableForBuyer($buyer, $filters)
            ->latest('id')
            ->limit($requested)
            ->get();

        return $this->buildPreviewPayload($buyer, $leads, $requested, $criteria);
    }

    /**
     * @return array<string, mixed>
     */
    public function mixedZoneDefault(User $buyer): array
    {
        $scheme = Scheme::query()->where('slug', 'insulation')->first();
        $selected = collect();
        $shortfalls = [];

        foreach (self::MIXED_ZONE_MIX as $zoneCode => $count) {
            $zoneLeads = $this->availabilityService
                ->findAvailableForBuyer($buyer, [
                    'scheme_id' => $scheme?->id,
                    'zone_codes' => [$zoneCode],
                ])
                ->latest('id')
                ->limit($count)
                ->get();

            $selected = $selected->merge($zoneLeads);
            if ($zoneLeads->count() < $count) {
                $shortfalls[$zoneCode] = $count - $zoneLeads->count();
            }
        }

        $requested = array_sum(self::MIXED_ZONE_MIX);
        $payload = $this->buildPreviewPayload($buyer, $selected->unique('id')->values(), $requested, [
            'scheme_id' => $scheme?->id,
            'zone_codes' => array_keys(self::MIXED_ZONE_MIX),
            'name' => 'Mixed Zone Pack',
        ]);

        $payload['mix'] = self::MIXED_ZONE_MIX;
        $payload['shortfalls'] = $shortfalls;
        $payload['package_type'] = 'mixed_zone';
        $payload['name'] = 'Mixed Zone Pack';

        return $payload;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function buildPreviewPayload(User $buyer, Collection $leads, int $requested, array $criteria): array
    {
        $total = 0.0;
        $size = 0.0;
        $zoneMix = [];

        foreach ($leads as $lead) {
            $pricing = $this->leadPricingService->calculate($lead);
            $lineTotal = $lead->selling_price !== null
                ? (float) $lead->selling_price
                : (float) ($pricing['selling_price'] ?? 0);
            $lineSize = $lead->size_m2 !== null ? (float) $lead->size_m2 : 0.0;
            $total += $lineTotal;
            $size += $lineSize;
            $code = $lead->zone?->code ?? 'unknown';
            $zoneMix[$code] = ($zoneMix[$code] ?? 0) + 1;
        }

        return [
            'enough' => $leads->count() >= $requested,
            'requested_count' => $requested,
            'matched_count' => $leads->count(),
            'lead_ids' => $leads->pluck('id')->values()->all(),
            'leads' => BuyerLeadPresenter::marketplaceCollection($leads, $buyer),
            'estimated_total' => round($total, 2),
            'total_size_m2' => round($size, 2),
            'avg_price_per_m2' => $size > 0 ? round($total / $size, 2) : null,
            'avg_size_m2' => $leads->count() > 0 ? round($size / $leads->count(), 2) : null,
            'zone_mix' => $zoneMix,
            'scheme_id' => $criteria['scheme_id'] ?? $leads->first()?->scheme_id,
            'criteria' => $criteria,
        ];
    }
}
