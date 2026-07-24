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
        return $user->can(Permissions::BUY_LEADS)
            && $package->status === PackageStatus::Available;
    }
}
