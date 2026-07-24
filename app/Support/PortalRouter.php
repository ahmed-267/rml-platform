<?php

namespace App\Support;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Models\User;

final class PortalRouter
{
    public static function dashboardRouteName(?User $user): string
    {
        return match ($user?->portal()) {
            'admin' => 'admin.dashboard',
            'seller' => 'seller.dashboard',
            'buyer' => 'buyer.dashboard',
            'auditor' => 'auditor.dashboard',
            default => 'dashboard',
        };
    }

    public static function dashboardPath(?User $user): string
    {
        return match ($user?->portal()) {
            'admin' => '/admin/dashboard',
            'seller' => '/seller/dashboard',
            'buyer' => '/buyer/dashboard',
            'auditor' => '/auditor/dashboard',
            default => '/dashboard',
        };
    }

    /**
     * Post-login / intended home path honouring approval status.
     */
    public static function homePath(?User $user): string
    {
        if (! $user) {
            return '/login';
        }

        if (self::requiresApprovalGate($user) && $user->approval_status !== ApprovalStatus::Approved) {
            return '/pending-approval';
        }

        return self::dashboardPath($user);
    }

    public static function isInternalRole(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminStaff->value,
            UserRole::InternalAuditor->value,
        ]);
    }

    /**
     * Seller and buyer accounts must be approved before portal access.
     */
    public static function requiresApprovalGate(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            UserRole::SellerCompanyAdmin->value,
            UserRole::SellerStaff->value,
            UserRole::IndividualSellerAgent->value,
            UserRole::BuyerAdmin->value,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function rolesForPortal(string $portal): array
    {
        return match ($portal) {
            'admin' => [
                UserRole::SuperAdmin->value,
                UserRole::AdminStaff->value,
            ],
            'seller' => [
                UserRole::SellerCompanyAdmin->value,
                UserRole::SellerStaff->value,
                UserRole::IndividualSellerAgent->value,
            ],
            'buyer' => [
                UserRole::BuyerAdmin->value,
            ],
            'auditor' => [
                UserRole::InternalAuditor->value,
            ],
            default => [],
        };
    }
}
