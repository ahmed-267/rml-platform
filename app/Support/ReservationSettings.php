<?php

namespace App\Support;

final class ReservationSettings extends CachedConfigSettings
{
    protected static function cacheKey(): string
    {
        return 'rml.reservation_settings';
    }

    protected static function configKey(): string
    {
        return 'rml.reservations';
    }

    public static function leadReservationHours(): int
    {
        return max(1, static::int('lead_reservation_hours', LeadSettings::reservationHours()));
    }

    public static function packageReservationHours(): int
    {
        return max(1, static::int('package_reservation_hours', PackageSettings::reservationHours()));
    }

    public static function abandonedExpiresHours(): int
    {
        return max(1, static::int('abandoned_expires_hours', 72));
    }

    public static function returnLeadsToListed(): bool
    {
        return static::bool('return_leads_to_listed', true);
    }

    public static function returnPackagesToAvailable(): bool
    {
        return static::bool('return_packages_to_available', true);
    }

    public static function allowEditReservedPackage(): bool
    {
        return static::bool('allow_edit_reserved_package', false);
    }

    public static function onPaymentFail(): string
    {
        $value = static::string('on_payment_fail', 'release');

        return in_array($value, ['release', 'keep_locked'], true) ? $value : 'release';
    }

    public static function shouldReleaseOnPaymentFail(): bool
    {
        return static::onPaymentFail() === 'release';
    }
}
