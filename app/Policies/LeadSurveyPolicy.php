<?php

namespace App\Policies;

use App\Enums\SurveyStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadSurvey;
use App\Models\User;
use App\Support\Permissions;

class LeadSurveyPolicy
{
    public function view(User $user, LeadSurvey $survey): bool
    {
        return $this->canAccessLead($user, $survey->lead)
            && (
                $user->can(Permissions::CONDUCT_SURVEYS)
                || $user->can(Permissions::REVIEW_SURVEYS)
                || $user->hasRole(UserRole::SuperAdmin->value)
            );
    }

    public function conduct(User $user, Lead $lead): bool
    {
        return $this->canAccessLead($user, $lead)
            && (
                $user->can(Permissions::CONDUCT_SURVEYS)
                || $user->hasRole(UserRole::SuperAdmin->value)
            );
    }

    public function update(User $user, LeadSurvey $survey): bool
    {
        if (! $this->conduct($user, $survey->lead)) {
            return false;
        }

        return $survey->status->isEditableBySurveyor()
            || $survey->status === SurveyStatus::NeedsCorrection;
    }

    public function review(User $user, LeadSurvey $survey): bool
    {
        return $user->can(Permissions::REVIEW_SURVEYS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    private function canAccessLead(User $user, ?Lead $lead): bool
    {
        if (! $lead) {
            return false;
        }

        if (
            $user->hasRole(UserRole::SuperAdmin->value)
            || $user->can(Permissions::VIEW_LEADS)
            || $user->can(Permissions::AUDIT_LEADS)
        ) {
            return true;
        }

        if ($user->can('view', $lead)) {
            return true;
        }

        return false;
    }
}
