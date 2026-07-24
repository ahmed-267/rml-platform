<?php

namespace App\Support;

/**
 * Display labels for payment method vs payment provider.
 * DB values stay unchanged; UI uses this mapping.
 */
final class PaymentPresentation
{
    public static function methodLabel(?string $method): ?string
    {
        if ($method === null || $method === '') {
            return null;
        }

        $key = 'rml.payment_methods.'.$method;
        $translated = __($key);

        return is_string($translated) && $translated !== $key
            ? $translated
            : $method;
    }

    public static function providerLabel(?string $provider): ?string
    {
        if ($provider === null || $provider === '') {
            return null;
        }

        $normalized = match (strtolower($provider)) {
            'stripe', 'stripe_checkout' => 'stripe',
            'mollie' => 'mollie',
            'manual', 'manual_bank_transfer', 'bank_transfer' => 'manual',
            default => strtolower($provider),
        };

        $key = 'rml.payment_providers.'.$normalized;
        $translated = __($key);

        return is_string($translated) && $translated !== $key
            ? $translated
            : $provider;
    }

    public static function providerKey(?string $provider): ?string
    {
        if ($provider === null || $provider === '') {
            return null;
        }

        return match (strtolower($provider)) {
            'stripe', 'stripe_checkout' => 'stripe',
            'mollie' => 'mollie',
            'manual', 'manual_bank_transfer', 'bank_transfer' => 'manual',
            default => strtolower($provider),
        };
    }
}
