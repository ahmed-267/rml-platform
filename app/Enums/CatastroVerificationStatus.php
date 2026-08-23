<?php

namespace App\Enums;

enum CatastroVerificationStatus: string
{
    case NotChecked = 'not_checked';
    case LookupInProgress = 'lookup_in_progress';
    case Matched = 'matched';
    case PartiallyMatched = 'partially_matched';
    case MismatchDetected = 'mismatch_detected';
    case MultiplePropertiesFound = 'multiple_properties_found';
    case ManualReviewRequired = 'manual_review_required';
    case RegionalProviderRequired = 'regional_provider_required';
    case ServiceUnavailable = 'service_unavailable';
    case LookupFailed = 'lookup_failed';
    case PropertyNotFound = 'property_not_found';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
