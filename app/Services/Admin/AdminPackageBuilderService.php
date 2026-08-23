<?php

namespace App\Services\Admin;

use App\Enums\PackageStatus;
use App\Enums\PackageType;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadPackage;
use App\Models\User;
use App\Services\Buyer\LeadAvailabilityService;
use App\Services\DistanceService;
use App\Services\LeadPricingService;
use App\Services\AuditLogService;
use App\Support\PackageSettings;
use App\Support\ReferenceGenerator;
use App\Support\ZoneDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPackageBuilderService
{
    public function __construct(
        private readonly LeadAvailabilityService $leadAvailability = new LeadAvailabilityService,
        private readonly LeadPricingService $leadPricing = new LeadPricingService,
        private readonly DistanceService $distanceService = new DistanceService,
        private readonly AuditLogService $auditLogService = new AuditLogService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function eligibleLeadsForPicker(int $limit = 300): array
    {
        return $this->eligibleQuery()
            ->with(['sellerCompany:id,name', 'submittedBy:id,name'])
            ->latest('leads.id')
            ->limit($limit)
            ->get()
            ->map(function (Lead $lead) {
                $pricing = $this->leadPricing->calculate($lead);
                $selling = $lead->selling_price !== null
                    ? (float) $lead->selling_price
                    : (float) ($pricing['selling_price'] ?? 0);
                $payout = $lead->buying_price !== null
                    ? (float) $lead->buying_price
                    : ($this->leadPricing->suggestedSellerPayout($lead) ?? 0.0);

                return [
                    'id' => $lead->id,
                    'lead_reference' => $lead->lead_reference,
                    'scheme' => $lead->scheme?->name,
                    'scheme_id' => $lead->scheme_id,
                    'zone' => ZoneDisplay::code($lead->zone?->code),
                    'seller_company' => $lead->sellerCompany?->name ?? $lead->submittedBy?->name,
                    'size_m2' => $lead->size_m2 !== null ? (float) $lead->size_m2 : null,
                    'selling_price' => round($selling, 2),
                    'buying_price' => round($payout, 2),
                    'latitude' => $lead->latitude !== null ? (float) $lead->latitude : null,
                    'longitude' => $lead->longitude !== null ? (float) $lead->longitude : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $leadIds
     * @return array<string, mixed>
     */
    public function summarize(?Company $installer, array $leadIds, ?float $radiusKm = null): array
    {
        $leads = $this->loadEligibleLeads($leadIds);

        if ($installer) {
            $distances = $this->distancesFor($installer, $leads);

            return $this->buildSummary($installer, $leads, $distances, $radiusKm);
        }

        $summary = $this->buildSummaryWithoutInstaller($leads);
        $summary['suggested_name'] = $this->suggestName($leads, null);

        return $summary;
    }

    /**
     * @param  list<int>  $leadIds
     */
    public function create(
        User $admin,
        ?Company $installer,
        array $leadIds,
        ?string $name = null,
        ?float $radiusKm = null,
    ): LeadPackage {
        return DB::transaction(function () use ($admin, $installer, $leadIds, $name, $radiusKm) {
            if ($installer && ! PackageSettings::allowInstallerBasedCreation()) {
                throw ValidationException::withMessages([
                    'buyer_company_id' => __('rml.admin.packages.installer_creation_disabled'),
                ]);
            }
            if (! $installer && ! PackageSettings::allowWithoutInstaller()) {
                throw ValidationException::withMessages([
                    'buyer_company_id' => __('rml.admin.packages.installer_required'),
                ]);
            }
            if (! $installer && ! PackageSettings::allowManualCreation()) {
                throw ValidationException::withMessages([
                    'lead_ids' => __('rml.admin.packages.manual_creation_disabled'),
                ]);
            }

            $requestedIds = collect($leadIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            if (
                $requestedIds->count() < PackageSettings::minLeads()
                || $requestedIds->count() > PackageSettings::maxLeads()
            ) {
                throw ValidationException::withMessages([
                    'lead_ids' => __('rml.admin.packages.lead_count_out_of_range', [
                        'min' => PackageSettings::minLeads(),
                        'max' => PackageSettings::maxLeads(),
                    ]),
                ]);
            }

            $leads = $this->loadEligibleLeads($requestedIds->all(), lock: true);

            if ($leads->isEmpty() || $leads->count() !== $requestedIds->count()) {
                throw ValidationException::withMessages([
                    'lead_ids' => __('rml.admin.packages.leads_unavailable'),
                ]);
            }

            $totalArea = (float) $leads->sum(fn (Lead $lead) => (float) ($lead->size_m2 ?? 0));
            if (
                $totalArea < PackageSettings::minAreaM2()
                || $totalArea > PackageSettings::maxAreaM2()
            ) {
                throw ValidationException::withMessages([
                    'lead_ids' => __('rml.admin.packages.area_out_of_range', [
                        'min' => PackageSettings::minAreaM2(),
                        'max' => PackageSettings::maxAreaM2(),
                    ]),
                ]);
            }

            $summary = $installer
                ? $this->buildSummary(
                    $installer,
                    $leads,
                    $this->distancesFor($installer, $leads),
                    $radiusKm,
                )
                : array_merge(
                    $this->buildSummaryWithoutInstaller($leads),
                    ['suggested_name' => $this->suggestName($leads, null)],
                );

            if (
                $installer
                && isset($summary['distance_max_km'])
                && $summary['distance_max_km'] !== null
                && (float) $summary['distance_max_km'] > PackageSettings::maxLeadDistanceKm()
            ) {
                throw ValidationException::withMessages([
                    'lead_ids' => __('rml.admin.packages.distance_exceeded', [
                        'max' => PackageSettings::maxLeadDistanceKm(),
                    ]),
                ]);
            }

            $schemes = $leads->pluck('scheme.name')->filter()->unique()->values();
            if ($schemes->count() > 1 && ! PackageSettings::allowMixedScheme()) {
                throw ValidationException::withMessages([
                    'lead_ids' => __('rml.admin.packages.mixed_scheme_disabled'),
                ]);
            }

            $zones = $leads->pluck('zone.code')->filter()->unique()->values();
            if ($zones->count() > 1 && ! PackageSettings::allowMixedZone()) {
                throw ValidationException::withMessages([
                    'lead_ids' => __('rml.admin.packages.mixed_zone_disabled'),
                ]);
            }

            $packageType = $schemes->count() > 1
                ? PackageType::MixedZone
                : PackageType::Custom;

            $status = PackageStatus::tryFrom(PackageSettings::defaultStatus())
                ?? PackageStatus::Available;

            $package = LeadPackage::query()->create([
                'package_reference' => ReferenceGenerator::package(),
                'name' => $name !== null && trim($name) !== ''
                    ? trim($name)
                    : $summary['suggested_name'],
                'package_type' => $packageType,
                'scheme_id' => $schemes->count() === 1 ? $leads->first()?->scheme_id : null,
                'requested_leads_count' => $leads->count(),
                'size_range_min' => $leads->min(fn (Lead $lead) => $lead->size_m2),
                'size_range_max' => $leads->max(fn (Lead $lead) => $lead->size_m2),
                'distance_range_min' => $summary['distance_min_km'] ?? null,
                'distance_range_max' => $summary['distance_max_km'] ?? null,
                'zone_mix' => $summary['zone_mix'],
                'avg_price_per_m2' => $summary['avg_price_per_m2'],
                'estimated_total' => $summary['total_selling_price'],
                'status' => $status,
                'created_by_user_id' => $admin->id,
                'buyer_company_id' => $installer?->id,
            ]);

            $package->leads()->sync($leads->pluck('id')->all());

            $this->auditLogService->log(
                'package.created_by_admin',
                $package,
                null,
                [
                    'status' => $package->status?->value,
                    'lead_ids' => $leads->pluck('id')->all(),
                    'buyer_company_id' => $installer?->id,
                    'estimated_total' => $summary['total_selling_price'],
                ],
                $admin,
            );

            return $package->fresh([
                'scheme',
                'buyerCompany',
                'createdBy',
                'leads.scheme',
                'leads.zone',
                'leads.sellerCompany',
            ]);
        });
    }

    public function assignBuyer(LeadPackage $package, Company $installer): LeadPackage
    {
        if (in_array($package->status, [PackageStatus::Sold, PackageStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'package' => __('rml.admin.packages.cannot_assign_buyer'),
            ]);
        }

        $package->update(['buyer_company_id' => $installer->id]);

        $package->load(['leads']);
        if ($package->leads->isNotEmpty()
            && $installer->latitude !== null
            && $installer->longitude !== null
        ) {
            $distances = $this->distancesFor($installer, $package->leads);
            $valid = $distances->filter(fn ($km) => $km !== null)->values();
            if ($valid->isNotEmpty()) {
                $package->update([
                    'distance_range_min' => round((float) $valid->min(), 1),
                    'distance_range_max' => round((float) $valid->max(), 1),
                ]);
            }
        }

        return $package->fresh([
            'scheme',
            'buyerCompany',
            'createdBy',
            'leads.scheme',
            'leads.zone',
            'leads.sellerCompany',
        ]);
    }

    public function cancel(LeadPackage $package, ?User $admin = null): LeadPackage
    {
        if ($package->status === PackageStatus::Cancelled) {
            return $package;
        }

        if ($package->status === PackageStatus::Sold) {
            throw ValidationException::withMessages([
                'package' => __('rml.admin.packages.cannot_cancel_sold'),
            ]);
        }

        $before = ['status' => $package->status?->value];
        $package->update(['status' => PackageStatus::Cancelled]);

        if ($admin) {
            $this->auditLogService->log(
                'package.cancelled_by_admin',
                $package,
                $before,
                ['status' => PackageStatus::Cancelled->value],
                $admin,
            );
        }

        return $package->fresh([
            'scheme',
            'buyerCompany',
            'createdBy',
            'leads.scheme',
            'leads.zone',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(LeadPackage $package): array
    {
        $package->loadMissing([
            'scheme:id,name',
            'buyerCompany:id,name,city,formatted_address',
            'createdBy:id,name',
            'leads.scheme:id,name',
            'leads.zone:id,code,name',
            'leads.sellerCompany:id,name',
            'leads.submittedBy:id,name',
        ]);

        $leads = $package->leads;
        $installer = $package->buyerCompany;
        $distances = $installer
            ? $this->distancesFor($installer, $leads)
            : collect();

        $summary = $installer
            ? $this->buildSummary($installer, $leads, $distances, null)
            : $this->buildSummaryWithoutInstaller($leads);

        return [
            'id' => $package->id,
            'package_reference' => $package->package_reference,
            'name' => $package->name,
            'status' => $package->status?->value,
            'package_type' => $package->package_type?->value,
            'scheme' => $package->scheme?->name,
            'schemes' => $summary['schemes'] ?? [],
            'buyer_company' => $package->buyerCompany ? [
                'id' => $package->buyerCompany->id,
                'name' => $package->buyerCompany->name,
                'city' => $package->buyerCompany->city,
            ] : null,
            'created_by' => $package->createdBy?->name,
            'created_at' => $package->created_at?->toIso8601String(),
            'lead_count' => $leads->count(),
            'zone_mix' => $package->zone_mix,
            'distance_range_min' => $package->distance_range_min !== null
                ? (float) $package->distance_range_min
                : $summary['distance_min_km'],
            'distance_range_max' => $package->distance_range_max !== null
                ? (float) $package->distance_range_max
                : $summary['distance_max_km'],
            'total_size_m2' => $summary['total_size_m2'],
            'total_selling_price' => $summary['total_selling_price'],
            'total_seller_payout' => $summary['total_seller_payout'],
            'estimated_margin' => $summary['estimated_margin'],
            'avg_price_per_m2' => $summary['avg_price_per_m2'],
            'estimated_total' => $package->estimated_total !== null
                ? (float) $package->estimated_total
                : $summary['total_selling_price'],
            'leads' => $leads->map(function (Lead $lead) use ($distances) {
                $pricing = $this->leadPricing->calculate($lead);
                $selling = $lead->selling_price !== null
                    ? (float) $lead->selling_price
                    : (float) ($pricing['selling_price'] ?? 0);
                $payout = $lead->buying_price !== null
                    ? (float) $lead->buying_price
                    : ($this->leadPricing->suggestedSellerPayout($lead) ?? 0.0);

                return [
                    'id' => $lead->id,
                    'lead_reference' => $lead->lead_reference,
                    'scheme' => $lead->scheme?->name,
                    'zone' => ZoneDisplay::code($lead->zone?->code),
                    'seller_company' => $lead->sellerCompany?->name
                        ?? $lead->submittedBy?->name,
                    'size_m2' => $lead->size_m2 !== null ? (float) $lead->size_m2 : null,
                    'selling_price' => round($selling, 2),
                    'buying_price' => round($payout, 2),
                    'distance_km' => $distances->get($lead->id),
                    'view_url' => route('admin.leads-bought.show', $lead),
                ];
            })->values()->all(),
            'can_cancel' => in_array($package->status, [
                PackageStatus::Draft,
                PackageStatus::Available,
                PackageStatus::Locked,
            ], true),
            'can_assign_buyer' => $package->buyer_company_id === null
                && in_array($package->status, [
                    PackageStatus::Draft,
                    PackageStatus::Available,
                ], true),
        ];
    }

    public function suggestName(Collection $leads, ?Company $installer): string
    {
        $cityFromLeads = $leads
            ->pluck('city')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        $city = $installer?->city
            ?: ($installer ? (explode(',', (string) $installer->formatted_address)[0] ?? null) : null)
            ?: $cityFromLeads
            ?: 'Regional';
        $city = trim((string) $city);

        $schemes = $leads->pluck('scheme.name')->filter()->unique()->values();
        $schemeLabel = match (true) {
            $schemes->count() === 0 => 'Energy',
            $schemes->count() === 1 => (string) $schemes->first(),
            default => 'Mixed Energy',
        };

        return "{$city} {$schemeLabel} Leads Package";
    }

    /**
     * @param  list<int>  $leadIds
     * @return Collection<int, Lead>
     */
    private function loadEligibleLeads(array $leadIds, bool $lock = false): Collection
    {
        $ids = collect($leadIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $query = $this->eligibleQuery()->whereIn('leads.id', $ids->all());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function eligibleQuery(): Builder
    {
        return $this->leadAvailability->availableQuery()
            ->with(['scheme:id,name', 'zone:id,code,name'])
            ->whereNotNull('leads.latitude')
            ->whereNotNull('leads.longitude')
            ->whereDoesntHave('packages', function (Builder $packageQuery) {
                $packageQuery->whereIn(
                    'lead_packages.status',
                    AdminNearbyLeadMatchService::RESERVING_PACKAGE_STATUSES,
                );
            });
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return Collection<int, float>
     */
    private function distancesFor(Company $installer, Collection $leads): Collection
    {
        $lat = (float) $installer->latitude;
        $lng = (float) $installer->longitude;

        return $leads->mapWithKeys(function (Lead $lead) use ($lat, $lng) {
            if ($lead->latitude === null || $lead->longitude === null) {
                return [$lead->id => null];
            }

            return [
                $lead->id => $this->distanceService->betweenKm(
                    $lat,
                    $lng,
                    (float) $lead->latitude,
                    (float) $lead->longitude,
                ),
            ];
        });
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @param  Collection<int, float|null>  $distances
     * @return array<string, mixed>
     */
    private function buildSummary(
        Company $installer,
        Collection $leads,
        Collection $distances,
        ?float $radiusKm,
    ): array {
        $base = $this->buildSummaryWithoutInstaller($leads);
        $validDistances = $distances->filter(fn ($km) => $km !== null)->values();

        $base['installer'] = [
            'id' => $installer->id,
            'company_name' => $installer->name,
            'city' => $installer->city,
            'base_location' => $installer->formatted_address
                ?: trim(implode(', ', array_filter([
                    $installer->city,
                    $installer->postcode,
                ]))),
        ];
        $base['radius_km'] = $radiusKm;
        $base['average_distance_km'] = $validDistances->isEmpty()
            ? null
            : round((float) $validDistances->avg(), 1);
        $base['distance_min_km'] = $validDistances->isEmpty()
            ? null
            : round((float) $validDistances->min(), 1);
        $base['distance_max_km'] = $validDistances->isEmpty()
            ? null
            : round((float) $validDistances->max(), 1);
        $base['suggested_name'] = $this->suggestName($leads, $installer);
        $base['leads'] = collect($base['leads'])->map(function (array $row) use ($distances) {
            $row['distance_km'] = $distances->get($row['id']);

            return $row;
        })->values()->all();

        return $base;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return array<string, mixed>
     */
    private function buildSummaryWithoutInstaller(Collection $leads): array
    {
        $this->leadPricing->warmRulesCache();

        $totalSelling = 0.0;
        $totalPayout = 0.0;
        $totalSize = 0.0;
        $zoneMix = [];
        $schemes = [];
        $zones = [];
        $leadRows = [];

        foreach ($leads as $lead) {
            $pricing = $this->leadPricing->calculate($lead);
            $selling = $lead->selling_price !== null
                ? (float) $lead->selling_price
                : (float) ($pricing['selling_price'] ?? 0);
            $payout = $lead->buying_price !== null
                ? (float) $lead->buying_price
                : ($this->leadPricing->suggestedSellerPayout($lead) ?? 0.0);
            $size = $lead->size_m2 !== null ? (float) $lead->size_m2 : 0.0;
            $zoneCode = ZoneDisplay::code($lead->zone?->code) ?? 'unknown';
            $schemeName = $lead->scheme?->name;

            $totalSelling += $selling;
            $totalPayout += $payout;
            $totalSize += $size;
            $zoneMix[$zoneCode] = ($zoneMix[$zoneCode] ?? 0) + 1;

            if ($schemeName) {
                $schemes[$schemeName] = true;
            }
            if ($zoneCode !== 'unknown') {
                $zones[$zoneCode] = true;
            }

            $leadRows[] = [
                'id' => $lead->id,
                'lead_reference' => $lead->lead_reference,
                'scheme' => $schemeName,
                'zone' => $zoneCode !== 'unknown' ? $zoneCode : null,
                'size_m2' => $size > 0 ? $size : null,
                'selling_price' => round($selling, 2),
                'buying_price' => round($payout, 2),
                'expected_margin' => round($selling - $payout, 2),
                'distance_km' => null,
            ];
        }

        return [
            'selected_count' => $leads->count(),
            'schemes' => array_keys($schemes),
            'zones' => array_keys($zones),
            'zone_mix' => $zoneMix,
            'total_size_m2' => round($totalSize, 2),
            'total_selling_price' => round($totalSelling, 2),
            'total_seller_payout' => round($totalPayout, 2),
            'estimated_margin' => round($totalSelling - $totalPayout, 2),
            'avg_price_per_m2' => $totalSize > 0 ? round($totalSelling / $totalSize, 2) : null,
            'average_distance_km' => null,
            'distance_min_km' => null,
            'distance_max_km' => null,
            'leads' => $leadRows,
        ];
    }
}
