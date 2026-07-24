<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PendingEvidence = 'pending_evidence';
    case PendingValidation = 'pending_validation';
    case Validating = 'validating';
    case NeedsMoreInformation = 'needs_more_information';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Priced = 'priced';
    case Listed = 'listed';
    case Sold = 'sold';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
