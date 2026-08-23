<?php

namespace App\Enums;

enum SurveyStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case NeedsCorrection = 'needs_correction';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isEditableBySurveyor(): bool
    {
        return in_array($this, [
            self::NotStarted,
            self::InProgress,
            self::NeedsCorrection,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }
}
