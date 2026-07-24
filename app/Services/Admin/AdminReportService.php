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
use App\Models\User;
use App\Support\LeadStatusPresentation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminReportService
{
    private const ACCEPTED_STATUSES = [
        LeadStatus::Accepted->value,
        LeadStatus::Priced->value,
        LeadStatus::Listed->value,
        LeadStatus::Sold->value,
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
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
                'seller_performance' => $this->sellerPerformance(),
                'buyer_performance' => $this->buyerPerformance(),
            ],
            'seller_performance' => $this->sellerPerformance(),
            'buyer_performance' => $this->buyerPerformance(),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function summary(): array
    {
        $reviewedCount = Lead::query()
            ->whereIn('status', [
                ...self::ACCEPTED_STATUSES,
                LeadStatus::Rejected->value,
            ])
            ->count();

        $acceptedCount = Lead::query()
            ->whereIn('status', self::ACCEPTED_STATUSES)
            ->count();

        return [
            'total_leads_submitted' => Lead::query()->where('status', '!=', LeadStatus::Draft->value)->count(),
            'leads_sold' => Lead::query()->where('status', LeadStatus::Sold->value)->count(),
            'acceptance_rate' => $reviewedCount > 0 ? round(($acceptedCount / $reviewedCount) * 100, 1) : 0.0,
            'buyer_revenue_paid' => (float) Payment::query()
                ->where('type', PaymentType::BuyerPayment->value)
                ->where('status', PaymentStatus::Paid->value)
                ->sum('amount'),
            'seller_payouts_paid' => (float) Payout::query()
                ->where('status', PayoutStatus::Paid->value)
                ->sum('amount'),
            'total_margin' => (float) Lead::query()
                ->where('status', LeadStatus::Sold->value)
                ->selectRaw('COALESCE(SUM(COALESCE(expected_margin, selling_price - buying_price)), 0) as total')
                ->value('total'),
            'commissions_due' => (float) Commission::query()->where('status', 'due')->sum('commission_amount'),
            'commissions_paid' => (float) Commission::query()->where('status', 'paid')->sum('commission_amount'),
            'total_purchases' => Purchase::query()->count(),
            'approved_sellers' => User::query()
                ->where('approval_status', ApprovalStatus::Approved->value)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                    UserRole::SellerCompanyAdmin->value,
                    UserRole::IndividualSellerAgent->value,
                ]))
                ->count(),
            'approved_buyers' => User::query()
                ->where('approval_status', ApprovalStatus::Approved->value)
                ->whereHas('roles', fn ($q) => $q->where('name', UserRole::BuyerAdmin->value))
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function leadPipeline(): array
    {
        return LeadStatusPresentation::groupCounts(
            Lead::query()
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
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                UserRole::SellerCompanyAdmin->value,
                UserRole::IndividualSellerAgent->value,
                UserRole::BuyerAdmin->value,
            ]))
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
        return $this->groupCountByMonth(
            Lead::query()
                ->where('status', LeadStatus::Sold->value)
                ->whereNotNull('sold_at')
                ->get(['sold_at']),
            'sold_at',
        );
    }

    /**
     * @return list<array{month: string, submitted: int, accepted: int, rejected: int}>
     */
    private function leadVolumeByMonth(): array
    {
        $leads = Lead::query()
            ->where('status', '!=', LeadStatus::Draft->value)
            ->get(['created_at', 'accepted_at', 'rejected_at', 'status']);

        /** @var array<string, array{month: string, submitted: int, accepted: int, rejected: int}> $buckets */
        $buckets = [];

        foreach ($leads as $lead) {
            $createdMonth = $lead->created_at?->format('Y-m');
            if ($createdMonth) {
                $buckets[$createdMonth] ??= $this->emptyVolumeBucket($createdMonth);
                $buckets[$createdMonth]['submitted']++;
            }

            if ($lead->accepted_at) {
                $month = $lead->accepted_at->format('Y-m');
                $buckets[$month] ??= $this->emptyVolumeBucket($month);
                $buckets[$month]['accepted']++;
            } elseif (in_array($lead->status?->value ?? $lead->status, self::ACCEPTED_STATUSES, true) && $createdMonth) {
                $buckets[$createdMonth]['accepted']++;
            }

            if ($lead->rejected_at) {
                $month = $lead->rejected_at->format('Y-m');
                $buckets[$month] ??= $this->emptyVolumeBucket($month);
                $buckets[$month]['rejected']++;
            } elseif (($lead->status?->value ?? $lead->status) === LeadStatus::Rejected->value && $createdMonth) {
                $buckets[$createdMonth]['rejected']++;
            }
        }

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * @return array{month: string, submitted: int, accepted: int, rejected: int}
     */
    private function emptyVolumeBucket(string $month): array
    {
        return [
            'month' => $month,
            'submitted' => 0,
            'accepted' => 0,
            'rejected' => 0,
        ];
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function leadsByZone(): array
    {
        return Lead::query()
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
        return Lead::query()
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
        $soldLeads = Lead::query()
            ->where('status', LeadStatus::Sold->value)
            ->get(['sold_at', 'created_at', 'selling_price', 'buying_price', 'expected_margin']);

        /** @var array<string, array{month: string, revenue: float, cost: float, margin: float}> $buckets */
        $buckets = [];

        foreach ($soldLeads as $lead) {
            $month = ($lead->sold_at ?? $lead->created_at)?->format('Y-m');
            if (! $month) {
                continue;
            }

            $revenue = (float) ($lead->selling_price ?? 0);
            $cost = (float) ($lead->buying_price ?? 0);
            $margin = $lead->expected_margin !== null
                ? (float) $lead->expected_margin
                : $revenue - $cost;

            $buckets[$month] ??= [
                'month' => $month,
                'revenue' => 0.0,
                'cost' => 0.0,
                'margin' => 0.0,
            ];
            $buckets[$month]['revenue'] += $revenue;
            $buckets[$month]['cost'] += $cost;
            $buckets[$month]['margin'] += $margin;
        }

        // Include paid buyer payments / seller payouts by paid_at when sold leads are sparse
        if ($buckets === []) {
            $payments = Payment::query()
                ->where('type', PaymentType::BuyerPayment->value)
                ->where('status', PaymentStatus::Paid->value)
                ->get(['amount', 'paid_at', 'created_at']);

            foreach ($payments as $payment) {
                $month = ($payment->paid_at ?? $payment->created_at)?->format('Y-m');
                if (! $month) {
                    continue;
                }
                $buckets[$month] ??= [
                    'month' => $month,
                    'revenue' => 0.0,
                    'cost' => 0.0,
                    'margin' => 0.0,
                ];
                $buckets[$month]['revenue'] += (float) $payment->amount;
            }

            $payouts = Payout::query()
                ->where('status', PayoutStatus::Paid->value)
                ->get(['amount', 'paid_at', 'created_at']);

            foreach ($payouts as $payout) {
                $month = ($payout->paid_at ?? $payout->created_at)?->format('Y-m');
                if (! $month) {
                    continue;
                }
                $buckets[$month] ??= [
                    'month' => $month,
                    'revenue' => 0.0,
                    'cost' => 0.0,
                    'margin' => 0.0,
                ];
                $buckets[$month]['cost'] += (float) $payout->amount;
            }

            foreach ($buckets as &$bucket) {
                $bucket['margin'] = $bucket['revenue'] - $bucket['cost'];
            }
            unset($bucket);
        }

        ksort($buckets);

        return array_values(array_map(fn (array $row) => [
            'month' => $row['month'],
            'revenue' => round($row['revenue'], 2),
            'cost' => round($row['cost'], 2),
            'margin' => round($row['margin'], 2),
        ], $buckets));
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

        $rows = Lead::query()
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
        $purchases = Purchase::query()
            ->with(['buyerCompany:id,name', 'items:id,purchase_id,lead_id'])
            ->get();

        /** @var Collection<string, array{name: string, leads_bought: int, spent: float}> $grouped */
        $grouped = collect();

        foreach ($purchases as $purchase) {
            $name = $purchase->buyerCompany?->name ?? '—';
            $current = $grouped->get($name, [
                'name' => $name,
                'leads_bought' => 0,
                'spent' => 0.0,
            ]);
            $current['leads_bought'] += $purchase->items->count();
            $current['spent'] += (float) $purchase->total_amount;
            $grouped->put($name, $current);
        }

        return $grouped
            ->map(fn (array $row) => [
                'name' => $row['name'],
                'leads_bought' => $row['leads_bought'],
                'spent' => round($row['spent'], 2),
                'avg_per_lead' => $row['leads_bought'] > 0
                    ? round($row['spent'] / $row['leads_bought'], 2)
                    : 0.0,
            ])
            ->sortByDesc('leads_bought')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, int>
     */
    private function groupCountByMonth(Collection $rows, string $dateField): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $date = $row->{$dateField} ?? null;
            if (! $date) {
                continue;
            }
            $month = $date->format('Y-m');
            $counts[$month] = ($counts[$month] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }
}
