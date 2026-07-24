<?php

namespace App\Services\Seller;

use App\Models\Lead;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;

class SellerLeadScope
{
    /**
     * Scope leads visible to a seller user based on their permissions.
     *
     * @return Builder<Lead>
     */
    public static function forUser(User $user): Builder
    {
        $query = Lead::query();

        if ($user->can(Permissions::VIEW_COMPANY_LEADS)) {
            $companyId = $user->sellerProfile?->company_id;

            if ($companyId === null) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('seller_company_id', $companyId);
        }

        if ($user->can(Permissions::VIEW_OWN_LEADS)) {
            return $query->where('submitted_by_user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
