<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Scheme;
use App\Models\Zone;
use App\Services\Admin\LeadAuditService;
use App\Support\AdminLeadPresenter;
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
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::VIEW_LEADS)
                || $request->user()?->hasRole('super_admin'),
            403,
        );

        $query = Lead::query()
            ->where('leads.status', '!=', LeadStatus::Draft->value)
            ->with(['scheme:id,name', 'zone:id,code', 'submittedBy:id,name', 'sellerCompany:id,name']);

        if ($request->filled('status')) {
            $query->whereIn('leads.status', LeadStatusPresentation::expand($request->string('status')->toString()));
        }

        if ($request->filled('scheme_id')) {
            $query->where('leads.scheme_id', $request->integer('scheme_id'));
        }

        if ($request->filled('zone_id')) {
            $query->where('leads.zone_id', $request->integer('zone_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('leads.lead_reference', 'like', $search)
                    ->orWhere('leads.customer_first_name', 'like', $search)
                    ->orWhere('leads.customer_last_name', 'like', $search)
                    ->orWhereHas('submittedBy', fn ($uq) => $uq->where('name', 'like', $search))
                    ->orWhereHas('sellerCompany', fn ($cq) => $cq->where('name', 'like', $search))
                    ->orWhereHas(
                        'purchaseItems.purchase.buyerCompany',
                        fn ($bq) => $bq->where('name', 'like', $search),
                    );
            });
        }

        $sortState = $this->applySort($query, $request);
        $perPage = ListPagination::perPage($request);

        $leads = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Lead $lead) => AdminLeadPresenter::listRow($lead));

        return Inertia::render('Admin/LeadsBought/Index', [
            'leads' => $leads,
            'filters' => [
                'status' => $request->input('status'),
                'scheme_id' => $request->input('scheme_id'),
                'zone_id' => $request->input('zone_id'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => LeadStatusPresentation::visibleValues(includeDraft: false),
                'schemes' => Scheme::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name']),
                'zones' => Zone::query()->where('active', true)->orderBy('code')->get(['id', 'code', 'name']),
            ],
        ]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::VIEW_LEADS)
                || $request->user()?->hasRole('super_admin'),
            403,
        );

        abort_if($lead->status === LeadStatus::Draft, 404);

        $payload = [
            'lead' => AdminLeadPresenter::present($lead),
            'auditOpen' => $request->boolean('audit'),
        ];

        if ($request->boolean('audit') || $request->boolean('audit_data')) {
            $payload['audit'] = $this->auditPayload($lead);
        }

        return Inertia::render('Admin/LeadsBought/Show', $payload);
    }

    /**
     * @return array{sort: string, direction: string}
     */
    private function applySort(Builder $query, Request $request): array
    {
        $allowed = [
            'reference',
            'scheme',
            'zone',
            'seller',
            'buying_price',
            'selling_price',
            'status',
            'date',
        ];

        $sort = $request->string('sort')->toString();
        if ($sort === '' || ! in_array($sort, $allowed, true)) {
            $sort = 'date';
        }

        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

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
