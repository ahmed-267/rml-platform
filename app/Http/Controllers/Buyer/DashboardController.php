<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\Buyer\LeadAvailabilityService;
use App\Support\BuyerLeadPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, LeadAvailabilityService $availabilityService): Response|JsonResponse
    {
        $payload = $this->payload($request, $availabilityService);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return Inertia::render('Buyer/Dashboard', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, LeadAvailabilityService $availabilityService): array
    {
        $user = $request->user();
        $companyId = $user->buyerProfile?->company_id;

        $kpis = Cache::remember("buyer.dashboard.kpis.{$companyId}", 30, function () use ($companyId, $user, $availabilityService) {
            $leadsBought = (int) PurchaseItem::query()
                ->whereHas('purchase', fn ($q) => $q
                    ->where('buyer_company_id', $companyId)
                    ->where('status', PurchaseStatus::Paid))
                ->count();

            $pendingToBuy = (int) PurchaseItem::query()
                ->whereHas('purchase', fn ($q) => $q
                    ->where('buyer_company_id', $companyId)
                    ->where('status', PurchaseStatus::Pending))
                ->count();

            $pendingPaymentsQuery = Payment::query()
                ->where('payer_company_id', $companyId)
                ->where('status', PaymentStatus::Pending);

            return [
                'available_leads' => $availabilityService->availableQuery($user)->count(),
                'leads_bought' => $leadsBought,
                'pending_payments' => (clone $pendingPaymentsQuery)->count(),
                'pending_payments_amount' => (float) (clone $pendingPaymentsQuery)->sum('amount'),
                'pending_to_buy' => $pendingToBuy,
                'total_spent' => (float) Payment::query()
                    ->where('payer_company_id', $companyId)
                    ->where('status', PaymentStatus::Paid)
                    ->sum('amount'),
            ];
        });

        $recentPurchases = Purchase::query()
            ->where('buyer_company_id', $companyId)
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

        return [
            'kpis' => $kpis,
            'recent_purchases' => $recentPurchases,
            'recommended_leads' => BuyerLeadPresenter::marketplaceCollection($recommended, $user),
        ];
    }
}
