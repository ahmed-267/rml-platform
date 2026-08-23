<?php

namespace App\Support;

use App\Enums\SurveyStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\Survey\LeadSurveyService;

class SurveySummary
{
    /**
     * @return array<string, mixed>|null
     */
    public static function forLead(Lead $lead, User $user, string $portal): ?array
    {
        $canConduct = $user->can(Permissions::CONDUCT_SURVEYS) || $user->hasRole('super_admin');
        $canReview = $user->can(Permissions::REVIEW_SURVEYS) || $user->hasRole('super_admin');

        if (! $canConduct && ! $canReview) {
            return null;
        }

        $survey = $lead->survey;
        $service = app(LeadSurveyService::class);
        $status = $survey?->status;
        $action = $service->actionForStatus($status);

        $showRoute = route("{$portal}.surveys.show", $lead);
        $startRoute = route("{$portal}.surveys.start", $lead);

        if ($action === 'start' && ! $canConduct) {
            // Reviewers can only open an existing survey.
            if (! $survey) {
                return [
                    'exists' => false,
                    'status' => SurveyStatus::NotStarted->value,
                    'action' => 'start',
                    'action_label' => __('rml.survey.actions.start'),
                    'href' => null,
                    'eligibility_status' => $lead->survey_eligibility_status ?? 'survey_not_started',
                    'version' => null,
                    'last_saved_at' => null,
                    'can_conduct' => false,
                    'can_review' => $canReview,
                    'catastro_status' => $lead->latestCatastroSnapshot?->verification_status?->value
                        ?? 'not_checked',
                ];
            }
        }

        $href = match ($action) {
            'start' => $canConduct ? $startRoute : $showRoute,
            default => $showRoute,
        };

        $labelKey = match ($action) {
            'start' => 'rml.survey.actions.start',
            'continue' => 'rml.survey.actions.continue',
            'correct' => 'rml.survey.actions.correct',
            'review' => 'rml.survey.actions.review',
            'view_approved' => 'rml.survey.actions.view_approved',
            default => 'rml.survey.actions.view',
        };

        return [
            'exists' => $survey !== null,
            'status' => $status?->value ?? SurveyStatus::NotStarted->value,
            'action' => $action,
            'action_label' => __($labelKey),
            'href' => $href,
            'eligibility_status' => $lead->survey_eligibility_status
                ?? ($status ? $service->eligibilityLabel($status) : 'survey_not_started'),
            'version' => $survey?->version,
            'last_saved_at' => optional($survey?->last_saved_at)?->toIso8601String(),
            'can_conduct' => $canConduct,
            'can_review' => $canReview,
            'catastro_status' => $lead->latestCatastroSnapshot?->verification_status?->value
                ?? 'not_checked',
        ];
    }
}
