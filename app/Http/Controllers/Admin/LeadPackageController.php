<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\PackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminLeadPackageRequest;
use App\Models\Company;
use App\Models\LeadPackage;
use App\Models\Scheme;
use App\Services\Admin\AdminNearbyLeadMatchService;
use App\Services\Admin\AdminPackageBuilderService;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\PackageSettings;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadPackageController extends Controller
{
    public function __construct(
        private readonly AdminPackageBuilderService $packageBuilder,
        private readonly AdminNearbyLeadMatchService $nearbyMatch,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        return Inertia::render('Admin/Packages/Index', $this->indexProps($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function indexProps(Request $request): array
    {
        $query = LeadPackage::query()
            ->with([
                'buyerCompany:id,name,city',
                'scheme:id,name',
                'createdBy:id,name',
                'leads' => fn ($q) => $q->with('scheme:id,name'),
            ])
            ->withCount('leads')
            ->withSum('leads as leads_size_sum', 'size_m2');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('scheme_id')) {
            $schemeId = $request->integer('scheme_id');
            $query->where(function ($q) use ($schemeId) {
                $q->where('scheme_id', $schemeId)
                    ->orWhereHas('leads', fn ($lq) => $lq->where('leads.scheme_id', $schemeId));
            });
        }

        if ($request->filled('installer_id')) {
            $query->where('buyer_company_id', $request->integer('installer_id'));
        }

        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->date('created_from'));
        }

        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->date('created_to'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('package_reference', 'like', $search)
                    ->orWhere('name', 'like', $search)
                    ->orWhereHas('buyerCompany', fn ($cq) => $cq->where('name', 'like', $search));
            });
        }

        $sortState = ListSort::apply(
            $query,
            $request,
            [
                'reference' => 'package_reference',
                'name' => 'name',
                'buyer' => function (Builder $q, string $direction): void {
                    $q->orderBy(
                        Company::query()
                            ->select('name')
                            ->whereColumn('companies.id', 'lead_packages.buyer_company_id')
                            ->limit(1),
                        $direction,
                    );
                },
                'leads' => 'leads_count',
                'schemes' => function (Builder $q, string $direction): void {
                    $q->orderBy(
                        Scheme::query()
                            ->select('name')
                            ->whereColumn('schemes.id', 'lead_packages.scheme_id')
                            ->limit(1),
                        $direction,
                    );
                },
                'size' => 'leads_size_sum',
                'price' => 'estimated_total',
                'status' => 'status',
                'date' => 'created_at',
            ],
            'date',
            'desc',
        );
        $query->orderBy('lead_packages.id', $sortState['direction'] === 'asc' ? 'asc' : 'desc');

        $perPage = ListPagination::perPage($request);
        $packages = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (LeadPackage $package) {
                $schemes = $package->leads
                    ->pluck('scheme.name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($schemes === [] && $package->scheme?->name) {
                    $schemes = [$package->scheme->name];
                }

                return [
                    'id' => $package->id,
                    'package_reference' => $package->package_reference,
                    'name' => $package->name,
                    'status' => $package->status?->value,
                    'buyer_company' => $package->buyerCompany?->name,
                    'buyer_company_id' => $package->buyer_company_id,
                    'scheme' => $package->scheme?->name,
                    'schemes' => $schemes,
                    'lead_count' => $package->leads_count,
                    'total_size_m2' => round((float) ($package->leads_size_sum ?? $package->leads->sum('size_m2')), 2),
                    'estimated_total' => $package->estimated_total !== null
                        ? (float) $package->estimated_total
                        : null,
                    'created_by' => $package->createdBy?->name,
                    'created_at' => $package->created_at?->toIso8601String(),
                    'can_cancel' => in_array($package->status, [
                        PackageStatus::Draft,
                        PackageStatus::Available,
                        PackageStatus::Locked,
                    ], true),
                ];
            });

        return [
            'packages' => $packages,
            'filters' => [
                'status' => $request->input('status'),
                'scheme_id' => $request->input('scheme_id'),
                'installer_id' => $request->input('installer_id'),
                'created_from' => $request->input('created_from'),
                'created_to' => $request->input('created_to'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => PackageStatus::values(),
                'schemes' => Scheme::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name']),
                'installers' => Company::query()
                    ->where('type', CompanyType::Buyer->value)
                    ->where('approval_status', ApprovalStatus::Approved->value)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'can_manage' => $this->canManage($request),
            'can_create' => $this->canManage($request),
            'can_sell' => $this->canSell($request),
        ];
    }

    public function create(Request $request): Response
    {
        abort_unless($this->canManage($request), 403);

        $radiusKm = $this->nearbyMatch->resolveRadiusKm($request);
        $installerId = $request->integer('match_installer_id');
        $nearby = null;
        $eligible = $this->packageBuilder->eligibleLeadsForPicker();

        if ($installerId > 0) {
            $nearby = $this->nearbyMatch->match($request);
            $matchedIds = collect($nearby['lead_ids'] ?? [])->flip();
            $eligible = collect($eligible)
                ->filter(fn (array $lead) => $matchedIds->has($lead['id']))
                ->map(function (array $lead) use ($nearby) {
                    $matched = collect($nearby['leads'] ?? [])->firstWhere('id', $lead['id']);
                    if (is_array($matched)) {
                        $lead['distance_km'] = $matched['distance_km'] ?? null;
                        $lead['city'] = $matched['city'] ?? ($lead['city'] ?? null);
                    }

                    return $lead;
                })
                ->sortBy('distance_km')
                ->values()
                ->all();
        }

        return Inertia::render('Admin/Packages/Create', [
            'eligible_leads' => $eligible,
            'installers' => $this->nearbyMatch->installerOptions($radiusKm),
            'radius_options_km' => AdminNearbyLeadMatchService::RADIUS_OPTIONS_KM,
            'default_radius_km' => $this->nearbyMatch->defaultRadiusKm(),
            'filters' => [
                'match_installer_id' => $installerId > 0 ? $installerId : null,
                'radius_km' => $radiusKm,
            ],
            'nearby' => $nearby,
            'allow_without_buyer' => PackageSettings::allowWithoutBuyer(),
            'allow_mixed_scheme' => PackageSettings::allowMixedScheme(),
        ]);
    }

    public function show(Request $request, LeadPackage $package): Response
    {
        $this->authorizeView($request);

        return Inertia::render('Admin/Packages/Show', [
            'package' => $this->packageBuilder->present($package),
            'can_manage' => $this->canManage($request),
            'can_sell' => $this->canSell($request),
            'installer_options' => $this->canManage($request)
                ? Company::query()
                    ->where('type', CompanyType::Buyer->value)
                    ->where('approval_status', ApprovalStatus::Approved->value)
                    ->orderBy('name')
                    ->get(['id', 'name', 'city'])
                : [],
        ]);
    }

    public function store(StoreAdminLeadPackageRequest $request): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);

        if (! PackageSettings::allowWithoutBuyer() && ! $request->filled('match_installer_id')) {
            return back()->withErrors([
                'match_installer_id' => __('rml.admin.packages.buyer_required'),
            ]);
        }

        $installer = null;
        if ($request->filled('match_installer_id')) {
            $installer = $this->nearbyMatch->findInstaller($request->integer('match_installer_id'));
            abort_unless($installer !== null, 422, 'Installer not found or not eligible.');
        }

        $package = $this->packageBuilder->create(
            $request->user(),
            $installer,
            $request->input('lead_ids', []),
            $request->input('name'),
            $request->filled('radius_km') ? (float) $request->integer('radius_km') : null,
        );

        return redirect()
            ->route('admin.leads.index', ['tab' => 'packages'])
            ->with('success', __('rml.admin.packages.created'));
    }

    public function assignBuyer(Request $request, LeadPackage $package): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);

        $request->validate([
            'buyer_company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $installer = $this->nearbyMatch->findInstaller($request->integer('buyer_company_id'));
        abort_unless($installer !== null, 422);

        $this->packageBuilder->assignBuyer($package, $installer);

        return redirect()
            ->route('admin.packages.show', $package)
            ->with('success', __('rml.admin.packages.buyer_assigned'));
    }

    public function cancel(Request $request, LeadPackage $package): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);

        $this->packageBuilder->cancel($package, $request->user());

        return redirect()
            ->route('admin.leads.index', ['tab' => 'packages'])
            ->with('success', __('rml.admin.packages.cancelled'));
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->hasRole('super_admin')
                || $user?->can(Permissions::MANAGE_PACKAGES)
                || (
                    $user?->can(Permissions::VIEW_LEADS)
                    && $user?->hasRole('admin_staff')
                ),
            403,
        );
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return (bool) (
            $user?->hasRole('super_admin')
            || $user?->can(Permissions::MANAGE_PACKAGES)
        );
    }

    private function canSell(Request $request): bool
    {
        $user = $request->user();

        return (bool) (
            $user?->hasRole('super_admin')
            || $user?->can(Permissions::SELL_TO_BUYERS)
        );
    }
}
