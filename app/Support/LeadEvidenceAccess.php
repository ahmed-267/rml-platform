<?php

namespace App\Support;

use App\Enums\EvidenceFileType;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadEvidenceFile;
use App\Models\User;

final class LeadEvidenceAccess
{
    public static function canView(User $user, LeadEvidenceFile $evidence): bool
    {
        $evidence->loadMissing('lead');

        if (
            $user->can(Permissions::VIEW_LEADS)
            || $user->can(Permissions::AUDIT_LEADS)
            || $user->hasRole(UserRole::SuperAdmin->value)
            || $user->hasRole(UserRole::AdminStaff->value)
            || $user->hasRole(UserRole::InternalAuditor->value)
        ) {
            return true;
        }

        $lead = $evidence->lead;
        if (! $lead instanceof Lead) {
            return false;
        }

        // Sellers: company admin → company leads; staff/agent → own leads only.
        if ($user->can('view', $lead) && self::isSellerSide($user)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<EvidenceFileType>
     */
    public static function requiredTypesForScheme(?string $schemeSlug): array
    {
        // Photos + homeowner agreement are required for every scheme.
        // EPC / technical docs remain optional uploads (shown in UI).
        return [
            EvidenceFileType::Photo,
            EvidenceFileType::SignedHomeownerAgreement,
        ];
    }

    private static function isSellerSide(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SellerCompanyAdmin->value,
            UserRole::SellerStaff->value,
            UserRole::IndividualSellerAgent->value,
        ]);
    }
}
