<?php

namespace App\Services\Buyer;

use App\Enums\LeadStatus;
use App\Enums\PurchaseStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\DistanceService;
use App\Services\LeadPricingService;
use Illuminate\Database\Eloquent\Builder;

class LeadAvailabilityService
{
    /**
     * @var list<LeadStatus>
     */
    public const MARKETPLACE_STATUSES = [
        LeadStatus::Accepted,
        LeadStatus::Priced,
        LeadStatus::Listed,
    ];

    public function __construct(
        private readonly LeadPricingService $leadPricingService = new LeadPricingService,
        private readonly DistanceService $distanceService = new DistanceService,
    ) {}

    /**
     * @return list<string>
     */
    public static function marketplaceStatusValues(): array
    {
        return array_map(fn (LeadStatus $status) => $status->value, self::MARKETPLACE_STATUSES);
    }

    public function availableQuery(?User $buyer = null): Builder
    {
        $query = Lead::query()
            ->whereIn('status', self::marketplaceStatusValues())
            ->whereNotIn('status', [
                LeadStatus::Sold->value,
                LeadStatus::Cancelled->value,
                LeadStatus::Disputed->value,
            ])
            ->whereDoesntHave('purchaseItems', function (Builder $itemQuery) {
                $itemQuery->whereHas('purchase', function (Builder $purchaseQuery) {
                    $purchaseQuery->whereIn('status', [
                        PurchaseStatus::Pending->value,
                        PurchaseStatus::Paid->value,
                    ]);
                });
            });

        return $query;
    }

    public function isAvailable(Lead $lead): bool
    {
        if (! in_array($lead->status, self::MARKETPLACE_STATUSES, true)) {
            return false;
        }

        return $this->availableQuery()->whereKey($lead->id)->exists();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function findAvailableForBuyer(User $buyer, array $filters = []): Builder
    {
        $company = $buyer->buyerProfile?->company;
        $query = $this->availableQuery($buyer)
            ->with(['scheme:id,name,slug', 'zone:id,code,name,scheme_id']);

        if (! empty($filters['scheme_id'])) {
            $query->where('scheme_id', (int) $filters['scheme_id']);
        }

        if (! empty($filters['zone_id'])) {
            $query->where('zone_id', (int) $filters['zone_id']);
        }

        if (! empty($filters['zone_codes']) && is_array($filters['zone_codes'])) {
            $codes = array_values(array_filter($filters['zone_codes']));
            if ($codes !== []) {
                $query->whereHas('zone', fn (Builder $q) => $q->whereIn('code', $codes));
            }
        }

        if (isset($filters['min_size']) && $filters['min_size'] !== '' && $filters['min_size'] !== null) {
            $query->where('size_m2', '>=', (float) $filters['min_size']);
        }

        if (isset($filters['max_size']) && $filters['max_size'] !== '' && $filters['max_size'] !== null) {
            $query->where('size_m2', '<=', (float) $filters['max_size']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where('lead_reference', 'like', '%'.$search.'%');
        }

        $minDistance = $filters['min_distance'] ?? null;
        $maxDistance = $filters['max_distance'] ?? null;
        $minPrice = $filters['min_price'] ?? null;
        $maxPrice = $filters['max_price'] ?? null;

        if (
            ($minDistance !== null && $minDistance !== '')
            || ($maxDistance !== null && $maxDistance !== '')
            || ($minPrice !== null && $minPrice !== '')
            || ($maxPrice !== null && $maxPrice !== '')
        ) {
            $leadIds = (clone $query)->pluck('id');
            $matchedIds = Lead::query()
                ->whereIn('id', $leadIds)
                ->with(['scheme', 'zone'])
                ->get()
                ->filter(function (Lead $lead) use ($company, $minDistance, $maxDistance, $minPrice, $maxPrice) {
                    $distance = $this->distanceService->forLead($lead, $company);
                    $pricing = $this->leadPricingService->calculate($lead);
                    $price = $lead->selling_price !== null
                        ? (float) $lead->selling_price
                        : ($pricing['selling_price'] ?? null);

                    if ($minDistance !== null && $minDistance !== '' && ($distance === null || $distance < (float) $minDistance)) {
                        return false;
                    }

                    if ($maxDistance !== null && $maxDistance !== '' && ($distance === null || $distance > (float) $maxDistance)) {
                        return false;
                    }

                    if ($minPrice !== null && $minPrice !== '' && ($price === null || $price < (float) $minPrice)) {
                        return false;
                    }

                    if ($maxPrice !== null && $maxPrice !== '' && ($price === null || $price > (float) $maxPrice)) {
                        return false;
                    }

                    return true;
                })
                ->pluck('id')
                ->all();

            $query->whereIn('id', $matchedIds === [] ? [0] : $matchedIds);
        }

        return $query;
    }
}
