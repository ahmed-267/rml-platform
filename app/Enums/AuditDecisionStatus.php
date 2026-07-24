<?php

namespace App\Enums;

enum AuditDecisionStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case RecommendedAccept = 'recommended_accept';
    case RecommendedReject = 'recommended_reject';
    case NeedsMoreInformation = 'needs_more_information';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
