<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Scheme;
use App\Models\Zone;
use App\Services\Admin\AdminNearbyLeadMatchService;
use App\Services\Admin\LeadAuditService;
use App\Support\AdminLeadPresenter;
use App\Support\CaseInsensitiveSearch;
use App\Support\LeadStatusPresentation;
use App\Support\ListPagination;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeadBoughtController extends Controller
{
    public function __construct(
        private readonly LeadAuditService $leadAuditService,
        private readonly AdminNearbyLeadMatchService $nearbyMatch,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        return Inertia::render('Admin/LeadsBought/Index', $this->indexProps($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function indexProps(Request $request): array
    {
        $canMatch = $request->user()?->hasRole('super_admin')
            || $request->user()?->can(Permissions::MANAGE_BUYERS);

        $nearby = $canMatch
            ? $this->nearbyMatch->match($request)
            : [
                'active' => false,
                'installer' => null,
                'radius_km' => $this->nearbyMatch->defaultRadiusKm(),
                'leads' => [],
                'summary' => [
                    'matched_count' => 0,
                    'average_distance_km' => null,
                    'total_size_m2' => 0.0,
                ],
                'lead_ids' => [],
            ];

        $matchActive = (bool) ($nearby['active'] ?? false);
        $distanceByLeadId = collect($nearby['leads'] ?? [])
            ->mapWithKeys(fn (array $row) => [(int) $row['id'] => (float) $row['distance_km']]);

        $reservingStatuses = AdminNearbyLeadMatchService::RESERVING_PACKAGE_STATUSES;
        $withRelations = ['scheme:id,name', 'zone:id,code', 'submittedBy:id,name', 'sellerCompany:id,name'];
        $withExists = [
            'packages as in_active_package' => fn ($packageQuery) => $packageQuery
                ->whereIn('lead_packages.status', $reservingStatuses),
            'purchaseItems as in_active_purchase' => fn ($itemQuery) => $itemQuery
                ->whereHas('purchase', fn ($purchaseQuery) => $purchaseQuery->whereIn('status', [
                    \App\Enums\PurchaseStatus::Pending->value,
                    \App\Enums\PurchaseStatus::Paid->value,
                ])),
        ];

        if ($matchActive) {
            $matchedIds = $nearby['lead_ids'] ?? [];
            $query = Lead::query()
                ->whereIn('leads.id', $matchedIds === [] ? [0] : $matchedIds)
                ->with($withRelations)
                ->withExists($withExists);
        } else {
            $query = Lead::query()
                ->where('leads.status', '!=', LeadStatus::Sold->value)
                ->with($withRelations)
                ->withExists($withExists);

            if ($request->filled('status')) {
                $expanded = array_values(array_diff(
                    LeadStatusPresentation::expand($request->string('status')->toString()),
                    [LeadStatus::Sold->value],
                ));
                if ($expanded !== []) {
                    $query->whereIn('leads.status', $expanded);
                }
            } else {
                // Default registered tab: hide pure drafts unless explicitly filtered.
                // Admin-created drafts remain reachable via create redirect + search by reference.
                $query->where('leads.status', '!=', LeadStatus::Draft->value);
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

            if ($request->filled('seller_company_id')) {
                $query->where('leads.seller_company_id', $request->integer('seller_company_id'));
            }

            if ($request->filled('submitted_from')) {
                $query->whereDate('leads.created_at', '>=', $request->date('submitted_from'));
            }

            if ($request->filled('submitted_to')) {
                $query->whereDate('leads.created_at', '<=', $request->date('submitted_to'));
            }

            if ($request->filled('search')) {
                $search = '%'.$request->string('search')->toString().'%';
                $like = CaseInsensitiveSearch::operator();
                $query->where(function ($q) use ($search, $like) {
                    $q->where('leads.lead_reference', $like, $search)
                        ->orWhere('leads.customer_first_name', $like, $search)
                        ->orWhere('leads.customer_last_name', $like, $search)
                        ->orWhere('leads.notes', $like, $search)
                        ->orWhere('leads.cadastral_reference', $like, $search)
                        ->orWhereHas('submittedBy', fn ($uq) => $uq->where('name', $like, $search))
                        ->orWhereHas('sellerCompany', fn ($cq) => $cq->where('name', $like, $search));
                });
            }
        }

        $sortState = $this->applySort($query, $request, $matchActive, $distanceByLeadId);
        $perPage = ListPagination::perPage($request);

        $leads = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Lead $lead) use ($distanceByLeadId, $matchActive) {
                $row = AdminLeadPresenter::listRow($lead);
                if ($matchActive && $distanceByLeadId->has($lead->id)) {
                    $row['distance_km'] = $distanceByLeadId->get($lead->id);
                }
                $listed = in_array(
                    $lead->status?->value,
                    \App\Services\Buyer\LeadAvailabilityService::marketplaceStatusValues(),
                    true,
                );
                $unlocked = ! (bool) ($lead->in_active_package ?? false)
                    && ! (bool) ($lead->in_active_purchase ?? false);
                $row['packagable'] = $listed && ! (bool) ($lead->in_active_package ?? false);
                $row['sellable'] = $listed && $unlocked;

                return $row;
            });

        $radiusKm = (float) ($nearby['radius_km'] ?? $this->nearbyMatch->defaultRadiusKm());

        return [
            'leads' => $leads,
            'nearby' => $canMatch ? $nearby : null,
            'filters' => [
                'status' => $request->input('status'),
                'scheme_id' => $request->input('scheme_id'),
                'zone_id' => $request->input('zone_id'),
                'zone_code' => $request->input('zone_code'),
                'seller_company_id' => $request->input('seller_company_id'),
                'submitted_from' => $request->input('submitted_from'),
                'submitted_to' => $request->input('submitted_to'),
                'search' => $request->input('search'),
                'match_installer_id' => $canMatch ? $request->input('match_installer_id') : null,
                'radius_km' => $canMatch ? $radiusKm : null,
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => array_values(array_diff(
                    LeadStatusPresentation::visibleValues(includeDraft: false),
                    [LeadStatusPresentation::SOLD],
                )),
                'schemes' => Scheme::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name']),
                'zones' => Zone::query()
                    ->where('active', true)
                    ->orderBy('code')
                    ->get(['id', 'code', 'name'])
                    ->unique('code')
                    ->values()
                    ->map(fn (Zone $zone) => [
                        'id' => $zone->id,
                        'code' => $zone->code,
                        'name' => $zone->name,
                    ])
                    ->all(),
                'seller_companies' => Company::query()
                    ->where('type', CompanyType::Seller->value)
                    ->where('approval_status', ApprovalStatus::Approved->value)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'match_installers' => $canMatch
                    ? $this->nearbyMatch->installerOptions($radiusKm)
                    : [],
                'radius_options_km' => AdminNearbyLeadMatchService::RADIUS_OPTIONS_KM,
            ],
            'can_create' => (bool) (
                $request->user()?->hasRole('super_admin')
                || $request->user()?->can(Permissions::CREATE_ADMIN_LEADS)
            ),
            'can_sell' => (bool) (
                $request->user()?->hasRole('super_admin')
                || $request->user()?->can(Permissions::SELL_TO_BUYERS)
            ),
        ];
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()?->can(Permissions::VIEW_LEADS)
                || $request->user()?->hasRole('super_admin'),
            403,
        );
    }

    public function show(Request $request, Lead $lead): Response
    {
        $this->authorizeView($request);

        $canSell = (bool) (
            $request->user()?->hasRole('super_admin')
            || $request->user()?->can(Permissions::SELL_TO_BUYERS)
        );

        $availability = app(\App\Services\Buyer\LeadAvailabilityService::class);
        $inActivePackage = $lead->packages()
            ->whereIn('lead_packages.status', [
                \App\Enums\PackageStatus::Draft->value,
                \App\Enums\PackageStatus::Available->value,
                \App\Enums\PackageStatus::Locked->value,
                \App\Enums\PackageStatus::Sold->value,
            ])
            ->exists();
        $sellable = $availability->isAvailable($lead) && ! $inActivePackage;
        $sellableReason = null;
        if (! $sellable) {
            $sellableReason = match (true) {
                $lead->status === LeadStatus::Draft => 'draft',
                $lead->status === LeadStatus::Sold => 'sold',
                $lead->status === LeadStatus::Rejected => 'rejected',
                $lead->status === LeadStatus::Cancelled => 'cancelled',
                $inActivePackage => 'in_package',
                default => 'not_listed',
            };
        }

        $lead->loadMissing(['survey', 'latestCatastroSnapshot']);
        $surveySummary = \App\Support\SurveySummary::forLead($lead, $request->user(), 'admin');
        if (
            $sellable
            && $lead->survey_eligibility_status
            && $lead->survey_eligibility_status !== 'survey_approved'
        ) {
            $sellable = false;
            $sellableReason = 'survey_'.$lead->survey_eligibility_status;
        }

        $payload = [
            'lead' => AdminLeadPresenter::present($lead),
            'auditOpen' => $request->boolean('audit'),
            'can_sell' => $canSell,
            'sellable' => $sellable,
            'sellable_reason' => $sellableReason,
            'survey' => $surveySummary,
            'catastro' => app(\App\Services\Catastro\CatastroLookupService::class)
                ->presentForLead($lead, includeProtected: true),
            'can_lookup_catastro' => (bool) $request->user()?->can(\App\Support\Permissions::LOOKUP_CATASTRO),
            'can_review_catastro' => (bool) $request->user()?->can(\App\Support\Permissions::REVIEW_CATASTRO),
        ];

        if ($request->boolean('audit') || $request->boolean('audit_data')) {
            $payload['audit'] = $this->auditPayload($lead);
        }

        return Inertia::render('Admin/LeadsBought/Show', $payload);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, float>  $distanceByLeadId
     * @return array{sort: string, direction: string}
     */
    private function applySort(
        Builder $query,
        Request $request,
        bool $matchActive = false,
        $distanceByLeadId = null,
    ): array {
        $allowed = [
            'reference',
            'scheme',
            'zone',
            'seller',
            'buying_price',
            'selling_price',
            'status',
            'date',
            'distance',
        ];

        $sort = $request->string('sort')->toString();
        if ($sort === '' || ! in_array($sort, $allowed, true)) {
            $sort = $matchActive ? 'distance' : 'date';
        }

        if ($sort === 'distance' && ! $matchActive) {
            $sort = 'date';
        }

        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        if ($sort === 'distance' && ! $request->filled('direction')) {
            $direction = 'asc';
        }

        if ($sort === 'distance' && $matchActive && $distanceByLeadId !== null) {
            $orderedIds = $distanceByLeadId->sort()->keys()->values();
            if ($direction === 'desc') {
                $orderedIds = $orderedIds->reverse()->values();
            }
            if ($orderedIds->isEmpty()) {
                $query->orderBy('leads.id');
            } else {
                $cases = $orderedIds
                    ->map(fn ($id, $index) => 'WHEN '.(int) $id.' THEN '.$index)
                    ->implode(' ');
                $query->orderByRaw("CASE leads.id {$cases} ELSE 999999 END");
            }
        } else {
            match ($sort) {
                'reference' => $query->orderBy('leads.lead_reference', $direction),
                'scheme' => $query->orderBy(
                    Scheme::query()
                        ->select('name')
                        ->whereColumn('schemes.id', 'leads.scheme_id')
                        ->limit(1),
                    $direction,
                ),
                'zone' => $query->orderBy(
                    Zone::query()
                        ->select('code')
                        ->whereColumn('zones.id', 'leads.zone_id')
                        ->limit(1),
                    $direction,
                ),
                'seller' => $query->orderBy(
                    DB::table('users')
                        ->select('name')
                        ->whereColumn('users.id', 'leads.submitted_by_user_id')
                        ->limit(1),
                    $direction,
                ),
                'buying_price' => $query->orderBy('leads.buying_price', $direction),
                'selling_price' => $query->orderBy('leads.selling_price', $direction),
                'status' => $query->orderBy('leads.status', $direction),
                default => $query->orderBy('leads.created_at', $direction),
            };
        }

        $query->orderBy('leads.id', $direction === 'asc' ? 'asc' : 'desc');

        return [
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(Lead $lead): array
    {
        return [
            'checklist' => $this->leadAuditService->checklistItems($lead->scheme_id),
            'pricing' => $this->leadAuditService->pricingPreview($lead),
            'lead' => AdminLeadPresenter::present($lead),
        ];
    }
}
