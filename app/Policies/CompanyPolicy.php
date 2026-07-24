<?php

namespace App\Policies;

use App\Enums\CompanyType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Support\Permissions;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::MANAGE_SELLERS)
            || $user->can(Permissions::MANAGE_BUYERS)
            || $user->can(Permissions::MANAGE_USERS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function view(User $user, Company $company): bool
    {
        if ($this->viewAny($user)) {
            return true;
        }

        if ($company->type === CompanyType::Seller) {
            return $user->sellerProfile?->company_id === $company->id;
        }

        if ($company->type === CompanyType::Buyer) {
            return $user->buyerProfile?->company_id === $company->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_SELLERS)
            || $user->can(Permissions::MANAGE_BUYERS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }

    /**
     * Sellers cannot inspect buyer companies.
     */
    public function viewBuyerCompany(User $user, Company $company): bool
    {
        if ($company->type !== CompanyType::Buyer) {
            return false;
        }

        if ($user->hasAnyRole([
            UserRole::SellerCompanyAdmin->value,
            UserRole::SellerStaff->value,
            UserRole::IndividualSellerAgent->value,
        ])) {
            return false;
        }

        return $this->view($user, $company);
    }

    /**
     * Buyers cannot inspect seller companies.
     */
    public function viewSellerCompany(User $user, Company $company): bool
    {
        if ($company->type !== CompanyType::Seller) {
            return false;
        }

        if ($user->hasRole(UserRole::BuyerAdmin->value)) {
            return false;
        }

        return $this->view($user, $company);
    }
}
