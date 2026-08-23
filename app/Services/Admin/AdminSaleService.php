<?php

namespace App\Services\Admin;

use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\LeadPackage;
use App\Models\Purchase;
use App\Models\User;

/**
 * @deprecated Use AdminBuyerPurchaseService — kept as a thin alias for existing bindings/tests.
 */
class AdminSaleService
{
    public function __construct(
        private readonly AdminBuyerPurchaseService $purchaseService = new AdminBuyerPurchaseService,
    ) {}

    /**
     * @param  list<int>  $leadIds
     */
    public function sellLeads(
        User $admin,
        Company $buyerCompany,
        array $leadIds,
        PaymentMethod $method,
        ?User $buyerUser = null,
        ?float $overrideTotal = null,
    ): Purchase {
        $purchase = $this->purchaseService->buyLeads(
            $admin,
            $buyerCompany,
            $leadIds,
            $method,
            $buyerUser,
        );

        if ($overrideTotal !== null && $purchase->payment) {
            $purchase->payment->update(['amount' => round($overrideTotal, 2)]);
            $purchase->update(['total_amount' => round($overrideTotal, 2)]);
        }

        return $purchase->fresh(['payment', 'items.lead', 'buyerCompany', 'buyerUser']);
    }

    public function sellPackage(
        User $admin,
        Company $buyerCompany,
        LeadPackage $package,
        PaymentMethod $method,
        ?User $buyerUser = null,
        ?float $overrideTotal = null,
    ): Purchase {
        $purchase = $this->purchaseService->buyPackage(
            $admin,
            $buyerCompany,
            $package,
            $method,
            $buyerUser,
        );

        if ($overrideTotal !== null && $purchase->payment) {
            $purchase->payment->update(['amount' => round($overrideTotal, 2)]);
            $purchase->update(['total_amount' => round($overrideTotal, 2)]);
        }

        return $purchase->fresh(['payment', 'items.lead', 'items.leadPackage', 'buyerCompany', 'buyerUser']);
    }
}
