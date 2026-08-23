<?php

namespace App\Support;

final class PackageSettings extends CachedConfigSettings
{
    protected static function cacheKey(): string
    {
        return 'rml.package_settings';
    }

    protected static function configKey(): string
    {
        return 'rml.packages';
    }

    public static function allowMixedScheme(): bool
    {
        return static::bool('allow_mixed_scheme', true);
    }

    public static function allowWithoutBuyer(): bool
    {
        return static::bool('allow_without_buyer', true);
    }

    public static function defaultStatus(): string
    {
        return static::string('default_status', 'available');
    }

    public static function reservationLock(): bool
    {
        return static::bool('reservation_lock', true);
    }

    public static function expiryDays(): int
    {
        return max(1, static::int('expiry_days', 14));
    }

    public static function allowManualCreation(): bool
    {
        return static::bool('allow_manual_creation', true);
    }

    public static function allowInstallerBasedCreation(): bool
    {
        return static::bool('allow_installer_based_creation', true);
    }

    public static function allowWithoutInstaller(): bool
    {
        return static::bool('allow_without_installer', true);
    }

    public static function allowMixedZone(): bool
    {
        return static::bool('allow_mixed_zone', true);
    }

    public static function minLeads(): int
    {
        return max(1, static::int('min_leads', 1));
    }

    public static function maxLeads(): int
    {
        return max(static::minLeads(), static::int('max_leads', 100));
    }

    public static function minAreaM2(): float
    {
        return max(0, static::float('min_area_m2', 0));
    }

    public static function maxAreaM2(): float
    {
        return max(static::minAreaM2(), static::float('max_area_m2', 100000));
    }

    public static function defaultSearchRadiusKm(): float
    {
        return max(1, static::float('default_search_radius_km', 50));
    }

    public static function maxLeadDistanceKm(): float
    {
        return max(1, static::float('max_lead_distance_km', 200));
    }

    public static function releaseLeadsOnExpiry(): bool
    {
        return static::bool('release_leads_on_expiry', true);
    }

    public static function reservationHours(): int
    {
        return max(1, static::int('reservation_hours', 48));
    }
}
