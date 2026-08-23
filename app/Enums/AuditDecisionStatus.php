<?php

namespace App\Enums;

enum AuditDecisionStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case RecommendedAccept = 'recommended_accept';
    case RecommendedReject = 'recommended_reject';
    case NeedsMoreInformation = 'needs_more_information';
    case ReSurveyRequired = 're_survey_required';
    case ManualVerificationRequired = 'manual_verification_required';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * User-facing Pre-Installation Audit outcome label key under
     * rml.pre_installation.audit_outcomes.*.
     */
    public function preInstallationOutcomeKey(): string
    {
        return match ($this) {
            self::Pending, self::InReview => 'pending_review',
            self::RecommendedAccept, self::Accepted => 'approved',
            self::NeedsMoreInformation => 'needs_information',
            self::ReSurveyRequired => 're_survey_required',
            self::ManualVerificationRequired => 'manual_verification_required',
            self::RecommendedReject, self::Rejected => 'rejected',
        };
    }
}
