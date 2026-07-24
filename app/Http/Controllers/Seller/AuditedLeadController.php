<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Scheme;
use App\Services\Seller\SellerLeadScope;
use App\Support\LeadStatusPresentation;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\SellerLeadPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditedLeadController extends Controller
{
    private const AUDITED_VISIBLE_STATUSES = [
        LeadStatusPresentation::NEEDS_INFORMATION,
        LeadStatusPresentation::LISTED,
        LeadStatusPresentation::REJECTED,
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('viewAny', Lead::class), 403);

        $auditedInternal = [
            ...LeadStatusPresentation::expand(LeadStatusPresentation::NEEDS_INFORMATION),
            ...LeadStatusPresentation::expand(LeadStatusPresentation::LISTED),
            ...LeadStatusPresentation::expand(LeadStatusPresentation::REJECTED),
        ];

        $query = SellerLeadScope::forUser($request->user())
            ->with([
                'scheme:id,name',
                'zone:id,code',
                'evidenceFiles',
                'audits' => fn ($q) => $q->latest('id'),
            ])
            ->whereIn('status', $auditedInternal);

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, self::AUDITED_VISIBLE_STATUSES, true)) {
                $query->whereIn('status', LeadStatusPresentation::expand($status));
            }
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('lead_reference', 'like', $search)
                    ->orWhere('customer_first_name', 'like', $search)
                    ->orWhere('customer_last_name', 'like', $search);
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

        return Inertia::render('Seller/AuditedLeads', [
            'leads' => $leads,
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => self::AUDITED_VISIBLE_STATUSES,
            ],
        ]);
    }
}
