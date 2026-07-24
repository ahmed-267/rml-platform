<?php

namespace App\Services\Buyer;

use App\Enums\LeadStatus;
use App\Enums\PackageStatus;
use App\Enums\PackageType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PurchaseItemType;
use App\Enums\PurchaseStatus;
use App\Models\Lead;
use App\Models\LeadPackage;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DistanceService;
use App\Services\LeadPricingService;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerPurchaseService
{
    public function __construct(
        private readonly LeadAvailabilityService $availabilityService = new LeadAvailabilityService,
        private readonly LeadPricingService $leadPricingService = new LeadPricingService,
        private readonly DistanceService $distanceService = new DistanceService,
        private readonly AuditLogService $auditLogService = new AuditLogService,
        private readonly PackageBuilderService $packageBuilderService = new PackageBuilderService,
    ) {}

    /**
     * @param  list<int>  $leadIds
     */
    public function createLeadPurchase(User $buyer, array $leadIds, PaymentMethod $method): Purchase
    {
        $companyId = $buyer->buyerProfile?->company_id;
        if (! $companyId) {
            throw ValidationException::withMessages([
                'lead_ids' => __('rml.buyer.leads.no_company'),
            ]);
        }

        $leadIds = array_values(array_unique(array_map('intval', $leadIds)));
        sort($leadIds);

        if ($leadIds === []) {
            throw ValidationException::withMessages([
                'lead_ids' => __('rml.buyer.leads.select_required'),
            ]);
        }

        return DB::transaction(function () use ($buyer, $leadIds, $method, $companyId) {
            $existing = $this->findPendingLeadPurchaseForExactLeads($buyer, $companyId, $leadIds);
            if ($existing) {
                $this->syncPendingPaymentMethod($existing, $method);

                return $existing->fresh(['payment', 'items.lead', 'items.leadPackage']) ?? $existing;
            }

            $leads = Lead::query()
                ->whereIn('id', $leadIds)
                ->lockForUpdate()
                ->get();

            if ($leads->count() !== count($leadIds)) {
                throw ValidationException::withMessages([
                    'lead_ids' => __('rml.buyer.leads.unavailable'),
                ]);
            }

            foreach ($leads as $lead) {
                if ($lead->status === LeadStatus::Sold) {
                    throw ValidationException::withMessages([
                        'lead_ids' => __('rml.buyer.leads.already_sold'),
                    ]);
                }

                if (! $this->availabilityService->isAvailable($lead)) {
                    $ownPending = $this->findOwnPendingPurchaseContainingLead($buyer, $companyId, (int) $lead->id);
                    if ($ownPending) {
                        throw ValidationException::withMessages([
                            'lead_ids' => __('rml.buyer.leads.pending_payment_exists'),
                        ]);
                    }

                    throw ValidationException::withMessages([
                        'lead_ids' => __('rml.buyer.leads.unavailable'),
                    ]);
                }
            }

            return $this->persistPurchase($buyer, $companyId, $leads, $method, null);
        });
    }

    /**
     * @param  list<int>  $leadIds
     */
    public function findPendingLeadPurchaseForExactLeads(User $buyer, int $companyId, array $leadIds): ?Purchase
    {
        $normalized = array_values(array_unique(array_map('intval', $leadIds)));
        sort($normalized);

        $candidates = Purchase::query()
            ->where('buyer_company_id', $companyId)
            ->where('buyer_user_id', $buyer->id)
            ->where('status', PurchaseStatus::Pending->value)
            ->with(['items', 'payment'])
            ->lockForUpdate()
            ->get();

        foreach ($candidates as $purchase) {
            $ids = $purchase->items
                ->pluck('lead_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($ids === $normalized) {
                return $purchase;
            }
        }

        return null;
    }

    public function findOwnPendingPurchaseContainingLead(User $buyer, int $companyId, int $leadId): ?Purchase
    {
        return Purchase::query()
            ->where('buyer_company_id', $companyId)
            ->where('buyer_user_id', $buyer->id)
            ->where('status', PurchaseStatus::Pending->value)
            ->whereHas('items', fn ($q) => $q->where('lead_id', $leadId))
            ->with(['payment', 'items'])
            ->first();
    }

    private function syncPendingPaymentMethod(Purchase $purchase, PaymentMethod $method): void
    {
        $payment = $purchase->payment;
        if (! $payment || $payment->status === PaymentStatus::Paid) {
            return;
        }

        if ($payment->method === $method) {
            return;
        }

        $payment->update([
            'method' => $method,
            'provider' => $method === PaymentMethod::Card
                ? (string) config('payments.card_provider', 'stripe')
                : 'manual_bank_transfer',
            'status' => PaymentStatus::Pending,
            'failed_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function createPackagePurchase(User $buyer, LeadPackage $package, PaymentMethod $method): Purchase
    {
        $companyId = $buyer->buyerProfile?->company_id;
        if (! $companyId) {
            throw ValidationException::withMessages([
                'package_id' => __('rml.buyer.leads.no_company'),
            ]);
        }

        if ($package->status !== PackageStatus::Available) {
            throw ValidationException::withMessages([
                'package_id' => __('rml.buyer.packages.unavailable'),
            ]);
        }

        return DB::transaction(function () use ($buyer, $package, $method, $companyId) {
            $package = LeadPackage::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            $leads = $package->leads()->lockForUpdate()->get();

            if ($leads->isEmpty()) {
                throw ValidationException::withMessages([
                    'package_id' => __('rml.buyer.packages.no_leads'),
                ]);
            }

            foreach ($leads as $lead) {
                if (! $this->availabilityService->isAvailable($lead)) {
                    throw ValidationException::withMessages([
                        'package_id' => __('rml.buyer.leads.unavailable'),
                    ]);
                }
            }

            $purchase = $this->persistPurchase($buyer, $companyId, $leads, $method, $package);
            $package->update(['status' => PackageStatus::Locked]);

            return $purchase;
        });
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function createCustomPackagePurchase(User $buyer, array $criteria, PaymentMethod $method): Purchase
    {
        $preview = $this->packageBuilderService->preview($buyer, $criteria);

        if (! ($preview['enough'] ?? false) || empty($preview['lead_ids'])) {
            throw ValidationException::withMessages([
                'lead_count' => __('rml.buyer.packages.not_enough_leads'),
            ]);
        }

        return DB::transaction(function () use ($buyer, $criteria, $method, $preview) {
            $companyId = $buyer->buyerProfile?->company_id;
            $leads = Lead::query()->whereIn('id', $preview['lead_ids'])->lockForUpdate()->get();

            foreach ($leads as $lead) {
                if (! $this->availabilityService->isAvailable($lead)) {
                    throw ValidationException::withMessages([
                        'lead_count' => __('rml.buyer.leads.unavailable'),
                    ]);
                }
            }

            $pricing = $this->sumLeads($leads);
            $package = LeadPackage::query()->create([
                'package_reference' => ReferenceGenerator::package(),
                'name' => $criteria['name'] ?? 'Custom Package',
                'package_type' => PackageType::Custom,
                'scheme_id' => $criteria['scheme_id'] ?? $leads->first()?->scheme_id,
                'requested_leads_count' => (int) ($criteria['lead_count'] ?? $leads->count()),
                'size_range_min' => $criteria['min_size'] ?? null,
                'size_range_max' => $criteria['max_size'] ?? null,
                'distance_range_min' => $criteria['min_distance'] ?? null,
                'distance_range_max' => $criteria['max_distance'] ?? null,
                'zone_mix' => $preview['zone_mix'] ?? null,
                'avg_price_per_m2' => $pricing['avg_price_per_m2'],
                'estimated_total' => $pricing['total_amount'],
                'status' => PackageStatus::Locked,
                'created_by_user_id' => $buyer->id,
            ]);

            $package->leads()->sync($leads->pluck('id')->all());

            return $this->persistPurchase($buyer, (int) $companyId, $leads, $method, $package);
        });
    }

    public function createMixedZonePurchase(User $buyer, PaymentMethod $method): Purchase
    {
        $preview = $this->packageBuilderService->mixedZoneDefault($buyer);

        if (! ($preview['enough'] ?? false) || empty($preview['lead_ids'])) {
            throw ValidationException::withMessages([
                'package' => __('rml.buyer.packages.not_enough_leads'),
            ]);
        }

        return $this->createCustomPackagePurchase($buyer, [
            'name' => 'Mixed Zone Pack',
            'lead_count' => count($preview['lead_ids']),
            'scheme_id' => $preview['scheme_id'] ?? null,
            'zone_codes' => ['D1', 'D2', 'E1', 'E2'],
            'lead_ids' => $preview['lead_ids'],
        ], $method);
    }

    public function cancelPendingPurchase(User $buyer, Purchase $purchase): void
    {
        $companyId = $buyer->buyerProfile?->company_id;

        if ($companyId === null || $purchase->buyer_company_id !== $companyId) {
            abort(403);
        }

        if ($purchase->status !== PurchaseStatus::Pending) {
            throw ValidationException::withMessages([
                'purchase' => __('rml.buyer.payments.cannot_cancel'),
            ]);
        }

        DB::transaction(function () use ($buyer, $purchase) {
            $purchase->load(['payment', 'items.leadPackage']);

            $purchase->update(['status' => PurchaseStatus::Cancelled]);

            if ($purchase->payment && in_array($purchase->payment->status, [
                PaymentStatus::Pending,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
            ], true)) {
                $purchase->payment->update([
                    'status' => PaymentStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);
            }

            foreach ($purchase->items as $item) {
                if ($item->leadPackage && $item->leadPackage->status === PackageStatus::Locked) {
                    $item->leadPackage->update(['status' => PackageStatus::Available]);
                }
            }

            $this->auditLogService->log(
                'purchase.cancelled',
                $purchase,
                ['status' => PurchaseStatus::Pending->value],
                ['status' => PurchaseStatus::Cancelled->value],
                $buyer,
            );
        });
    }

    /**
     * @param  Collection<int, Lead>  $leads
     */
    private function persistPurchase(
        User $buyer,
        int $companyId,
        Collection $leads,
        PaymentMethod $method,
        ?LeadPackage $package,
    ): Purchase {
        $totals = $this->sumLeads($leads);

        $payment = Payment::query()->create([
            'payment_reference' => ReferenceGenerator::payment(),
            'payer_user_id' => $buyer->id,
            'payer_company_id' => $companyId,
            'type' => PaymentType::BuyerPayment,
            'method' => $method,
            'provider' => $method === PaymentMethod::Card
                ? (string) config('payments.card_provider', 'stripe')
                : 'manual_bank_transfer',
            'status' => PaymentStatus::Pending,
            'amount' => $totals['total_amount'],
            'currency' => 'EUR',
            'due_date' => now()->addDays(7)->toDateString(),
            'metadata' => [
                'phase' => 8,
            ],
        ]);

        $purchase = Purchase::query()->create([
            'purchase_reference' => ReferenceGenerator::purchase(),
            'buyer_company_id' => $companyId,
            'buyer_user_id' => $buyer->id,
            'status' => PurchaseStatus::Pending,
            'total_amount' => $totals['total_amount'],
            'total_size_m2' => $totals['total_size_m2'],
            'payment_id' => $payment->id,
            'purchased_at' => null,
        ]);

        foreach ($leads as $lead) {
            $line = $this->priceLead($lead);
            PurchaseItem::query()->create([
                'purchase_id' => $purchase->id,
                'lead_id' => $lead->id,
                'lead_package_id' => $package?->id,
                'item_type' => $package ? PurchaseItemType::Package : PurchaseItemType::Lead,
                'quantity' => 1,
                'unit_price' => $line['unit_price'],
                'total_price' => $line['total_price'],
            ]);
        }

        $this->auditLogService->log(
            'purchase.created',
            $purchase,
            null,
            [
                'status' => PurchaseStatus::Pending->value,
                'payment_id' => $payment->id,
                'lead_ids' => $leads->pluck('id')->all(),
                'package_id' => $package?->id,
                'amount' => $totals['total_amount'],
            ],
            $buyer,
        );

        return $purchase->fresh(['payment', 'items.lead', 'items.leadPackage']);
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return array{total_amount: float, total_size_m2: float, avg_price_per_m2: float|null}
     */
    private function sumLeads(Collection $leads): array
    {
        $total = 0.0;
        $size = 0.0;

        foreach ($leads as $lead) {
            $line = $this->priceLead($lead);
            $total += $line['total_price'];
            $size += $line['size_m2'];
        }

        return [
            'total_amount' => round($total, 2),
            'total_size_m2' => round($size, 2),
            'avg_price_per_m2' => $size > 0 ? round($total / $size, 2) : null,
        ];
    }

    /**
     * @return array{unit_price: float, total_price: float, size_m2: float, price_per_m2: float|null}
     */
    private function priceLead(Lead $lead): array
    {
        $pricing = $this->leadPricingService->calculate($lead);
        $size = $lead->size_m2 !== null ? (float) $lead->size_m2 : 0.0;
        $total = $lead->selling_price !== null
            ? (float) $lead->selling_price
            : (float) ($pricing['selling_price'] ?? 0);
        $perM2 = $pricing['price_per_m2'];

        return [
            'unit_price' => $perM2 ?? ($size > 0 ? round($total / $size, 2) : $total),
            'total_price' => round($total, 2),
            'size_m2' => $size,
            'price_per_m2' => $perM2,
        ];
    }
}
