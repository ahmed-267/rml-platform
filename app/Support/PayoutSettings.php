<?php

namespace App\Support;

final class PayoutSettings extends CachedConfigSettings
{
    protected static function cacheKey(): string
    {
        return 'rml.payout_settings';
    }

    protected static function configKey(): string
    {
        return 'rml.payouts';
    }

    public static function method(): string
    {
        $method = static::string('method', 'per_m2');

        return in_array($method, ['fixed', 'per_m2', 'percentage'], true) ? $method : 'per_m2';
    }

    public static function fixedAmount(): ?float
    {
        $value = static::get('fixed_amount');

        return $value === null || $value === '' ? null : (float) $value;
    }

    public static function ratePerM2(): float
    {
        return max(0, static::float('rate_per_m2', 2.0));
    }

    public static function percentage(): ?float
    {
        $value = static::get('percentage');

        return $value === null || $value === '' ? null : (float) $value;
    }

    public static function rmlInternalPayouts(): bool
    {
        return static::bool('rml_internal_payouts', false)
            || LeadSettings::rmlInternalCreatesPayouts();
    }

    public static function companyPayouts(): bool
    {
        return static::bool('company_payouts', true);
    }

    public static function agentPayouts(): bool
    {
        return static::bool('agent_payouts', true);
    }

    public static function staffPayoutToCompany(): bool
    {
        return static::bool('staff_payout_to_company', true);
    }

    public static function allowManualOverride(): bool
    {
        return static::bool('allow_manual_override', true);
    }

    public static function calculateSuggested(?float $sizeM2, ?float $sellingPrice = null): ?float
    {
        return match (static::method()) {
            'fixed' => static::fixedAmount(),
            'percentage' => $sellingPrice !== null && static::percentage() !== null
                ? round($sellingPrice * (static::percentage() / 100), 2)
                : null,
            default => $sizeM2 !== null && $sizeM2 > 0
                ? round($sizeM2 * static::ratePerM2(), 2)
                : null,
        };
    }
}
