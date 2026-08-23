<?php

namespace App\Support;

use App\Enums\LeadStatus;

final class LeadSettings extends CachedConfigSettings
{
    protected static function cacheKey(): string
    {
        return 'rml.lead_settings';
    }

    protected static function configKey(): string
    {
        return 'rml.leads';
    }

    public static function defaultStatusAfterSubmission(): LeadStatus
    {
        $value = static::string('default_status_after_submission', LeadStatus::PendingValidation->value);

        return LeadStatus::tryFrom($value) ?? LeadStatus::PendingValidation;
    }

    public static function requireInternalAudit(): bool
    {
        return static::bool('require_internal_audit', true);
    }

    public static function allowEvidenceLater(): bool
    {
        return static::bool('allow_evidence_later', true);
    }

    public static function allowSellerCompanyLeads(): bool
    {
        return static::bool('allow_seller_company_leads', true);
    }

    public static function allowSellerAgentLeads(): bool
    {
        return static::bool('allow_seller_agent_leads', true);
    }

    public static function rmlInternalCreatesPayouts(): bool
    {
        return static::bool('rml_internal_creates_payouts', false);
    }

    public static function minPropertyAreaM2(): float
    {
        return static::float('min_property_area_m2', 1);
    }

    public static function maxPropertyAreaM2(): float
    {
        return static::float('max_property_area_m2', 10000);
    }

    public static function reservationHours(): int
    {
        return max(1, static::int('reservation_hours', 24));
    }

    public static function saleLockHours(): int
    {
        return max(1, static::int('sale_lock_hours', 48));
    }

    public static function allowRejectedResubmit(): bool
    {
        return static::bool('allow_rejected_resubmit', true);
    }

    public static function duplicateDetection(): string
    {
        $mode = static::string('duplicate_detection', 'soft');

        return in_array($mode, ['off', 'soft', 'strict'], true) ? $mode : 'soft';
    }

    public static function isAreaWithinLimits(?float $area): bool
    {
        if ($area === null) {
            return true;
        }

        return $area >= static::minPropertyAreaM2()
            && $area <= static::maxPropertyAreaM2();
    }
}
