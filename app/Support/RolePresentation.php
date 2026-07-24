<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * Visible role labels for UI — backend keys stay unchanged.
 */
final class RolePresentation
{
    public static function label(?string $roleKey): ?string
    {
        if ($roleKey === null || $roleKey === '') {
            return null;
        }

        $translated = __('rml.roles.'.$roleKey);

        if (is_string($translated) && $translated !== 'rml.roles.'.$roleKey) {
            return $translated;
        }

        $enum = UserRole::tryFrom($roleKey);

        return $enum?->label() ?? $roleKey;
    }

    /**
     * Map seller_type filter keys to the same visible labels as roles.
     */
    public static function sellerTypeLabel(string $typeKey): string
    {
        return match ($typeKey) {
            'company_admin' => self::label(UserRole::SellerCompanyAdmin->value)
                ?? UserRole::SellerCompanyAdmin->label(),
            'seller_staff' => self::label(UserRole::SellerStaff->value)
                ?? UserRole::SellerStaff->label(),
            'individual_agent' => self::label(UserRole::IndividualSellerAgent->value)
                ?? UserRole::IndividualSellerAgent->label(),
            default => $typeKey,
        };
    }
}
