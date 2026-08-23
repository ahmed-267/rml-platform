<?php

namespace App\Support;

final class CatastroSettings extends CachedConfigSettings
{
    protected static function cacheKey(): string
    {
        return 'rml.catastro_settings';
    }

    protected static function configKey(): string
    {
        return 'rml.catastro';
    }

    public static function enabled(): bool
    {
        if (! config('services.catastro.enabled', true)) {
            return false;
        }

        return static::bool('enabled', true);
    }

    public static function areaTolerancePercent(): float
    {
        return static::float('area_tolerance_percent', 15);
    }

    public static function coordinateWarnMeters(): float
    {
        return static::float('coordinate_warn_meters', 150);
    }

    public static function requireForAuditApproval(): bool
    {
        return static::bool('require_for_audit_approval', false);
    }
}
