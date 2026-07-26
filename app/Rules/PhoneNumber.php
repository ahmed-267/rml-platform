<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts international-style phone numbers: digits with optional +, spaces, dashes, parentheses.
 * Digit count must be between 9 and 15 (E.164-ish).
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail(__('rml.validation.phone_format'));

            return;
        }

        $trimmed = trim($value);

        if (! preg_match('/^\+?[\d\s\-().]+$/', $trimmed)) {
            $fail(__('rml.validation.phone_format'));

            return;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        $length = strlen($digits);

        if ($length < 9) {
            $fail(__('rml.validation.phone_too_short', [
                'count' => $length,
                'min' => 9,
                'max' => 15,
            ]));

            return;
        }

        if ($length > 15) {
            $fail(__('rml.validation.phone_too_long', [
                'count' => $length,
                'min' => 9,
                'max' => 15,
            ]));
        }
    }
}
