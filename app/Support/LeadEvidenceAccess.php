<?php

namespace App\Support;

use App\Enums\EvidenceFileType;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadEvidenceFile;
use App\Models\Scheme;
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

        // Buyers: only after purchase payment confirmed / details released.
        if (self::isBuyerSide($user)) {
            return app(\App\Services\Buyer\LeadReleaseService::class)
                ->canReleaseDetails($user, $lead);
        }

        return false;
    }

    /**
     * @return list<EvidenceFileType>
     */
    public static function requiredTypesForScheme(?string $schemeSlug): array
    {
        $requirements = self::schemeRequirements($schemeSlug);
        $types = [];

        if ($requirements['require_photos']) {
            $types[] = EvidenceFileType::Photo;
        }
        if ($requirements['require_homeowner_agreement']) {
            $types[] = EvidenceFileType::SignedHomeownerAgreement;
        }
        if ($requirements['require_epc']) {
            $types[] = EvidenceFileType::EligibilityDocument;
        }

        // Safe default when a scheme has all toggles off.
        if ($types === []) {
            $types = [
                EvidenceFileType::Photo,
                EvidenceFileType::SignedHomeownerAgreement,
            ];
        }

        return $types;
    }

    /**
     * @return array{
     *     require_internal_audit: bool,
     *     require_homeowner_agreement: bool,
     *     require_epc: bool,
     *     require_photos: bool,
     *     min_measurement: float|null,
     *     max_measurement: float|null
     * }
     */
    public static function schemeRequirements(?string $schemeSlug): array
    {
        $defaults = [
            'require_internal_audit' => true,
            'require_homeowner_agreement' => true,
            'require_epc' => false,
            'require_photos' => true,
            'min_measurement' => null,
            'max_measurement' => null,
        ];

        if (! $schemeSlug) {
            return $defaults;
        }

        $scheme = Scheme::query()->where('slug', $schemeSlug)->first(['id', 'metadata']);
        $meta = is_array($scheme?->metadata) ? $scheme->metadata : [];
        $requirements = is_array($meta['requirements'] ?? null) ? $meta['requirements'] : [];

        return [
            'require_internal_audit' => (bool) ($requirements['require_internal_audit'] ?? $defaults['require_internal_audit']),
            'require_homeowner_agreement' => (bool) ($requirements['require_homeowner_agreement'] ?? $defaults['require_homeowner_agreement']),
            'require_epc' => (bool) ($requirements['require_epc'] ?? $defaults['require_epc']),
            'require_photos' => (bool) ($requirements['require_photos'] ?? $defaults['require_photos']),
            'min_measurement' => isset($requirements['min_measurement'])
                ? (float) $requirements['min_measurement']
                : null,
            'max_measurement' => isset($requirements['max_measurement'])
                ? (float) $requirements['max_measurement']
                : null,
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

    private static function isBuyerSide(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::BuyerAdmin->value,
        ]);
    }
}
