<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class RejectionReasons
{
    public const CONTEXT_LEADS = 'leads';

    public const CONTEXT_ACCOUNTS = 'accounts';

    public const CONTEXT_PAYMENTS = 'payments';

    public const OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function codes(string $context): array
    {
        return match ($context) {
            self::CONTEXT_LEADS => [
                'missing_required_evidence',
                'photos_unclear',
                'homeowner_agreement_missing',
                'property_details_mismatch',
                'measurements_incorrect',
                'duplicate_lead',
                'property_not_eligible',
                'outside_supported_area',
                self::OTHER,
            ],
            self::CONTEXT_ACCOUNTS => [
                'company_details_incomplete',
                'invalid_business_information',
                'missing_required_documents',
                'duplicate_account',
                'not_eligible_for_platform',
                self::OTHER,
            ],
            self::CONTEXT_PAYMENTS => [
                'payment_proof_unclear',
                'payment_reference_mismatch',
                'amount_mismatch',
                'document_invalid',
                self::OTHER,
            ],
            default => [self::OTHER],
        };
    }

    public static function label(string $context, string $code): string
    {
        $key = "rml.rejection.{$context}.{$code}";
        $translated = __($key);

        return $translated !== $key ? $translated : $code;
    }

    /**
     * Human message for emails / threads (label + optional comment).
     */
    public static function composeMessage(string $context, string $code, ?string $comment = null): string
    {
        $label = self::label($context, $code);
        $comment = trim((string) $comment);

        if ($comment === '') {
            return $label;
        }

        return $label."\n\n".__('rml.rejection.comment_label').': '.$comment;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(string $context, string $codeField = 'reason_code', string $commentField = 'comment'): array
    {
        return [
            $codeField => ['required', 'string', Rule::in(self::codes($context))],
            $commentField => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn () => request()->input($codeField) === self::OTHER),
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(string $context): array
    {
        return array_map(
            fn (string $code) => [
                'value' => $code,
                'label' => self::label($context, $code),
            ],
            self::codes($context),
        );
    }
}
