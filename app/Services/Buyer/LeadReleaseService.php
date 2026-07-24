<?php

namespace App\Services\Buyer;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\Lead;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Collection;

class LeadReleaseService
{
    public function canReleaseDetails(User $buyer, Lead $lead): bool
    {
        $companyId = $buyer->buyerProfile?->company_id;

        if (! $companyId) {
            return false;
        }

        return Purchase::query()
            ->where('buyer_company_id', $companyId)
            ->where('status', PurchaseStatus::Paid)
            ->where(function ($query) {
                $query->whereHas('payment', fn ($q) => $q->where('status', PaymentStatus::Paid))
                    ->orWhere('status', PurchaseStatus::Paid);
            })
            ->whereHas('items', fn ($q) => $q->where('lead_id', $lead->id))
            ->exists();
    }

    /**
     * @return Collection<int, Lead>
     */
    public function releasedLeadsForPurchase(Purchase $purchase): Collection
    {
        $purchase->loadMissing(['items.lead', 'payment']);

        if (! $purchase->isPaid()) {
            return collect();
        }

        if ($purchase->payment && $purchase->payment->status !== PaymentStatus::Paid) {
            return collect();
        }

        return $purchase->items
            ->map(fn ($item) => $item->lead)
            ->filter()
            ->values();
    }

    public function purchaseIsReleased(Purchase $purchase): bool
    {
        if (! $purchase->isPaid()) {
            return false;
        }

        $purchase->loadMissing('payment');

        return $purchase->payment === null
            || $purchase->payment->status === PaymentStatus::Paid;
    }
}
