<?php

namespace App\Http\Controllers\Seller;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreLeadRequest;
use App\Http\Requests\Seller\UpdateLeadEvidenceRequest;
use App\Models\Lead;
use App\Models\Scheme;
use App\Models\Zone;
use App\Services\LeadSubmissionService;
use App\Services\Seller\SellerLeadScope;
use App\Support\LeadStatusPresentation;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\SellerLeadPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadSubmissionService $leadSubmissionService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeViewAny($request);

        $query = SellerLeadScope::forUser($request->user())
            ->with(['scheme:id,name', 'zone:id,code', 'evidenceFiles', 'submittedBy:id,name']);

        if ($request->filled('status')) {
            $query->whereIn('status', LeadStatusPresentation::expand($request->string('status')->toString()));
        }

        if ($request->filled('scheme_id')) {
            $query->where('scheme_id', $request->integer('scheme_id'));
        }

        if ($request->filled('zone_code')) {
            $zoneCode = $request->string('zone_code')->toString();
            $query->whereHas('zone', fn ($q) => $q->where('code', $zoneCode));
        } elseif ($request->filled('zone_id')) {
            $query->where('zone_id', $request->integer('zone_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('lead_reference', 'ilike', $search)
                    ->orWhere('customer_first_name', 'ilike', $search)
                    ->orWhere('customer_last_name', 'ilike', $search);
            });
        }

        $sortState = ListSort::apply(
            $query,
            $request,
            [
                'reference' => 'lead_reference',
                'scheme' => fn ($q, string $direction) => $q->orderBy(
                    Scheme::query()
                        ->select('name')
                        ->whereColumn('schemes.id', 'leads.scheme_id')
                        ->limit(1),
                    $direction,
                ),
                'zone' => fn ($q, string $direction) => $q->orderBy(
                    Zone::query()
                        ->select('code')
                        ->whereColumn('zones.id', 'leads.zone_id')
                        ->limit(1),
                    $direction,
                ),
                'status' => 'status',
                'date' => 'created_at',
                'payout' => 'buying_price',
            ],
            'date',
            'desc',
        );

        $perPage = ListPagination::perPage($request);
        $viewer = $request->user();

        $leads = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Lead $lead) => SellerLeadPresenter::present($lead, $viewer));

        return Inertia::render('Seller/Leads/Index', [
            'leads' => $leads,
            'filters' => [
                'status' => $request->input('status'),
                'scheme_id' => $request->input('scheme_id'),
                'zone_code' => $request->input('zone_code'),
                'zone_id' => $request->input('zone_id'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'schemes' => Scheme::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name']),
                'zones' => Zone::query()
                    ->where('active', true)
                    ->orderBy('code')
                    ->get(['id', 'code', 'name', 'scheme_id'])
                    ->unique('code')
                    ->values()
                    ->map(fn (Zone $zone) => [
                        'id' => $zone->id,
                        'code' => $zone->code,
                        'name' => $zone->name,
                        'scheme_id' => $zone->scheme_id,
                    ])
                    ->all(),
                'statuses' => LeadStatusPresentation::visibleValues(includeDraft: true),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('create', Lead::class), 403);

        return Inertia::render('Seller/Leads/Create', [
            'schemes' => $this->schemesForForm(),
            'lead' => null,
        ]);
    }

    public function edit(Request $request, Lead $lead): Response
    {
        abort_unless($request->user()?->can('update', $lead), 403);
        abort_unless(
            in_array($lead->status, [LeadStatus::Draft, LeadStatus::PendingEvidence], true),
            404,
        );

        return Inertia::render('Seller/Leads/Create', [
            'schemes' => $this->schemesForForm(),
            'lead' => SellerLeadPresenter::present($lead, $request->user()),
        ]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $asDraft = $request->boolean('as_draft');

        $lead = $this->leadSubmissionService->submit(
            $request->user(),
            $request->leadPayload(),
            $asDraft,
            $request->uploadedEvidence(),
        );

        if ($asDraft) {
            return redirect()
                ->route('seller.leads.edit', $lead)
                ->with('success', __('rml.seller.leads.draft_success'));
        }

        return redirect()
            ->route('seller.leads.show', $lead)
            ->with('success', __('rml.seller.leads.submitted_success'));
    }

    public function show(Request $request, Lead $lead): Response
    {
        abort_unless($request->user()?->can('view', $lead), 403);

        return Inertia::render('Seller/Leads/Show', [
            'lead' => SellerLeadPresenter::present($lead, $request->user()),
            'schemes' => $this->schemesForForm(),
        ]);
    }

    public function updateEvidence(UpdateLeadEvidenceRequest $request, Lead $lead): RedirectResponse
    {
        $this->leadSubmissionService->addEvidence(
            $request->user(),
            $lead,
            $request->uploadedEvidence(),
        );

        return redirect()
            ->route('seller.leads.show', $lead)
            ->with('success', __('rml.seller.leads.updated_success'));
    }

    private function authorizeViewAny(Request $request): void
    {
        abort_unless($request->user()?->can('viewAny', Lead::class), 403);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function schemesForForm(): array
    {
        return Scheme::query()
            ->where('active', true)
            ->with([
                'fields' => fn ($q) => $q->where('active', true)->orderBy('sort_order'),
                'zones' => fn ($q) => $q->where('active', true)->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Scheme $scheme) => [
                'id' => $scheme->id,
                'name' => $scheme->name,
                'slug' => $scheme->slug,
                'fields' => $scheme->fields->map(fn ($field) => [
                    'id' => $field->id,
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type?->value,
                    'options' => $field->options,
                    'required' => $field->required,
                ])->values()->all(),
                'zones' => $scheme->zones->map(fn ($zone) => [
                    'id' => $zone->id,
                    'code' => $zone->code,
                    'name' => $zone->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
