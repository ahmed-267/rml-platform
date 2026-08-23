<?php

namespace App\Services\Buyer;

use App\Enums\LeadStatus;
use App\Enums\PurchaseStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\DistanceService;
use App\Services\LeadPricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

        if (config('rml.leads.require_onsite_survey')) {
            $query->where('survey_eligibility_status', 'survey_approved');
        } else {
            // Once a survey workflow has started, only approved surveys remain eligible.
            $query->where(function (Builder $surveyQuery) {
                $surveyQuery->whereNull('survey_eligibility_status')
                    ->orWhere('survey_eligibility_status', 'survey_approved');
            });
        }

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
     * Batch-check availability for many lead IDs (one query).
     *
     * @param  iterable<int|string>  $leadIds
     * @return array<int, true> keyed by lead id
     */
    public function availableIdSet(iterable $leadIds): array
    {
        $ids = collect($leadIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return $this->availableQuery()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
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
            $query->where('lead_reference', 'ilike', '%'.$search.'%');
        }

        $minDistance = $filters['min_distance'] ?? null;
        $maxDistance = $filters['max_distance'] ?? null;
        $minPrice = $filters['min_price'] ?? null;
        $maxPrice = $filters['max_price'] ?? null;

        $hasDistanceFilter = ($minDistance !== null && $minDistance !== '')
            || ($maxDistance !== null && $maxDistance !== '');
        $hasPriceFilter = ($minPrice !== null && $minPrice !== '')
            || ($maxPrice !== null && $maxPrice !== '');

        if ($hasDistanceFilter) {
            $query->where(function (Builder $distanceQuery) use ($minDistance, $maxDistance) {
                $distanceQuery->where(function (Builder $stored) use ($minDistance, $maxDistance) {
                    $stored->whereNotNull('distance_km');
                    if ($minDistance !== null && $minDistance !== '') {
                        $stored->where('distance_km', '>=', (float) $minDistance);
                    }
                    if ($maxDistance !== null && $maxDistance !== '') {
                        $stored->where('distance_km', '<=', (float) $maxDistance);
                    }
                })->orWhere(function (Builder $computed) {
                    $computed->whereNull('distance_km')
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude');
                });
            });
        }

        if ($hasPriceFilter) {
            $query->where(function (Builder $priceQuery) use ($minPrice, $maxPrice) {
                $priceQuery->where(function (Builder $stored) use ($minPrice, $maxPrice) {
                    $stored->whereNotNull('selling_price');
                    if ($minPrice !== null && $minPrice !== '') {
                        $stored->where('selling_price', '>=', (float) $minPrice);
                    }
                    if ($maxPrice !== null && $maxPrice !== '') {
                        $stored->where('selling_price', '<=', (float) $maxPrice);
                    }
                })->orWhereNull('selling_price');
            });
        }

        if ($hasDistanceFilter || $hasPriceFilter) {
            $this->leadPricingService->warmRulesCache();
            $matchedIds = [];

            (clone $query)
                ->select([
                    'id',
                    'scheme_id',
                    'zone_id',
                    'size_m2',
                    'selling_price',
                    'distance_km',
                    'latitude',
                    'longitude',
                ])
                ->orderBy('id')
                ->chunkById(250, function (Collection $leads) use (
                    &$matchedIds,
                    $company,
                    $minDistance,
                    $maxDistance,
                    $minPrice,
                    $maxPrice,
                    $hasDistanceFilter,
                    $hasPriceFilter,
                ) {
                    foreach ($leads as $lead) {
                        if ($hasDistanceFilter) {
                            $distance = $this->distanceService->forLead($lead, $company);
                            if ($minDistance !== null && $minDistance !== '' && ($distance === null || $distance < (float) $minDistance)) {
                                continue;
                            }
                            if ($maxDistance !== null && $maxDistance !== '' && ($distance === null || $distance > (float) $maxDistance)) {
                                continue;
                            }
                        }

                        if ($hasPriceFilter) {
                            $pricing = $this->leadPricingService->calculate($lead);
                            $price = $lead->selling_price !== null
                                ? (float) $lead->selling_price
                                : ($pricing['selling_price'] ?? null);

                            if ($minPrice !== null && $minPrice !== '' && ($price === null || $price < (float) $minPrice)) {
                                continue;
                            }
                            if ($maxPrice !== null && $maxPrice !== '' && ($price === null || $price > (float) $maxPrice)) {
                                continue;
                            }
                        }

                        $matchedIds[] = (int) $lead->id;
                    }
                });

            $query->whereIn('id', $matchedIds === [] ? [0] : $matchedIds);
        }

        return $query;
    }
}
