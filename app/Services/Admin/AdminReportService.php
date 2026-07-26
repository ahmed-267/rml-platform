<?php

namespace App\Services\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Models\Commission;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Purchase;
use App\Models\Scheme;
use App\Models\User;
use App\Support\LeadStatusPresentation;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminReportService
{
    private const ACCEPTED_STATUSES = [
        LeadStatus::Accepted->value,
        LeadStatus::Priced->value,
        LeadStatus::Listed->value,
        LeadStatus::Sold->value,
    ];

    private const CACHE_SECONDS = 60;

    private const TABS = ['overview', 'charts', 'performance', 'tables'];

    /** @var array{date_from: ?string, date_to: ?string, scheme_id: ?int} */
    private array $filters = [
        'date_from' => null,
        'date_to' => null,
        'scheme_id' => null,
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $normalized = $this->normalizeFilters($filters);
        $cacheKey = 'admin.reports.payload.'.md5(json_encode($normalized));

        $payload = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($normalized) {
            $this->filters = $normalized;

            $sellerPerformance = $this->sellerPerformance();
            $buyerPerformance = $this->buyerPerformance();

            return [
                'summary' => $this->summary(),
                'lead_pipeline' => $this->leadPipeline(),
                'registrations_by_status' => $this->registrationsByStatus(),
                'monthly_sold' => $this->monthlySoldCounts(),
                'charts' => [
                    'lead_volume' => $this->leadVolumeByMonth(),
                    'leads_by_zone' => $this->leadsByZone(),
                    'leads_by_scheme' => $this->leadsByScheme(),
                    'revenue_margin' => $this->revenueMarginByMonth(),
                    'seller_performance' => $sellerPerformance,
                    'buyer_performance' => $buyerPerformance,
                ],
                'seller_performance' => $sellerPerformance,
                'buyer_performance' => $buyerPerformance,
            ];
        });

        return [
            ...$payload,
            'filters' => [
                ...$normalized,
                'tab' => isset($filters['tab']) && is_string($filters['tab']) && in_array($filters['tab'], self::TABS, true)
                    ? $filters['tab']
                    : 'overview',
            ],
            'filterOptions' => [
                'schemes' => Scheme::query()
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{date_from: ?string, date_to: ?string, scheme_id: ?int}
     */
    private function normalizeFilters(array $filters): array
    {
        $schemeId = $filters['scheme_id'] ?? null;

        return [
            'date_from' => isset($filters['date_from']) && $filters['date_from'] !== ''
                ? (string) $filters['date_from']
                : null,
            'date_to' => isset($filters['date_to']) && $filters['date_to'] !== ''
                ? (string) $filters['date_to']
                : null,
            'scheme_id' => $schemeId !== null && $schemeId !== ''
                ? (int) $schemeId
                : null,
        ];
    }

    private function applyDateRange(Builder|QueryBuilder $query, string|Expression $column): void
    {
        if ($this->filters['date_from']) {
            $query->where(
                $column,
                '>=',
                Carbon::parse($this->filters['date_from'])->startOfDay(),
            );
        }

        if ($this->filters['date_to']) {
            $query->where(
                $column,
                '<=',
                Carbon::parse($this->filters['date_to'])->endOfDay(),
            );
        }
    }

    private function baseLeadQuery(string $dateColumn = 'created_at'): Builder
    {
        $query = Lead::query();
        $this->applyDateRange($query, $dateColumn);

        if ($this->filters['scheme_id']) {
            $query->where($query->getModel()->getTable().'.scheme_id', $this->filters['scheme_id']);
        }

        return $query;
    }

    private function applySchemeOnPurchases(QueryBuilder $query): void
    {
        if (! $this->filters['scheme_id']) {
            return;
        }

        $schemeId = $this->filters['scheme_id'];

        $query->whereExists(function ($sub) use ($schemeId) {
            $sub->select(DB::raw('1'))
                ->from('purchase_items')
                ->join('leads', 'leads.id', '=', 'purchase_items.lead_id')
                ->whereColumn('purchase_items.purchase_id', 'purchases.id')
                ->where('leads.scheme_id', $schemeId);
        });
    }

    private function applySchemeOnPayments(Builder $query): void
    {
        if (! $this->filters['scheme_id']) {
            return;
        }

        $schemeId = $this->filters['scheme_id'];

        $query->whereHas('purchases.items.lead', fn (Builder $leadQuery) => $leadQuery->where('scheme_id', $schemeId));
    }

    private function applySchemeOnCommissions(Builder $query): void
    {
        if (! $this->filters['scheme_id']) {
            return;
        }

        $query->whereHas('lead', fn (Builder $leadQuery) => $leadQuery->where('scheme_id', $this->filters['scheme_id']));
    }

    private function applySchemeOnPayouts(Builder $query): void
    {
        if (! $this->filters['scheme_id']) {
            return;
        }

        $schemeId = $this->filters['scheme_id'];

        $query->whereHas('payment.purchases.items.lead', fn (Builder $leadQuery) => $leadQuery->where('scheme_id', $schemeId));
    }

    /**
     * @return array<string, float|int>
     */
    private function summary(): array
    {
        $reviewedCount = $this->baseLeadQuery()
            ->whereIn('status', [
                ...self::ACCEPTED_STATUSES,
                LeadStatus::Rejected->value,
            ])
            ->count();

        $acceptedCount = $this->baseLeadQuery()
            ->whereIn('status', self::ACCEPTED_STATUSES)
            ->count();

        $paymentQuery = Payment::query()
            ->where('type', PaymentType::BuyerPayment->value)
            ->where('status', PaymentStatus::Paid->value);
        $this->applyDateRange($paymentQuery, DB::raw('COALESCE(paid_at, created_at)'));
        $this->applySchemeOnPayments($paymentQuery);

        $payoutQuery = Payout::query()->where('status', PayoutStatus::Paid->value);
        $this->applyDateRange($payoutQuery, DB::raw('COALESCE(paid_at, created_at)'));
        $this->applySchemeOnPayouts($payoutQuery);

        $commissionDueQuery = Commission::query()->where('status', 'due');
        $this->applyDateRange($commissionDueQuery, DB::raw('COALESCE(due_at, created_at)'));
        $this->applySchemeOnCommissions($commissionDueQuery);

        $commissionPaidQuery = Commission::query()->where('status', 'paid');
        $this->applyDateRange($commissionPaidQuery, DB::raw('COALESCE(paid_at, created_at)'));
        $this->applySchemeOnCommissions($commissionPaidQuery);

        $purchaseQuery = Purchase::query();
        $this->applyDateRange($purchaseQuery, DB::raw('COALESCE(purchased_at, created_at)'));
        if ($this->filters['scheme_id']) {
            $purchaseQuery->whereHas('items.lead', fn (Builder $leadQuery) => $leadQuery->where('scheme_id', $this->filters['scheme_id']));
        }

        $approvedSellerQuery = User::query()
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                UserRole::SellerCompanyAdmin->value,
                UserRole::IndividualSellerAgent->value,
            ]));
        $this->applyDateRange($approvedSellerQuery, 'created_at');

        $approvedBuyerQuery = User::query()
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->whereHas('roles', fn ($q) => $q->where('name', UserRole::BuyerAdmin->value));
        $this->applyDateRange($approvedBuyerQuery, 'created_at');

        return [
            'total_leads_submitted' => $this->baseLeadQuery()
                ->where('status', '!=', LeadStatus::Draft->value)
                ->count(),
            'leads_sold' => $this->baseLeadQuery('sold_at')
                ->where('status', LeadStatus::Sold->value)
                ->count(),
            'acceptance_rate' => $reviewedCount > 0 ? round(($acceptedCount / $reviewedCount) * 100, 1) : 0.0,
            'buyer_revenue_paid' => (float) $paymentQuery->sum('amount'),
            'seller_payouts_paid' => (float) $payoutQuery->sum('amount'),
            'total_margin' => (float) $this->baseLeadQuery('sold_at')
                ->where('status', LeadStatus::Sold->value)
                ->selectRaw('COALESCE(SUM(COALESCE(expected_margin, selling_price - buying_price)), 0) as total')
                ->value('total'),
            'commissions_due' => (float) $commissionDueQuery->sum('commission_amount'),
            'commissions_paid' => (float) $commissionPaidQuery->sum('commission_amount'),
            'total_purchases' => $purchaseQuery->count(),
            'approved_sellers' => $approvedSellerQuery->count(),
            'approved_buyers' => $approvedBuyerQuery->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function leadPipeline(): array
    {
        return LeadStatusPresentation::groupCounts(
            $this->baseLeadQuery()
                ->where('status', '!=', LeadStatus::Draft->value)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->map(fn ($count) => (int) $count)
                ->all()
        );
    }

    /**
     * @return array<string, int>
     */
    private function registrationsByStatus(): array
    {
        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                UserRole::SellerCompanyAdmin->value,
                UserRole::IndividualSellerAgent->value,
                UserRole::BuyerAdmin->value,
            ]));

        $this->applyDateRange($query, 'created_at');

        return $query
            ->select('approval_status', DB::raw('count(*) as count'))
            ->groupBy('approval_status')
            ->pluck('count', 'approval_status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function monthlySoldCounts(): array
    {
        return $this->baseLeadQuery('sold_at')
            ->where('status', LeadStatus::Sold->value)
            ->whereNotNull('sold_at')
            ->selectRaw("to_char(sold_at, 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return list<array{month: string, submitted: int, accepted: int, rejected: int}>
     */
    private function leadVolumeByMonth(): array
    {
        $acceptedList = implode(',', array_map(
            fn (string $status) => "'{$status}'",
            self::ACCEPTED_STATUSES,
        ));
        $rejected = LeadStatus::Rejected->value;

        $submitted = $this->baseLeadQuery()
            ->where('status', '!=', LeadStatus::Draft->value)
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $accepted = $this->baseLeadQuery()
            ->where(function ($query) use ($acceptedList) {
                $query->whereNotNull('accepted_at')
                    ->orWhereRaw("status in ({$acceptedList})");
            })
            ->selectRaw("to_char(COALESCE(accepted_at, created_at), 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $rejectedRows = $this->baseLeadQuery()
            ->where(function ($query) use ($rejected) {
                $query->whereNotNull('rejected_at')
                    ->orWhere('status', $rejected);
            })
            ->selectRaw("to_char(COALESCE(rejected_at, created_at), 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $months = collect($submitted->keys())
            ->merge($accepted->keys())
            ->merge($rejectedRows->keys())
            ->unique()
            ->sort()
            ->values();

        return $months->map(fn (string $month) => [
            'month' => $month,
            'submitted' => (int) ($submitted[$month] ?? 0),
            'accepted' => (int) ($accepted[$month] ?? 0),
            'rejected' => (int) ($rejectedRows[$month] ?? 0),
        ])->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function leadsByZone(): array
    {
        return $this->baseLeadQuery()
            ->where('leads.status', '!=', LeadStatus::Draft->value)
            ->leftJoin('zones', 'leads.zone_id', '=', 'zones.id')
            ->selectRaw('zones.code as label, count(*) as total')
            ->groupBy('zones.code')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label ? (string) $row->label : '—',
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function leadsByScheme(): array
    {
        return $this->baseLeadQuery()
            ->where('leads.status', '!=', LeadStatus::Draft->value)
            ->leftJoin('schemes', 'leads.scheme_id', '=', 'schemes.id')
            ->selectRaw('schemes.name as label, count(*) as total')
            ->groupBy('schemes.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label ? (string) $row->label : '—',
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{month: string, revenue: float, cost: float, margin: float}>
     */
    private function revenueMarginByMonth(): array
    {
        $rows = $this->baseLeadQuery('sold_at')
            ->where('status', LeadStatus::Sold->value)
            ->selectRaw("to_char(COALESCE(sold_at, created_at), 'YYYY-MM') as month")
            ->selectRaw('COALESCE(SUM(selling_price), 0) as revenue')
            ->selectRaw('COALESCE(SUM(buying_price), 0) as cost')
            ->selectRaw('COALESCE(SUM(COALESCE(expected_margin, selling_price - buying_price)), 0) as margin')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        if ($rows->isNotEmpty()) {
            return $rows->map(fn ($row) => [
                'month' => (string) $row->month,
                'revenue' => round((float) $row->revenue, 2),
                'cost' => round((float) $row->cost, 2),
                'margin' => round((float) $row->margin, 2),
            ])->values()->all();
        }

        $payments = Payment::query()
            ->where('type', PaymentType::BuyerPayment->value)
            ->where('status', PaymentStatus::Paid->value);
        $this->applyDateRange($payments, DB::raw('COALESCE(paid_at, created_at)'));
        $this->applySchemeOnPayments($payments);

        $payments = $payments
            ->selectRaw("to_char(COALESCE(paid_at, created_at), 'YYYY-MM') as month")
            ->selectRaw('COALESCE(SUM(amount), 0) as revenue')
            ->groupBy('month')
            ->pluck('revenue', 'month');

        $payouts = Payout::query()->where('status', PayoutStatus::Paid->value);
        $this->applyDateRange($payouts, DB::raw('COALESCE(paid_at, created_at)'));
        $this->applySchemeOnPayouts($payouts);

        $payouts = $payouts
            ->selectRaw("to_char(COALESCE(paid_at, created_at), 'YYYY-MM') as month")
            ->selectRaw('COALESCE(SUM(amount), 0) as cost')
            ->groupBy('month')
            ->pluck('cost', 'month');

        $months = collect($payments->keys())->merge($payouts->keys())->unique()->sort()->values();

        return $months->map(function (string $month) use ($payments, $payouts) {
            $revenue = (float) ($payments[$month] ?? 0);
            $cost = (float) ($payouts[$month] ?? 0);

            return [
                'month' => $month,
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'margin' => round($revenue - $cost, 2),
            ];
        })->all();
    }

    /**
     * @return list<array{name: string, submitted: int, accepted: int, acceptance_rate: float}>
     */
    private function sellerPerformance(): array
    {
        $acceptedList = implode(',', array_map(
            fn (string $status) => "'{$status}'",
            self::ACCEPTED_STATUSES,
        ));

        $rows = $this->baseLeadQuery()
            ->where('leads.status', '!=', LeadStatus::Draft->value)
            ->leftJoin('companies', 'leads.seller_company_id', '=', 'companies.id')
            ->selectRaw('companies.name as name, count(*) as submitted')
            ->selectRaw("sum(case when leads.status in ({$acceptedList}) then 1 else 0 end) as accepted")
            ->groupBy('companies.name')
            ->orderByDesc('submitted')
            ->limit(10)
            ->get();

        return $rows->map(function ($row) {
            $submitted = (int) $row->submitted;
            $accepted = (int) $row->accepted;

            return [
                'name' => $row->name ? (string) $row->name : '—',
                'submitted' => $submitted,
                'accepted' => $accepted,
                'acceptance_rate' => $submitted > 0 ? round(($accepted / $submitted) * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{name: string, leads_bought: int, spent: float, avg_per_lead: float}>
     */
    private function buyerPerformance(): array
    {
        $perPurchase = DB::table('purchases')
            ->leftJoin('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->select('purchases.id', 'purchases.buyer_company_id', 'purchases.total_amount')
            ->selectRaw('COUNT(purchase_items.id) as item_count')
            ->groupBy('purchases.id', 'purchases.buyer_company_id', 'purchases.total_amount');

        if ($this->filters['date_from']) {
            $perPurchase->where(
                DB::raw('COALESCE(purchases.purchased_at, purchases.created_at)'),
                '>=',
                Carbon::parse($this->filters['date_from'])->startOfDay(),
            );
        }

        if ($this->filters['date_to']) {
            $perPurchase->where(
                DB::raw('COALESCE(purchases.purchased_at, purchases.created_at)'),
                '<=',
                Carbon::parse($this->filters['date_to'])->endOfDay(),
            );
        }

        $this->applySchemeOnPurchases($perPurchase);

        $rows = DB::query()
            ->fromSub($perPurchase, 'purchase_stats')
            ->leftJoin('companies', 'purchase_stats.buyer_company_id', '=', 'companies.id')
            ->selectRaw('companies.name as name')
            ->selectRaw('COALESCE(SUM(purchase_stats.item_count), 0) as leads_bought')
            ->selectRaw('COALESCE(SUM(purchase_stats.total_amount), 0) as spent')
            ->groupBy('companies.name')
            ->orderByDesc('leads_bought')
            ->limit(10)
            ->get();

        return $rows->map(function ($row) {
            $leadsBought = (int) $row->leads_bought;
            $spent = (float) $row->spent;

            return [
                'name' => $row->name ? (string) $row->name : '—',
                'leads_bought' => $leadsBought,
                'spent' => round($spent, 2),
                'avg_per_lead' => $leadsBought > 0 ? round($spent / $leadsBought, 2) : 0.0,
            ];
        })->values()->all();
    }
}
