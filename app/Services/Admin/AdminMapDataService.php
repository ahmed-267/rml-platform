<?php

namespace App\Services\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Services\DistanceService;
use App\Support\CaseInsensitiveSearch;
use App\Support\LeadStatusPresentation;
use App\Support\Permissions;
use App\Support\ZoneDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminMapDataService
{
    private const MARKER_LIMIT = 500;

    public function __construct(
        private readonly DistanceService $distanceService,
        private readonly AdminNearbyLeadMatchService $nearbyMatch,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forLeadsPage(Request $request, string $tab): array
    {
        $user = $request->user();
        $canSeeInstallers = $user?->hasRole('super_admin')
            || $user?->can(Permissions::MANAGE_BUYERS);

        $markerSet = $request->input('marker_set', 'both');
        if (! in_array($markerSet, ['leads', 'installers', 'both'], true)) {
            $markerSet = 'both';
        }

        $radiusKm = $this->nearbyMatch->resolveRadiusKm($request);
        $nearby = $tab === 'registered' && $canSeeInstallers
            ? $this->nearbyMatch->match($request)
            : [
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

        $matchActive = (bool) ($nearby['active'] ?? false);
        $matchedLeadIds = collect($nearby['lead_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $distanceByLeadId = collect($nearby['leads'] ?? [])
            ->mapWithKeys(fn (array $row) => [(int) $row['id'] => (float) $row['distance_km']]);

        $leadQuery = $this->baseLeadQuery($request, $tab);
        $leadHidden = (clone $leadQuery)
            ->where(function ($q) {
                $q->whereNull('leads.latitude')->orWhereNull('leads.longitude');
            })
            ->count();

        if ($matchActive) {
            $leadMarkers = $nearby['leads'];
            $leads = $matchedLeadIds === []
                ? collect()
                : Lead::query()
                    ->whereIn('id', $matchedLeadIds)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->get()
                    ->sortBy(fn (Lead $lead) => $distanceByLeadId->get((int) $lead->id, PHP_FLOAT_MAX))
                    ->values();
        } else {
            $leads = (clone $leadQuery)
                ->whereNotNull('leads.latitude')
                ->whereNotNull('leads.longitude')
                ->orderByDesc('leads.created_at')
                ->limit(self::MARKER_LIMIT)
                ->get();

            $leadMarkers = $leads
                ->map(fn (Lead $lead) => $this->presentLeadMarker($lead, $tab))
                ->values()
                ->all();
        }

        $installerHidden = 0;
        $installerMarkers = [];
        $installerOptions = [];
        $selectedInstallerId = $matchActive
            ? (int) ($nearby['installer']['id'] ?? $request->integer('match_installer_id'))
            : 0;

        // Always load approved installer pins when the admin can see them.
        // Lead-only filters (status/scheme/zone/search on registered) must not
        // remove installer markers — especially in marker_set=both.
        if ($canSeeInstallers) {
            $installerQuery = $this->baseInstallerQuery($request, $tab, $markerSet, $selectedInstallerId);
            $installerHidden = (clone $installerQuery)
                ->where(function ($q) {
                    $q->whereNull('companies.latitude')->orWhereNull('companies.longitude');
                })
                ->count();

            $installers = (clone $installerQuery)
                ->whereNotNull('companies.latitude')
                ->whereNotNull('companies.longitude')
                ->orderBy('companies.name')
                ->limit(self::MARKER_LIMIT)
                ->get();

            // Nearby counts use currently visible lead pins only (lead filters apply
            // to that count), but the installer pin list itself stays complete.
            $installersForNearbyCount = $leads instanceof Collection ? $leads : collect($leads);

            $installerMarkers = $installers
                ->map(function (Company $company) use ($installersForNearbyCount, $selectedInstallerId, $radiusKm, $matchActive) {
                    $marker = $this->presentInstallerMarker(
                        $company,
                        $installersForNearbyCount,
                        $matchActive ? $radiusKm : null,
                    );
                    $marker['selected'] = $selectedInstallerId > 0
                        && (int) $company->id === $selectedInstallerId;

                    return $marker;
                })
                ->values()
                ->all();

            if ($tab === 'registered') {
                $installerOptions = $this->nearbyMatch->installerOptions($radiusKm);
            } else {
                $installerOptions = Company::query()
                    ->where('type', CompanyType::Buyer->value)
                    ->where('approval_status', ApprovalStatus::Approved->value)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Company $company) => [
                        'id' => $company->id,
                        'name' => $company->name,
                    ])
                    ->values()
                    ->all();
            }
        }

        return [
            'view' => 'map',
            'tab' => $tab,
            'center' => [
                'lat' => (float) config('services.google_maps.default_center_lat', 40.4168),
                'lng' => (float) config('services.google_maps.default_center_lng', -3.7038),
            ],
            'default_radius_km' => $this->nearbyMatch->defaultRadiusKm(),
            'radius_options_km' => AdminNearbyLeadMatchService::RADIUS_OPTIONS_KM,
            'can_see_installers' => $canSeeInstallers,
            'show_installer_filter' => $canSeeInstallers && $tab === 'sold',
            'show_nearby_match' => $canSeeInstallers && $tab === 'registered',
            'can_manage_packages' => $tab === 'registered' && (
                $user?->hasRole('super_admin')
                || $user?->can(Permissions::MANAGE_PACKAGES)
            ),
            'nearby' => $nearby,
            'leads' => $leadMarkers,
            'installers' => $installerMarkers,
            'hidden' => [
                'leads' => $leadHidden,
                'installers' => $installerHidden,
            ],
            'installer_options' => $installerOptions,
            'status_options' => LeadStatusPresentation::visibleValuesForMapTab($tab),
            'payment_status_options' => $tab === 'sold' ? PaymentStatus::values() : [],
            'release_status_options' => $tab === 'sold'
                ? ['released', 'pending_release']
                : [],
            'filters' => [
                'status' => $tab === 'registered' ? $request->input('status') : null,
                'scheme_id' => $request->input('scheme_id'),
                'zone_code' => $tab === 'registered' ? $request->input('zone_code') : null,
                'search' => $request->input('search'),
                'installer_id' => $tab === 'sold' ? $request->input('installer_id') : null,
                'match_installer_id' => $tab === 'registered' ? $request->input('match_installer_id') : null,
                'radius_km' => $tab === 'registered' ? $radiusKm : null,
                'payment_status' => $tab === 'sold' ? $request->input('payment_status') : null,
                'release_status' => $tab === 'sold' ? $request->input('release_status') : null,
                'sold_from' => $tab === 'sold' ? $request->input('sold_from') : null,
                'sold_to' => $tab === 'sold' ? $request->input('sold_to') : null,
                'marker_set' => $markerSet,
            ],
        ];
    }

    private function baseLeadQuery(Request $request, string $tab)
    {
        $query = Lead::query()
            ->with([
                'scheme:id,name',
                'zone:id,code,name',
                'sellerCompany:id,name',
                'submittedBy:id,name',
                'purchaseItems.purchase.buyerCompany:id,name',
                'purchaseItems.purchase.buyerUser:id,name',
                'purchaseItems.purchase.payment:id,status',
            ]);

        if ($tab === 'sold') {
            $query->where('leads.status', LeadStatus::Sold->value);
            $this->applySoldFilters($query, $request);
        } else {
            $query->whereNotIn('leads.status', [
                LeadStatus::Draft->value,
                LeadStatus::Sold->value,
            ]);
            $this->applyRegisteredFilters($query, $request);
        }

        return $query;
    }

    private function applyRegisteredFilters($query, Request $request): void
    {
        if ($request->filled('status')) {
            $expanded = array_values(array_diff(
                LeadStatusPresentation::expand($request->string('status')->toString()),
                [LeadStatus::Sold->value, LeadStatus::Draft->value],
            ));
            if ($expanded !== []) {
                $query->whereIn('leads.status', $expanded);
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

    private function applySoldFilters($query, Request $request): void
    {
        if ($request->filled('scheme_id')) {
            $query->where('leads.scheme_id', $request->integer('scheme_id'));
        }

        if ($request->filled('installer_id')) {
            $installerId = $request->integer('installer_id');
            $query->whereHas(
                'purchaseItems.purchase',
                fn ($pq) => $pq->where('buyer_company_id', $installerId),
            );
        }

        if ($request->filled('payment_status')) {
            $paymentStatus = $request->string('payment_status')->toString();
            $query->whereHas(
                'purchaseItems.purchase.payment',
                fn ($pq) => $pq->where('status', $paymentStatus),
            );
        }

        if ($request->filled('release_status')) {
            $release = $request->string('release_status')->toString();
            if ($release === 'released') {
                $query->whereHas(
                    'purchaseItems.purchase.payment',
                    fn ($pq) => $pq->where('status', PaymentStatus::Paid->value),
                );
            } elseif ($release === 'pending_release') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('purchaseItems.purchase.payment')
                        ->orWhereHas(
                            'purchaseItems.purchase.payment',
                            fn ($pq) => $pq->where('status', '!=', PaymentStatus::Paid->value),
                        );
                });
            }
        }

        if ($request->filled('sold_from')) {
            $query->whereDate('leads.sold_at', '>=', $request->date('sold_from'));
        }

        if ($request->filled('sold_to')) {
            $query->whereDate('leads.sold_at', '<=', $request->date('sold_to'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $like = CaseInsensitiveSearch::operator();
            $query->where(function ($q) use ($search, $like) {
                $q->where('leads.lead_reference', $like, $search)
                    ->orWhereHas('submittedBy', fn ($uq) => $uq->where('name', $like, $search))
                    ->orWhereHas('sellerCompany', fn ($cq) => $cq->where('name', $like, $search))
                    ->orWhereHas(
                        'purchaseItems.purchase.buyerCompany',
                        fn ($bq) => $bq->where('name', $like, $search),
                    );
            });
        }
    }

    private function baseInstallerQuery(
        Request $request,
        string $tab,
        string $markerSet,
        int $selectedMatchInstallerId = 0,
    ) {
        $query = Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->with([
                'buyerProfiles' => fn ($q) => $q->with('user:id,name')->limit(5),
            ]);

        // Buyer/installer filter only applies on sold tab.
        if ($tab === 'sold' && $request->filled('installer_id')) {
            $query->where('companies.id', $request->integer('installer_id'));
        }

        // Nearby match: keep selected installer pin (and still show others for context).
        if ($tab === 'registered' && $selectedMatchInstallerId > 0) {
            // No hard filter — selected flag handles highlight; all approved installers remain.
        }

        // Name search only when browsing installers alone.
        if ($markerSet === 'installers' && $request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $like = CaseInsensitiveSearch::operator();
            $query->where(function ($q) use ($search, $like) {
                $q->where('companies.name', $like, $search)
                    ->orWhere('companies.city', $like, $search)
                    ->orWhere('companies.address', $like, $search)
                    ->orWhere('companies.formatted_address', $like, $search);
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLeadMarker(Lead $lead, string $tab): array
    {
        $purchase = $lead->purchaseItems->first()?->purchase;
        $paymentStatus = $purchase?->payment?->status?->value;
        $releaseStatus = $paymentStatus === PaymentStatus::Paid->value
            ? 'released'
            : ($tab === 'sold' ? 'pending_release' : null);

        $marker = [
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
            'buyer_company' => $purchase?->buyerCompany?->name,
            'buyer_name' => $purchase?->buyerUser?->name,
            'payment_status' => $paymentStatus,
            'release_status' => $releaseStatus,
            'sold_at' => $lead->sold_at?->toDateString(),
            'latitude' => (float) $lead->latitude,
            'longitude' => (float) $lead->longitude,
            'view_url' => $tab === 'sold'
                ? route('admin.leads-sold.show', $lead)
                : route('admin.leads-bought.show', $lead),
            'geocode_url' => route('admin.leads.geocode', $lead),
            'location_url' => $tab === 'sold'
                ? route('admin.leads-sold.show', $lead)
                : route('admin.leads-bought.show', $lead),
        ];

        if ($tab === 'registered') {
            $marketplace = \App\Services\Buyer\LeadAvailabilityService::marketplaceStatusValues();
            $inActivePackage = $lead->packages()
                ->whereIn('lead_packages.status', AdminNearbyLeadMatchService::RESERVING_PACKAGE_STATUSES)
                ->exists();
            $statusValue = $lead->status?->value;
            $packagable = in_array($statusValue, $marketplace, true) && ! $inActivePackage;
            $marker['packagable'] = $packagable;
            $marker['packagable_reason'] = match (true) {
                $packagable => null,
                $inActivePackage => 'in_package',
                $statusValue === LeadStatus::Sold->value => 'sold',
                $statusValue === LeadStatus::Rejected->value => 'rejected',
                $statusValue === LeadStatus::Cancelled->value => 'cancelled',
                $statusValue === LeadStatus::NeedsMoreInformation->value => 'needs_information',
                in_array($statusValue, [
                    LeadStatus::Submitted->value,
                    LeadStatus::PendingEvidence->value,
                    LeadStatus::PendingValidation->value,
                    LeadStatus::Validating->value,
                ], true) => 'pending_review',
                default => 'not_listed',
            };
            if ($packagable) {
                $pricing = app(\App\Services\LeadPricingService::class)->calculate($lead);
                $selling = $lead->selling_price !== null
                    ? (float) $lead->selling_price
                    : (float) ($pricing['selling_price'] ?? 0);
                $payout = $lead->buying_price !== null
                    ? (float) $lead->buying_price
                    : (app(\App\Services\LeadPricingService::class)->suggestedSellerPayout($lead) ?? 0.0);
                $marker['selling_price'] = round($selling, 2);
                $marker['buying_price'] = round($payout, 2);
                $marker['expected_margin'] = round($selling - $payout, 2);
            }
        }

        return $marker;
    }

    /**
     * @param  Collection<int, Lead>  $filteredLeadsWithCoords
     * @return array<string, mixed>
     */
    private function presentInstallerMarker(
        Company $company,
        Collection $filteredLeadsWithCoords,
        ?float $radiusOverrideKm = null,
    ): array {
        $profile = $company->buyerProfiles->first();
        $buyerUser = $profile?->user;
        $radius = $radiusOverrideKm
            ?? ($profile?->max_distance_km !== null
                ? (float) $profile->max_distance_km
                : (float) config('services.google_maps.default_radius_km', 50));

        $nearby = 0;
        $lat = (float) $company->latitude;
        $lng = (float) $company->longitude;

        foreach ($filteredLeadsWithCoords as $lead) {
            if ($lead->latitude === null || $lead->longitude === null) {
                continue;
            }
            $distance = $this->distanceService->betweenKm(
                $lat,
                $lng,
                (float) $lead->latitude,
                (float) $lead->longitude,
            );
            if ($distance <= $radius) {
                $nearby++;
            }
        }

        $baseLocation = $company->formatted_address
            ?: trim(implode(', ', array_filter([
                $company->address,
                $company->postcode,
                $company->city,
                $company->country,
            ])));

        return [
            'id' => $company->id,
            'type' => 'installer',
            'company_name' => $company->name,
            'base_location' => $baseLocation !== '' ? $baseLocation : null,
            'status' => $company->approval_status?->value,
            'nearby_leads_count' => $nearby,
            'nearby_radius_km' => $radius,
            'selected' => false,
            'latitude' => $lat,
            'longitude' => $lng,
            'buyer_user_id' => $buyerUser?->id,
            'view_url' => $buyerUser
                ? route('admin.buyers.show', $buyerUser)
                : null,
            'geocode_url' => $buyerUser
                ? route('admin.buyers.geocode-company', $buyerUser)
                : null,
            'location_url' => $buyerUser
                ? route('admin.buyers.company-location', $buyerUser)
                : null,
        ];
    }
}
