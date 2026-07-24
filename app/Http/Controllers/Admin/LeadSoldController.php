<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Scheme;
use App\Models\Zone;
use App\Support\AdminLeadPresenter;
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
        abort_unless(
            $request->user()?->can(Permissions::VIEW_LEADS)
                || $request->user()?->hasRole('super_admin'),
            403,
        );

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

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('leads.lead_reference', 'like', $search)
                    ->orWhereHas('purchaseItems.purchase.buyerCompany', fn ($bq) => $bq->where('name', 'like', $search))
                    ->orWhereHas('submittedBy', fn ($uq) => $uq->where('name', 'like', $search))
                    ->orWhereHas('sellerCompany', fn ($cq) => $cq->where('name', 'like', $search));
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

                return [
                    ...$row,
                    'buyer_company' => $purchase?->buyerCompany?->name,
                    'buyer_name' => $purchase?->buyerUser?->name,
                    'payment_status' => $purchase?->payment?->status?->value,
                    'margin' => $lead->expected_margin !== null
                        ? (float) $lead->expected_margin
                        : (
                            $lead->selling_price !== null && $lead->buying_price !== null
                                ? round((float) $lead->selling_price - (float) $lead->buying_price, 2)
                                : null
                        ),
                ];
            });

        return Inertia::render('Admin/LeadsSold/Index', [
            'leads' => $leads,
            'filters' => [
                'scheme_id' => $request->input('scheme_id'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'schemes' => Scheme::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name']),
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
        ]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::VIEW_LEADS)
                || $request->user()?->hasRole('super_admin'),
            403,
        );

        abort_unless($lead->status === LeadStatus::Sold, 404);

        $presented = AdminLeadPresenter::present($lead);

        $activity = AuditLog::query()
            ->where('entity_type', Lead::class)
            ->where('entity_id', $lead->id)
            ->latest('id')
            ->limit(5)
            ->with('user:id,name')
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user_name' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/LeadsSold/Show', [
            'lead' => [
                ...$presented,
                'activity' => $activity,
            ],
        ]);
    }

    /**
     * @return array{sort: string, direction: string}
     */
    private function applySort(Builder $query, Request $request): array
    {
        $allowed = [
            'reference',
            'seller',
            'buyer',
            'scheme',
            'zone',
            'buying_price',
            'selling_price',
            'margin',
            'payment_status',
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
            'buyer' => $query->orderBy(
                DB::table('companies')
                    ->select('companies.name')
                    ->join('purchases', 'purchases.buyer_company_id', '=', 'companies.id')
                    ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')
                    ->whereColumn('purchase_items.lead_id', 'leads.id')
                    ->limit(1),
                $direction,
            ),
            'buying_price' => $query->orderBy('leads.buying_price', $direction),
            'selling_price' => $query->orderBy('leads.selling_price', $direction),
            'margin' => $query->orderByRaw(
                'COALESCE(leads.expected_margin, leads.selling_price - leads.buying_price) '.$direction,
            ),
            'payment_status' => $query->orderBy(
                DB::table('payments')
                    ->select('payments.status')
                    ->join('purchases', 'purchases.payment_id', '=', 'payments.id')
                    ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')
                    ->whereColumn('purchase_items.lead_id', 'leads.id')
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
