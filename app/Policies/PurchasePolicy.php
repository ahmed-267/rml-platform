<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Purchase;
use App\Models\User;
use App\Support\Permissions;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::BUY_LEADS)
            || $user->can(Permissions::VIEW_PURCHASED_LEADS)
            || $user->can(Permissions::MANAGE_PAYMENTS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        if ($user->can(Permissions::MANAGE_PAYMENTS) || $user->hasRole(UserRole::SuperAdmin->value)) {
            return true;
        }

        if ($user->hasRole(UserRole::BuyerAdmin->value)) {
            return $purchase->buyer_user_id === $user->id
                || $purchase->buyer_company_id === $user->buyerProfile?->company_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::BUY_LEADS);
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $user->can(Permissions::MANAGE_PAYMENTS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function viewSellerDetails(User $user, Purchase $purchase): bool
    {
        if ($user->hasRole(UserRole::BuyerAdmin->value)) {
            return false;
        }

        return $user->can(Permissions::MANAGE_SELLERS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }
}
