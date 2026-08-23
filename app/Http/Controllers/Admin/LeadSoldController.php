<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Scheme;
use App\Models\Zone;
use App\Support\AdminLeadPresenter;
use App\Support\CaseInsensitiveSearch;
use App\Support\ListPagination;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeadSoldController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        return Inertia::render('Admin/LeadsSold/Index', $this->indexProps($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function indexProps(Request $request): array
    {
        $query = Lead::query()
            ->where('leads.status', LeadStatus::Sold->value)
            ->with([
                'scheme:id,name',
                'zone:id,code',
                'submittedBy:id,name',
                'sellerCompany:id,name',
                'purchaseItems.purchase.buyerCompany:id,name',
                'purchaseItems.purchase.buyerUser:id,name',
                'purchaseItems.purchase.payment:id,payment_reference,status,amount,paid_at,method',
            ]);

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
                    ->orWhere('leads.notes', $like, $search)
                    ->orWhere('leads.cadastral_reference', $like, $search)
                    ->orWhereHas('purchaseItems.purchase.buyerCompany', fn ($bq) => $bq->where('name', $like, $search))
                    ->orWhereHas('submittedBy', fn ($uq) => $uq->where('name', $like, $search))
                    ->orWhereHas('sellerCompany', fn ($cq) => $cq->where('name', $like, $search));
            });
        }

        $sortState = $this->applySort($query, $request);
        $perPage = ListPagination::perPage($request);

        $leads = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Lead $lead) {
                $row = AdminLeadPresenter::listRow($lead);
                $purchase = $lead->purchaseItems->first()?->purchase;
                $paymentStatus = $purchase?->payment?->status?->value;
                $releaseStatus = $paymentStatus === PaymentStatus::Paid->value
                    ? 'released'
                    : 'pending_release';

                return [
                    ...$row,
                    'buyer_company' => $purchase?->buyerCompany?->name,
                    'buyer_company_id' => $purchase?->buyer_company_id,
                    'buyer_user_id' => $purchase?->buyer_user_id,
                    'buyer_name' => $purchase?->buyerUser?->name,
                    'payment_id' => $purchase?->payment?->id,
                    'payment_reference' => $purchase?->payment?->payment_reference,
                    'payment_status' => $paymentStatus,
                    'release_status' => $releaseStatus,
                    'margin' => $lead->expected_margin !== null
                        ? (float) $lead->expected_margin
                        : (
                            $lead->selling_price !== null && $lead->buying_price !== null
                                ? round((float) $lead->selling_price - (float) $lead->buying_price, 2)
                                : null
                        ),
                ];
            });

        return [
            'leads' => $leads,
            'filters' => [
                'scheme_id' => $request->input('scheme_id'),
                'installer_id' => $request->input('installer_id'),
                'payment_status' => $request->input('payment_status'),
                'release_status' => $request->input('release_status'),
                'sold_from' => $request->input('sold_from'),
                'sold_to' => $request->input('sold_to'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'schemes' => Scheme::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name']),
                'installers' => Company::query()
                    ->where('type', CompanyType::Buyer->value)
                    ->where('approval_status', ApprovalStatus::Approved->value)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'payment_statuses' => PaymentStatus::values(),
                'release_statuses' => ['released', 'pending_release'],
            ],
            'summary' => [
                'total_sold' => Lead::query()->where('status', LeadStatus::Sold->value)->count(),
                'total_revenue' => (float) Lead::query()
                    ->where('status', LeadStatus::Sold->value)
                    ->sum('selling_price'),
                'total_margin' => (float) Lead::query()
                    ->where('status', LeadStatus::Sold->value)
                    ->selectRaw('COALESCE(SUM(COALESCE(expected_margin, selling_price - buying_price)), 0) as total')
                    ->value('total'),
            ],
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

        abort_unless($lead->status === LeadStatus::Sold, 404);

        $lead->load([
            'scheme',
            'zone',
            'submittedBy',
            'sellerCompany',
            'purchaseItems.purchase.buyerCompany',
            'purchaseItems.purchase.buyerUser',
            'purchaseItems.purchase.payment',
        ]);

        $purchase = $lead->purchaseItems->first()?->purchase;
        $payment = $purchase?->payment;
        $releaseStatus = $payment?->status === PaymentStatus::Paid
            ? 'released'
            : 'pending_release';

        $lead->loadMissing(['survey', 'latestCatastroSnapshot']);

        return Inertia::render('Admin/LeadsSold/Show', [
            'lead' => [
                ...AdminLeadPresenter::present($lead),
                'buyer' => [
                    'company' => $purchase?->buyerCompany?->name,
                    'name' => $purchase?->buyerUser?->name,
                    'payment_reference' => $payment?->payment_reference,
                    'payment_status' => $payment?->status?->value,
                    'payment_method' => $payment?->method?->value,
                    'release_status' => $releaseStatus,
                ],
            ],
            'catastro' => app(\App\Services\Catastro\CatastroLookupService::class)
                ->presentForLead($lead, includeProtected: true),
            'can_lookup_catastro' => (bool) $request->user()?->can(\App\Support\Permissions::LOOKUP_CATASTRO),
            'can_review_catastro' => (bool) $request->user()?->can(\App\Support\Permissions::REVIEW_CATASTRO),
            'audit_logs' => AuditLog::query()
                ->where('auditable_type', Lead::class)
                ->where('auditable_id', $lead->id)
                ->latest()
                ->limit(20)
                ->get(['id', 'action', 'created_at', 'actor_user_id']),
        ]);
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
            'buyer',
            'buying_price',
            'selling_price',
            'margin',
            'status',
            'date',
            'payment_status',
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
            'buyer' => $query->orderBy(
                Company::query()
                    ->select('name')
                    ->whereIn(
                        'companies.id',
                        DB::table('purchases')
                            ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')
                            ->whereColumn('purchase_items.lead_id', 'leads.id')
                            ->select('purchases.buyer_company_id')
                            ->limit(1),
                    )
                    ->limit(1),
                $direction,
            ),
            'buying_price' => $query->orderBy('leads.buying_price', $direction),
            'selling_price' => $query->orderBy('leads.selling_price', $direction),
            'margin' => $query->orderByRaw(
                'COALESCE(leads.expected_margin, leads.selling_price - leads.buying_price) '.$direction,
            ),
            'status' => $query->orderBy('leads.status', $direction),
            'payment_status' => $query->orderBy(
                DB::table('payments')
                    ->join('purchases', 'purchases.payment_id', '=', 'payments.id')
                    ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')
                    ->whereColumn('purchase_items.lead_id', 'leads.id')
                    ->select('payments.status')
                    ->limit(1),
                $direction,
            ),
            default => $query->orderBy('leads.sold_at', $direction),
        };

        $query->orderBy('leads.id', $direction === 'asc' ? 'asc' : 'desc');

        return [
            'sort' => $sort,
            'direction' => $direction,
        ];
    }
}
