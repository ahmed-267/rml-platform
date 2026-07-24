<?php

namespace App\Services;

use App\Enums\CommissionAppliesTo;
use App\Enums\CommissionStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Purchase;
use App\Support\ReferenceGenerator;

class CommissionService
{
    /**
     * @return array{percentage: float, base_amount: float, commission_amount: float}
     */
    public function calculate(float $baseAmount, float $percentage): array
    {
        $commissionAmount = round($baseAmount * ($percentage / 100), 2);

        return [
            'percentage' => $percentage,
            'base_amount' => round($baseAmount, 2),
            'commission_amount' => $commissionAmount,
        ];
    }

    public function resolvePercentage(CommissionAppliesTo $appliesTo, ?float $override = null): float
    {
        if ($override !== null) {
            return $override;
        }

        $rule = CommissionRule::query()
            ->where('applies_to', $appliesTo->value)
            ->where('active', true)
            ->orderByDesc('id')
            ->first();

        return $rule ? (float) $rule->percentage : 0.0;
    }

    /**
     * Commission becomes due only when the lead is sold and buyer payment is confirmed.
     */
    public function canBecomeDue(Lead $lead, ?Purchase $purchase = null, ?Payment $payment = null): bool
    {
        if ($lead->status !== LeadStatus::Sold) {
            return false;
        }

        if ($payment !== null) {
            return $payment->status === PaymentStatus::Paid;
        }

        if ($purchase !== null) {
            if ($purchase->status !== PurchaseStatus::Paid) {
                return false;
            }

            $purchase->loadMissing('payment');

            return $purchase->payment?->status === PaymentStatus::Paid
                || $purchase->status === PurchaseStatus::Paid;
        }

        $paidPurchase = Purchase::query()
            ->where('status', PurchaseStatus::Paid)
            ->whereHas('items', fn ($q) => $q->where('lead_id', $lead->id))
            ->with('payment')
            ->first();

        return $paidPurchase !== null;
    }

    public function markDueIfEligible(Commission $commission, Lead $lead, ?Purchase $purchase = null): Commission
    {
        if (! $this->canBecomeDue($lead, $purchase)) {
            return $commission;
        }

        if ($commission->status === CommissionStatus::Pending) {
            $commission->status = CommissionStatus::Due;
            $commission->due_at = now();
            $commission->save();
        }

        return $commission->refresh();
    }

    public function createForLead(
        Lead $lead,
        int $sellerUserId,
        CommissionAppliesTo $appliesTo,
        ?float $percentageOverride = null,
        ?int $sellerCompanyId = null,
    ): Commission {
        $baseAmount = (float) ($lead->selling_price ?? $lead->buying_price ?? 0);
        $percentage = $this->resolvePercentage($appliesTo, $percentageOverride);
        $calc = $this->calculate($baseAmount, $percentage);

        return Commission::query()->create([
            'commission_reference' => ReferenceGenerator::commission(),
            'lead_id' => $lead->id,
            'seller_user_id' => $sellerUserId,
            'seller_company_id' => $sellerCompanyId ?? $lead->seller_company_id,
            'percentage' => $calc['percentage'],
            'base_amount' => $calc['base_amount'],
            'commission_amount' => $calc['commission_amount'],
            'status' => CommissionStatus::Pending,
        ]);
    }
}
