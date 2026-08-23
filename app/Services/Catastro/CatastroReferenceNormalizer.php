<?php

namespace App\Services\Catastro;

use InvalidArgumentException;

final class CatastroReferenceNormalizer
{
    /**
     * Normalise Spanish cadastral reference (14 or 20 chars without separators).
     */
    public static function normalize(string $value): string
    {
        $normalized = strtoupper(preg_replace('/[\s\-\.]/', '', trim($value)) ?? '');

        if ($normalized === '') {
            throw new InvalidArgumentException('cadastral_reference_required');
        }

        if (! preg_match('/^[A-Z0-9]{14}$|^[A-Z0-9]{20}$/', $normalized)) {
            throw new InvalidArgumentException('cadastral_reference_invalid');
        }

        return $normalized;
    }

    public static function tryNormalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return self::normalize($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
