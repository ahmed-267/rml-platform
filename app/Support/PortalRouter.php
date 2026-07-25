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

    /**
     * Only honour url.intended when it belongs to the user's portal
     * (or shared account routes). Never send a seller/buyer to /admin/*.
     */
    public static function safeIntendedPath(?User $user, ?string $intended): ?string
    {
        if (! $user || ! $intended) {
            return null;
        }

        $path = parse_url($intended, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (in_array($path, ['/profile', '/pending-approval', '/dashboard'], true)) {
            return $path;
        }

        $portal = $user->portal();

        return match ($portal) {
            'admin' => str_starts_with($path, '/admin') ? $path : null,
            'seller' => str_starts_with($path, '/seller') ? $path : null,
            'buyer' => str_starts_with($path, '/buyer') ? $path : null,
            'auditor' => str_starts_with($path, '/auditor') ? $path : null,
            default => null,
        };
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
