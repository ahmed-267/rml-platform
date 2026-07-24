<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Purchase;
use App\Models\User;
use App\Support\Permissions;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW_LEADS)
            || $user->can(Permissions::VIEW_OWN_LEADS)
            || $user->can(Permissions::VIEW_COMPANY_LEADS)
            || $user->can(Permissions::BUY_LEADS)
            || $user->can(Permissions::VIEW_PURCHASED_LEADS);
    }

    public function view(User $user, Lead $lead): bool
    {
        if ($user->can(Permissions::VIEW_LEADS)) {
            return true;
        }

        if ($this->isSellerSide($user)) {
            return $this->sellerCanViewLead($user, $lead);
        }

        if ($this->isBuyerSide($user)) {
            return in_array($lead->status->value, ['accepted', 'priced', 'listed'], true)
                || $this->buyerHasPaidPurchaseForLead($user, $lead);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::SUBMIT_LEADS);
    }

    public function update(User $user, Lead $lead): bool
    {
        if ($user->can(Permissions::ACCEPT_REJECT_LEADS) || $user->can(Permissions::AUDIT_LEADS)) {
            return true;
        }

        return $this->isSellerSide($user) && $this->sellerCanViewLead($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }

    /**
     * Sellers must never see buyer information related to a lead.
     */
    public function viewBuyerInfo(User $user, Lead $lead): bool
    {
        if ($this->isSellerSide($user)) {
            return false;
        }

        return $user->can(Permissions::VIEW_LEADS)
            || $user->can(Permissions::MANAGE_BUYERS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    /**
     * Buyers must never see seller details.
     */
    public function viewSellerInfo(User $user, Lead $lead): bool
    {
        if ($this->isBuyerSide($user)) {
            return false;
        }

        return $user->can(Permissions::VIEW_LEADS)
            || $user->can(Permissions::MANAGE_SELLERS)
            || $this->sellerCanViewLead($user, $lead);
    }

    /**
     * Customer PII is hidden from buyers until a paid purchase exists.
     */
    public function viewCustomerDetails(User $user, Lead $lead): bool
    {
        if ($user->can(Permissions::VIEW_CUSTOMER_DETAILS) || $user->hasRole(UserRole::SuperAdmin->value)) {
            return true;
        }

        if ($this->isSellerSide($user)) {
            return $this->sellerCanViewLead($user, $lead);
        }

        if ($this->isBuyerSide($user)) {
            return $this->buyerHasPaidPurchaseForLead($user, $lead);
        }

        return false;
    }

    public function viewInternalPricing(User $user, Lead $lead): bool
    {
        if ($this->isSellerSide($user)) {
            return false;
        }

        return $user->can(Permissions::VIEW_INTERNAL_PRICING)
            || $user->can(Permissions::OVERRIDE_PRICING)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    private function sellerCanViewLead(User $user, Lead $lead): bool
    {
        if ($user->can(Permissions::VIEW_COMPANY_LEADS)) {
            $companyId = $user->sellerProfile?->company_id;

            return $companyId !== null && $lead->seller_company_id === $companyId;
        }

        if ($user->can(Permissions::VIEW_OWN_LEADS)) {
            return $lead->submitted_by_user_id === $user->id;
        }

        return false;
    }

    private function buyerHasPaidPurchaseForLead(User $user, Lead $lead): bool
    {
        $companyId = $user->buyerProfile?->company_id;

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

    private function isSellerSide(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SellerCompanyAdmin->value,
            UserRole::SellerStaff->value,
            UserRole::IndividualSellerAgent->value,
        ]);
    }

    private function isBuyerSide(User $user): bool
    {
        return $user->hasRole(UserRole::BuyerAdmin->value);
    }
}
