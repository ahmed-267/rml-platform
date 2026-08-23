<?php

namespace App\Support;

final class PricingSettings extends CachedConfigSettings
{
    protected static function cacheKey(): string
    {
        return 'rml.pricing_settings';
    }

    protected static function configKey(): string
    {
        return 'rml.pricing';
    }

    public static function calculationMethod(): string
    {
        $method = static::string('calculation_method', 'per_m2');

        return in_array($method, ['fixed', 'per_m2'], true) ? $method : 'per_m2';
    }

    public static function fixedSellingPrice(): ?float
    {
        $value = static::get('fixed_selling_price');

        return $value === null || $value === '' ? null : (float) $value;
    }

    public static function minimumSellingPrice(): float
    {
        return max(0, static::float('minimum_selling_price', 0));
    }

    public static function maximumDiscountPercent(): float
    {
        return max(0, min(100, static::float('maximum_discount_percent', 25)));
    }

    public static function allowManualOverride(): bool
    {
        return static::bool('allow_manual_override', true);
    }

    public static function packageDiscountPercentMax(): float
    {
        return max(0, min(100, static::float('package_discount_percent_max', 15)));
    }

    public static function taxPercent(): float
    {
        return max(0, min(100, static::float('tax_percent', 0)));
    }

    public static function currency(): string
    {
        return 'EUR';
    }

    public static function applyMinimum(?float $price): ?float
    {
        if ($price === null) {
            return null;
        }

        return max($price, static::minimumSellingPrice());
    }
}
