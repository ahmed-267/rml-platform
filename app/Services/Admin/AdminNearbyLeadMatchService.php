<?php

namespace App\Services\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\PackageStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Buyer\LeadAvailabilityService;
use App\Services\DistanceService;
use App\Services\LeadPricingService;
use App\Support\CaseInsensitiveSearch;
use App\Support\LeadStatusPresentation;
use App\Support\ZoneDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Straight-line nearby matching between approved installers and eligible registered leads.
 */
class AdminNearbyLeadMatchService
{
    public const RADIUS_OPTIONS_KM = [10, 25, 50, 100, 200];

    /**
     * Package statuses that reserve leads for an active deal (exclude from matching).
     *
     * @var list<string>
     */
    public const RESERVING_PACKAGE_STATUSES = [
        PackageStatus::Draft->value,
        PackageStatus::Available->value,
        PackageStatus::Locked->value,
        PackageStatus::Sold->value,
    ];

    public function __construct(
        private readonly DistanceService $distanceService,
        private readonly LeadAvailabilityService $leadAvailability,
        private readonly LeadPricingService $leadPricing = new LeadPricingService,
    ) {}

    public function defaultRadiusKm(): float
    {
        $fromSettings = (int) round(\App\Support\PackageSettings::defaultSearchRadiusKm());
        if (in_array($fromSettings, self::RADIUS_OPTIONS_KM, true)) {
            return (float) $fromSettings;
        }

        $configured = (float) config('services.google_maps.default_radius_km', 50);
        if (in_array((int) $configured, self::RADIUS_OPTIONS_KM, true)) {
            return $configured;
        }

        return 50.0;
    }

    public function resolveRadiusKm(Request $request): float
    {
        if (! $request->filled('radius_km')) {
            return $this->defaultRadiusKm();
        }

        $radius = (int) $request->input('radius_km');

        return in_array($radius, self::RADIUS_OPTIONS_KM, true)
            ? (float) $radius
            : $this->defaultRadiusKm();
    }

    public function findInstaller(int $companyId): ?Company
    {
        return Company::query()
            ->whereKey($companyId)
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with([
                'buyerProfiles' => fn ($q) => $q->with('user:id,name')->limit(5),
            ])
            ->first();
    }

    /**
     * Eligible leads for packaging/sale (marketplace + not reserved).
     */
    public function eligibleLeadQuery(Request $request): Builder
    {
        $query = $this->leadAvailability->availableQuery()
            ->with([
                'scheme:id,name',
                'zone:id,code,name',
                'sellerCompany:id,name',
                'submittedBy:id,name',
            ])
            ->whereNotNull('leads.latitude')
            ->whereNotNull('leads.longitude')
            // Prepare for package reservations: exclude locked/sold package members.
            ->whereDoesntHave('packages', function (Builder $packageQuery) {
                $packageQuery->whereIn('lead_packages.status', self::RESERVING_PACKAGE_STATUSES);
            });

        $this->applyLeadFilters($query, $request);

        return $query;
    }

    /**
     * @return array{
     *     active: bool,
     *     installer: ?array<string, mixed>,
     *     radius_km: float,
     *     leads: list<array<string, mixed>>,
     *     summary: array{matched_count: int, average_distance_km: float|null, total_size_m2: float},
     *     lead_ids: list<int>
     * }
     */
    public function match(Request $request): array
    {
        $radiusKm = $this->resolveRadiusKm($request);
        $installerId = $request->integer('match_installer_id');

        $empty = [
            'active' => false,
            'installer' => null,
            'radius_km' => $radiusKm,
            'leads' => [],
            'summary' => [
                'matched_count' => 0,
                'average_distance_km' => null,
                'total_size_m2' => 0.0,
            ],
            'lead_ids' => [],
        ];

        if ($installerId <= 0) {
            return $empty;
        }

        $installer = $this->findInstaller($installerId);
        if (! $installer) {
            return [
                ...$empty,
                'active' => true,
            ];
        }

        $installerLat = (float) $installer->latitude;
        $installerLng = (float) $installer->longitude;

        $leads = $this->eligibleLeadQuery($request)
            ->limit(500)
            ->get()
            ->map(function (Lead $lead) use ($installerLat, $installerLng, $radiusKm) {
                $distance = $this->distanceService->betweenKm(
                    $installerLat,
                    $installerLng,
                    (float) $lead->latitude,
                    (float) $lead->longitude,
                );

                if ($distance > $radiusKm) {
                    return null;
                }

                return [
                    'lead' => $lead,
                    'distance_km' => $distance,
                ];
            })
            ->filter()
            ->sortBy('distance_km')
            ->values();

        $presented = $leads->map(fn (array $row) => $this->presentMatchedLead($row['lead'], $row['distance_km']))->all();
        $distances = $leads->pluck('distance_km');
        $totalSize = (float) $leads->sum(fn (array $row) => (float) ($row['lead']->size_m2 ?? 0));

        return [
            'active' => true,
            'installer' => $this->presentInstaller($installer),
            'radius_km' => $radiusKm,
            'leads' => $presented,
            'summary' => [
                'matched_count' => $leads->count(),
                'average_distance_km' => $distances->isEmpty()
                    ? null
                    : round((float) $distances->avg(), 1),
                'total_size_m2' => round($totalSize, 2),
            ],
            'lead_ids' => $leads->map(fn (array $row) => (int) $row['lead']->id)->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function installerOptions(float $radiusKm, ?Collection $eligibleLeads = null): array
    {
        $eligibleLeads ??= $this->eligibleLeadQuery(request())->limit(500)->get();

        return Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get()
            ->map(function (Company $company) use ($eligibleLeads, $radiusKm) {
                $matched = 0;
                $lat = (float) $company->latitude;
                $lng = (float) $company->longitude;

                foreach ($eligibleLeads as $lead) {
                    $distance = $this->distanceService->betweenKm(
                        $lat,
                        $lng,
                        (float) $lead->latitude,
                        (float) $lead->longitude,
                    );
                    if ($distance <= $radiusKm) {
                        $matched++;
                    }
                }

                $baseLocation = $company->formatted_address
                    ?: trim(implode(', ', array_filter([
                        $company->city,
                        $company->postcode,
                    ])));

                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'city' => $company->city,
                    'base_location' => $baseLocation !== '' ? $baseLocation : null,
                    'matched_count' => $matched,
                    'latitude' => $lat,
                    'longitude' => $lng,
                ];
            })
            ->values()
            ->all();
    }

    private function applyLeadFilters(Builder $query, Request $request): void
    {
        if ($request->filled('status')) {
            $expanded = array_values(array_intersect(
                LeadStatusPresentation::expand($request->string('status')->toString()),
                LeadAvailabilityService::marketplaceStatusValues(),
            ));
            if ($expanded !== []) {
                $query->whereIn('leads.status', $expanded);
            } else {
                // Status outside eligible set → no matches.
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('scheme_id')) {
            $query->where('leads.scheme_id', $request->integer('scheme_id'));
        }

        if ($request->filled('zone_code')) {
            $zoneCode = $request->string('zone_code')->toString();
            $query->whereHas('zone', fn ($zq) => $zq->where('code', $zoneCode));
        } elseif ($request->filled('zone_id')) {
            $query->where('leads.zone_id', $request->integer('zone_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $like = CaseInsensitiveSearch::operator();
            $query->where(function ($q) use ($search, $like) {
                $q->where('leads.lead_reference', $like, $search)
                    ->orWhere('leads.customer_first_name', $like, $search)
                    ->orWhere('leads.customer_last_name', $like, $search)
                    ->orWhereHas('submittedBy', fn ($uq) => $uq->where('name', $like, $search))
                    ->orWhereHas('sellerCompany', fn ($cq) => $cq->where('name', $like, $search));
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMatchedLead(Lead $lead, float $distanceKm): array
    {
        $pricing = $this->leadPricing->calculate($lead);
        $selling = $lead->selling_price !== null
            ? (float) $lead->selling_price
            : (float) ($pricing['selling_price'] ?? 0);
        $payout = $lead->buying_price !== null
            ? (float) $lead->buying_price
            : ($this->leadPricing->suggestedSellerPayout($lead) ?? 0.0);

        return [
            'id' => $lead->id,
            'type' => 'lead',
            'lead_reference' => $lead->lead_reference,
            'status' => LeadStatusPresentation::visibleKey($lead->status?->value) ?? $lead->status?->value,
            'scheme' => $lead->scheme?->name,
            'zone' => ZoneDisplay::code($lead->zone?->code),
            'size_m2' => $lead->size_m2 !== null ? (float) $lead->size_m2 : null,
            'seller_company' => $lead->sellerCompany?->name ?? $lead->submittedBy?->name,
            'city' => $lead->city,
            'approximate_location' => $lead->formatted_address
                ?? collect([$lead->city, $lead->postcode])->filter()->implode(', ')
                ?: null,
            'selling_price' => round($selling, 2),
            'buying_price' => round($payout, 2),
            'expected_margin' => round($selling - $payout, 2),
            'distance_km' => $distanceKm,
            'latitude' => (float) $lead->latitude,
            'longitude' => (float) $lead->longitude,
            'view_url' => route('admin.leads-bought.show', $lead),
            'geocode_url' => route('admin.leads.geocode', $lead),
            'location_url' => route('admin.leads-bought.show', $lead),
            'matched' => true,
            'packagable' => true,
            'packagable_reason' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentInstaller(Company $company): array
    {
        $profile = $company->buyerProfiles->first();
        $buyerUser = $profile?->user;
        $baseLocation = $company->formatted_address
            ?: trim(implode(', ', array_filter([
                $company->address,
                $company->postcode,
                $company->city,
                $company->country,
            ])));

        return [
            'id' => $company->id,
            'company_name' => $company->name,
            'city' => $company->city,
            'base_location' => $baseLocation !== '' ? $baseLocation : null,
            'latitude' => (float) $company->latitude,
            'longitude' => (float) $company->longitude,
            'view_url' => $buyerUser ? route('admin.buyers.show', $buyerUser) : null,
        ];
    }
}
