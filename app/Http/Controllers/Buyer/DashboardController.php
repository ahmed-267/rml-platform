<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Purchase;
use App\Services\Buyer\LeadAvailabilityService;
use App\Support\BuyerLeadPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, LeadAvailabilityService $availabilityService): Response
    {
        $user = $request->user();
        $companyId = $user->buyerProfile?->company_id;

        $availableCount = $availabilityService->availableQuery($user)->count();

        $purchasesQuery = Purchase::query()->where('buyer_company_id', $companyId);
        $paymentsQuery = Payment::query()->where('payer_company_id', $companyId);

        $leadsBought = (clone $purchasesQuery)
            ->where('status', PurchaseStatus::Paid)
            ->withCount('items')
            ->get()
            ->sum('items_count');

        $pendingPayments = (clone $paymentsQuery)
            ->where('status', PaymentStatus::Pending)
            ->get();

        $pendingToBuy = (clone $purchasesQuery)
            ->where('status', PurchaseStatus::Pending)
            ->withCount('items')
            ->get()
            ->sum('items_count');

        $totalSpent = (float) (clone $paymentsQuery)
            ->where('status', PaymentStatus::Paid)
            ->sum('amount');

        $recentPurchases = (clone $purchasesQuery)
            ->with(['items.lead.scheme', 'items.lead.zone', 'items.leadPackage', 'payment'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (Purchase $purchase) {
                $firstItem = $purchase->items->first();
                $lead = $firstItem?->lead;
                $package = $firstItem?->leadPackage;

                return [
                    'id' => $purchase->id,
                    'purchase_reference' => $purchase->purchase_reference,
                    'display_reference' => $package?->package_reference
                        ?? $lead?->lead_reference
                        ?? $purchase->purchase_reference,
                    'scheme' => $lead?->scheme?->name,
                    'zone' => $package
                        ? collect($package->zone_mix ?? [])->keys()->implode(', ')
                        : $lead?->zone?->code,
                    'purchased_at' => $purchase->purchased_at?->toIso8601String()
                        ?? $purchase->created_at?->toIso8601String(),
                    'status' => $purchase->status?->value,
                    'payment_status' => $purchase->payment?->status?->value,
                ];
            });

        $recommended = $availabilityService
            ->findAvailableForBuyer($user)
            ->latest('id')
            ->limit(5)
            ->get();

        return Inertia::render('Buyer/Dashboard', [
            'kpis' => [
                'available_leads' => $availableCount,
                'leads_bought' => (int) $leadsBought,
                'pending_payments' => $pendingPayments->count(),
                'pending_payments_amount' => (float) $pendingPayments->sum('amount'),
                'pending_to_buy' => (int) $pendingToBuy,
                'total_spent' => $totalSpent,
            ],
            'recent_purchases' => $recentPurchases,
            'recommended_leads' => BuyerLeadPresenter::marketplaceCollection($recommended, $user),
        ]);
    }
}
