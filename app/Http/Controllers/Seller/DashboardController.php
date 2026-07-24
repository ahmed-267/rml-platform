<?php

namespace App\Http\Controllers\Seller;

use App\Enums\CommissionStatus;
use App\Enums\LeadStatus;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Payout;
use App\Services\Seller\SellerLeadScope;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $leadsQuery = SellerLeadScope::forUser($user);

        $statusCounts = (clone $leadsQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $companyId = $user->sellerProfile?->company_id;

        $commissionsQuery = Commission::query()->where('seller_user_id', $user->id);
        $payoutsQuery = Payout::query()->where('seller_user_id', $user->id);

        if ($companyId && $user->can(Permissions::VIEW_COMPANY_LEADS)) {
            $commissionsQuery = Commission::query()->where(function ($q) use ($user, $companyId) {
                $q->where('seller_user_id', $user->id)
                    ->orWhere('seller_company_id', $companyId);
            });
            $payoutsQuery = Payout::query()->where(function ($q) use ($user, $companyId) {
                $q->where('seller_user_id', $user->id)
                    ->orWhere('seller_company_id', $companyId);
            });
        }

        return Inertia::render('Seller/Dashboard', [
            'kpis' => [
                'total_leads' => (clone $leadsQuery)->count(),
                'drafts' => (int) ($statusCounts[LeadStatus::Draft->value] ?? 0),
                'pending_evidence' => (int) ($statusCounts[LeadStatus::PendingEvidence->value] ?? 0),
                'pending_validation' => (int) ($statusCounts[LeadStatus::PendingValidation->value] ?? 0),
                'needs_more_information' => (int) ($statusCounts[LeadStatus::NeedsMoreInformation->value] ?? 0),
                'accepted' => (int) ($statusCounts[LeadStatus::Accepted->value] ?? 0),
                'rejected' => (int) ($statusCounts[LeadStatus::Rejected->value] ?? 0),
                'sold' => (int) ($statusCounts[LeadStatus::Sold->value] ?? 0),
                'commissions_due' => (float) (clone $commissionsQuery)
                    ->where('status', CommissionStatus::Due)
                    ->sum('commission_amount'),
                'commissions_paid' => (float) (clone $commissionsQuery)
                    ->where('status', CommissionStatus::Paid)
                    ->sum('commission_amount'),
                'payouts_pending' => (float) (clone $payoutsQuery)
                    ->where('status', PayoutStatus::Pending)
                    ->sum('amount'),
                'payouts_paid' => (float) (clone $payoutsQuery)
                    ->where('status', PayoutStatus::Paid)
                    ->sum('amount'),
            ],
            'recent_leads' => (clone $leadsQuery)
                ->with(['scheme:id,name', 'zone:id,code'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($lead) => [
                    'id' => $lead->id,
                    'lead_reference' => $lead->lead_reference,
                    'status' => $lead->status?->value,
                    'scheme_name' => $lead->scheme?->name,
                    'zone_code' => $lead->zone?->code,
                    'customer_name' => trim($lead->customer_first_name.' '.$lead->customer_last_name),
                    'created_at' => $lead->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
