<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use App\Support\Permissions;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::MANAGE_PAYMENTS)
            || $user->can(Permissions::BUY_LEADS)
            || $user->can(Permissions::VIEW_OWN_COMMISSIONS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->can(Permissions::MANAGE_PAYMENTS) || $user->hasRole(UserRole::SuperAdmin->value)) {
            return true;
        }

        if ($payment->payer_user_id === $user->id) {
            return true;
        }

        $buyerCompanyId = $user->buyerProfile?->company_id;
        if ($buyerCompanyId && $payment->payer_company_id === $buyerCompanyId) {
            return true;
        }

        $sellerCompanyId = $user->sellerProfile?->company_id;
        if ($sellerCompanyId && $payment->payer_company_id === $sellerCompanyId) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::BUY_LEADS)
            || $user->can(Permissions::MANAGE_PAYMENTS);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->can(Permissions::EDIT_PAYMENTS)
            || $user->can(Permissions::MANAGE_PAYMENTS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
