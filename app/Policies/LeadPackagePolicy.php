<?php

namespace App\Policies;

use App\Enums\PackageStatus;
use App\Enums\UserRole;
use App\Models\LeadPackage;
use App\Models\User;
use App\Support\Permissions;

class LeadPackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::BUY_LEADS)
            || $user->can(Permissions::VIEW_LEADS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function view(User $user, LeadPackage $package): bool
    {
        if ($user->can(Permissions::VIEW_LEADS) || $user->hasRole(UserRole::SuperAdmin->value)) {
            return true;
        }

        if (! $user->can(Permissions::BUY_LEADS)) {
            return false;
        }

        return in_array($package->status, [
            PackageStatus::Available,
            PackageStatus::Locked,
            PackageStatus::Sold,
        ], true);
    }

    public function buy(User $user, LeadPackage $package): bool
    {
        if (! $user->can(Permissions::BUY_LEADS) || $package->status !== PackageStatus::Available) {
            return false;
        }

        if ($package->buyer_company_id === null) {
            return true;
        }

        return (int) $user->buyerProfile?->company_id === (int) $package->buyer_company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value)
            || $user->can(Permissions::MANAGE_PACKAGES);
    }

    public function update(User $user, LeadPackage $package): bool
    {
        return $this->create($user);
    }

    public function cancel(User $user, LeadPackage $package): bool
    {
        return $this->create($user)
            && $package->status !== PackageStatus::Sold
            && $package->status !== PackageStatus::Cancelled;
    }
}
