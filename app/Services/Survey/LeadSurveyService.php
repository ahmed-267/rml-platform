<?php

namespace App\Services\Survey;

use App\Enums\CatastroProvider;
use App\Enums\CatastroVerificationStatus;
use App\Enums\SurveyStatus;
use App\Models\Lead;
use App\Models\LeadCatastroSnapshot;
use App\Models\LeadSurvey;
use App\Models\LeadSurveyEvidence;
use App\Models\LeadSurveyMeasurementSection;
use App\Models\LeadSurveyVersion;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Catastro\CatastroLookupService;
use App\Support\FilesystemDisk;
use App\Support\SurveyFieldConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadSurveyService
{
    public const STEPS = [
        'property',
        'measurements',
        'scheme',
        'evidence',
        'homeowner',
        'risks',
        'review',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService = new AuditLogService,
    ) {}

    public function ensureForLead(Lead $lead, User $actor): LeadSurvey
    {
        $existing = LeadSurvey::query()->where('lead_id', $lead->id)->first();
        if ($existing) {
            $existing = $existing->load([
                'measurementSections',
                'evidence',
                'surveyor:id,name',
                'lead.scheme',
                'lead.zone',
                'lead.sellerCompany',
                'lead.submittedBy',
                'lead.latestCatastroSnapshot',
            ]);

            // Recover invalid drafts that stored a future current_step.
            $earliest = $this->earliestIncompleteStep($existing);
            if (! $this->canAccessStep($existing, (string) $existing->current_step)) {
                $existing->forceFill(['current_step' => $earliest])->save();
            }

            return $existing->fresh([
                'measurementSections',
                'evidence',
                'surveyor:id,name',
                'lead.scheme',
                'lead.zone',
                'lead.sellerCompany',
                'lead.submittedBy',
                'lead.latestCatastroSnapshot',
            ]);
        }

        return DB::transaction(function () use ($lead, $actor) {
            if ($lead->submitted_property_area_m2 === null && $lead->size_m2 !== null) {
                $lead->forceFill([
                    'submitted_property_area_m2' => $lead->size_m2,
                ])->save();
            }

            $this->ensureCatastroPlaceholder($lead);

            $survey = LeadSurvey::query()->create([
                'lead_id' => $lead->id,
                'status' => SurveyStatus::InProgress,
                'version' => 1,
                'current_step' => 'property',
                'surveyor_user_id' => $actor->id,
                'started_at' => now(),
                'last_saved_at' => now(),
                'last_edited_by_user_id' => $actor->id,
                'survey_date' => now()->toDateString(),
            ]);

            $lead->forceFill([
                'survey_eligibility_status' => $this->eligibilityLabel(SurveyStatus::InProgress),
            ])->save();

            $this->recordVersion($survey, $actor, 'survey.started', 'Survey started');
            $this->auditLogService->log(
                'survey.started',
                $survey,
                null,
                ['status' => SurveyStatus::InProgress->value, 'lead_id' => $lead->id],
                $actor,
            );

            return $survey->fresh([
                'measurementSections',
                'evidence',
                'surveyor:id,name',
                'lead.scheme',
                'lead.zone',
                'lead.sellerCompany',
                'lead.submittedBy',
                'lead.latestCatastroSnapshot',
            ]);
        });
    }

    public function ensureCatastroPlaceholder(Lead $lead): LeadCatastroSnapshot
    {
        $latest = $lead->latestCatastroSnapshot;
        if ($latest) {
            return $latest;
        }

        return LeadCatastroSnapshot::query()->create([
            'lead_id' => $lead->id,
            'provider' => CatastroProvider::Unknown,
            'verification_status' => CatastroVerificationStatus::NotChecked,
            'cadastral_reference' => $lead->cadastral_reference,
            'match_summary' => null,
            'lookup_at' => null,
        ]);
    }

    /**
     * Recalculate which steps are complete from saved survey data.
     *
     * @return list<string>
     */
    public function completedSteps(LeadSurvey $survey): array
    {
        $survey->loadMissing(['measurementSections', 'evidence', 'lead.scheme']);
        $completed = [];

        foreach (self::STEPS as $step) {
            if ($step === 'review') {
                continue;
            }
            if ($this->validateStep($survey, $step) === []) {
                $completed[] = $step;
            } else {
                break;
            }
        }

        if (count($completed) === 6) {
            $completed[] = 'review';
        }

        return $completed;
    }

    public function earliestIncompleteStep(LeadSurvey $survey): string
    {
        $completed = $this->completedSteps($survey);
        foreach (self::STEPS as $step) {
            if (! in_array($step, $completed, true)) {
                return $step;
            }
        }

        return 'review';
    }

    /**
     * Highest step index the user may open (0-based).
     */
    public function maxReachableStepIndex(LeadSurvey $survey): int
    {
        $completed = $this->completedSteps($survey);
        $count = count(array_filter(
            $completed,
            fn (string $step) => $step !== 'review',
        ));

        // Unlock next step after last sequential completion.
        return min(count(self::STEPS) - 1, $count);
    }

    public function canAccessStep(LeadSurvey $survey, string $step): bool
    {
        $index = array_search($step, self::STEPS, true);
        if ($index === false) {
            return false;
        }

        return $index <= $this->maxReachableStepIndex($survey);
    }

    /**
     * @return array<string, string>
     */
    public function validateStep(LeadSurvey $survey, string $step): array
    {
        $survey->loadMissing(['measurementSections', 'evidence', 'lead.scheme']);

        return match ($step) {
            'property' => $this->validatePropertyStep($survey),
            'measurements' => $this->validateMeasurementsStep($survey),
            'scheme' => $this->validateSchemeStep($survey),
            'evidence' => $this->validateEvidenceStep($survey),
            'homeowner' => $this->validateHomeownerStep($survey),
            'risks' => $this->validateRisksStep($survey),
            'review' => $this->validateForSubmit($survey),
            default => ['step' => __('rml.survey.errors.invalid_step')],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveDraft(LeadSurvey $survey, User $actor, array $data, bool $asSubmit = false): LeadSurvey
    {
        if ($asSubmit) {
            return $this->submit($survey, $actor, $data);
        }

        if (! $survey->status->isEditableBySurveyor()
            && ! $actor->can(\App\Support\Permissions::REVIEW_SURVEYS)
            && ! $actor->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'survey' => __('rml.survey.not_editable'),
            ]);
        }

        $intent = (string) ($data['intent'] ?? 'draft');
        $requestedStep = isset($data['current_step']) && in_array($data['current_step'], self::STEPS, true)
            ? (string) $data['current_step']
            : null;
        $advanceTo = isset($data['advance_to']) && in_array($data['advance_to'], self::STEPS, true)
            ? (string) $data['advance_to']
            : null;

        return DB::transaction(function () use ($survey, $actor, $data, $intent, $requestedStep, $advanceTo) {
            $survey = LeadSurvey::query()->whereKey($survey->id)->lockForUpdate()->firstOrFail();
            $this->applyPayload($survey, $data, $actor, allowStepWrite: false);
            if ($survey->status === SurveyStatus::NotStarted) {
                $survey->status = SurveyStatus::InProgress;
                $survey->started_at ??= now();
            }
            $survey->last_saved_at = now();
            $survey->last_edited_by_user_id = $actor->id;
            $survey->save();

            $this->syncMeasurementSections($survey, $data['measurement_sections'] ?? []);
            $survey->load(['measurementSections', 'evidence', 'lead.scheme']);

            if ($intent === 'continue') {
                $fromStep = $requestedStep ?? $this->earliestIncompleteStep($survey);
                $errors = $this->validateStep($survey, $fromStep);
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors + [
                        'step' => __('rml.survey.errors.step_incomplete'),
                    ]);
                }

                $fromIndex = array_search($fromStep, self::STEPS, true);
                $target = $advanceTo
                    ?? self::STEPS[min((int) $fromIndex + 1, count(self::STEPS) - 1)];
                if (! $this->canAccessStep($survey, $target) && $target !== self::STEPS[min((int) $fromIndex + 1, count(self::STEPS) - 1)]) {
                    throw ValidationException::withMessages([
                        'current_step' => __('rml.survey.errors.step_locked'),
                    ]);
                }
                // After validating fromStep, next step is unlocked.
                $nextIndex = min((int) $fromIndex + 1, count(self::STEPS) - 1);
                $survey->current_step = $advanceTo && array_search($advanceTo, self::STEPS, true) === $nextIndex
                    ? $advanceTo
                    : self::STEPS[$nextIndex];
            } else {
                // Draft: keep user on a valid reachable step; never unlock via draft alone.
                $earliest = $this->earliestIncompleteStep($survey);
                if ($requestedStep && $this->canAccessStep($survey, $requestedStep)) {
                    $survey->current_step = $requestedStep;
                } else {
                    $survey->current_step = $earliest;
                }
            }

            $survey->save();
            $survey->lead?->forceFill([
                'survey_eligibility_status' => $this->eligibilityLabel($survey->status),
            ])->save();

            $this->auditLogService->log(
                'survey.draft_saved',
                $survey,
                null,
                [
                    'status' => $survey->status->value,
                    'current_step' => $survey->current_step,
                    'intent' => $intent,
                    'version' => $survey->version,
                ],
                $actor,
            );

            return $survey->fresh(['measurementSections', 'evidence', 'lead.latestCatastroSnapshot', 'lead.scheme']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(LeadSurvey $survey, User $actor, array $data): LeadSurvey
    {
        if (! $survey->status->isEditableBySurveyor()
            && $survey->status !== SurveyStatus::NeedsCorrection) {
            throw ValidationException::withMessages([
                'survey' => __('rml.survey.not_editable'),
            ]);
        }

        return DB::transaction(function () use ($survey, $actor, $data) {
            $survey = LeadSurvey::query()->whereKey($survey->id)->lockForUpdate()->firstOrFail();
            $this->applyPayload($survey, $data, $actor);
            $survey->last_saved_at = now();
            $survey->last_edited_by_user_id = $actor->id;
            $survey->save();

            $this->syncMeasurementSections($survey, $data['measurement_sections'] ?? []);
            $survey->load(['measurementSections', 'evidence', 'lead.scheme']);

            $errors = $this->validateForSubmit($survey);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $wasCorrection = $survey->status === SurveyStatus::NeedsCorrection;
            $survey->status = $wasCorrection ? SurveyStatus::Resubmitted : SurveyStatus::Submitted;
            $survey->submitted_at = now();
            $survey->current_step = 'review';
            $survey->version = (int) $survey->version + ($wasCorrection ? 1 : 0);
            if (! $wasCorrection) {
                $survey->version = max(1, (int) $survey->version);
            }
            $survey->save();

            $this->recordVersion(
                $survey,
                $actor,
                $wasCorrection ? 'survey.resubmitted' : 'survey.submitted',
                $wasCorrection ? 'Survey resubmitted after correction' : 'Survey submitted',
            );

            $survey->lead?->forceFill([
                'survey_eligibility_status' => $this->eligibilityLabel($survey->status),
            ])->save();

            $this->auditLogService->log(
                $wasCorrection ? 'survey.resubmitted' : 'survey.submitted',
                $survey,
                null,
                ['status' => $survey->status->value, 'version' => $survey->version],
                $actor,
            );

            return $survey->fresh(['measurementSections', 'evidence', 'lead.latestCatastroSnapshot', 'lead.scheme']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestCorrection(LeadSurvey $survey, User $auditor, array $data): LeadSurvey
    {
        return DB::transaction(function () use ($survey, $auditor, $data) {
            $survey = LeadSurvey::query()->whereKey($survey->id)->lockForUpdate()->firstOrFail();
            if (! in_array($survey->status, [
                SurveyStatus::Submitted,
                SurveyStatus::UnderReview,
                SurveyStatus::Resubmitted,
            ], true)) {
                throw ValidationException::withMessages([
                    'survey' => __('rml.survey.cannot_request_correction'),
                ]);
            }

            $survey->status = SurveyStatus::NeedsCorrection;
            $survey->correction_request = (string) ($data['correction_request'] ?? '');
            $survey->correction_sections = $data['correction_sections'] ?? [];
            $survey->reviewed_by_user_id = $auditor->id;
            $survey->reviewed_at = now();
            $survey->save();

            $this->recordVersion($survey, $auditor, 'survey.correction_requested', $survey->correction_request);
            $survey->lead?->forceFill([
                'survey_eligibility_status' => $this->eligibilityLabel($survey->status),
            ])->save();

            $this->auditLogService->log(
                'survey.correction_requested',
                $survey,
                null,
                [
                    'status' => $survey->status->value,
                    'sections' => $survey->correction_sections,
                ],
                $auditor,
            );

            return $survey->fresh(['measurementSections', 'evidence']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function approve(LeadSurvey $survey, User $auditor, array $data): LeadSurvey
    {
        return DB::transaction(function () use ($survey, $auditor, $data) {
            $survey = LeadSurvey::query()->whereKey($survey->id)->lockForUpdate()->firstOrFail();
            if (! in_array($survey->status, [
                SurveyStatus::Submitted,
                SurveyStatus::UnderReview,
                SurveyStatus::Resubmitted,
            ], true)) {
                throw ValidationException::withMessages([
                    'survey' => __('rml.survey.cannot_approve'),
                ]);
            }

            $approvedArea = $data['auditor_approved_area_m2'] ?? $survey->surveyed_installation_area_m2
                ?? $survey->surveyed_floor_area_m2;

            $survey->status = SurveyStatus::Approved;
            $survey->auditor_approved_area_m2 = $approvedArea !== null ? (float) $approvedArea : null;
            $survey->auditor_approved_installation_area_m2 = isset($data['auditor_approved_installation_area_m2'])
                ? (float) $data['auditor_approved_installation_area_m2']
                : $survey->surveyed_installation_area_m2;
            $survey->auditor_decision_notes = $data['auditor_decision_notes'] ?? null;
            $survey->internal_audit_notes = $data['internal_audit_notes'] ?? $survey->internal_audit_notes;
            $survey->reviewed_by_user_id = $auditor->id;
            $survey->reviewed_at = now();
            $survey->approved_at = now();
            $survey->save();

            $this->recordVersion($survey, $auditor, 'survey.approved', 'Survey approved');
            $survey->lead?->forceFill([
                'survey_eligibility_status' => $this->eligibilityLabel($survey->status),
            ])->save();

            $this->auditLogService->log(
                'survey.approved',
                $survey,
                null,
                [
                    'status' => $survey->status->value,
                    'auditor_approved_area_m2' => $survey->auditor_approved_area_m2,
                ],
                $auditor,
            );

            return $survey->fresh(['measurementSections', 'evidence', 'lead']);
        });
    }

    public function reject(LeadSurvey $survey, User $auditor, string $notes): LeadSurvey
    {
        return DB::transaction(function () use ($survey, $auditor, $notes) {
            $survey = LeadSurvey::query()->whereKey($survey->id)->lockForUpdate()->firstOrFail();
            $survey->status = SurveyStatus::Rejected;
            $survey->auditor_decision_notes = $notes;
            $survey->reviewed_by_user_id = $auditor->id;
            $survey->reviewed_at = now();
            $survey->rejected_at = now();
            $survey->save();

            $this->recordVersion($survey, $auditor, 'survey.rejected', $notes);
            $survey->lead?->forceFill([
                'survey_eligibility_status' => $this->eligibilityLabel($survey->status),
            ])->save();

            $this->auditLogService->log(
                'survey.rejected',
                $survey,
                null,
                ['status' => $survey->status->value],
                $auditor,
            );

            return $survey->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function storeEvidence(
        LeadSurvey $survey,
        User $actor,
        UploadedFile $file,
        array $meta = [],
    ): LeadSurveyEvidence {
        if (! $survey->status->isEditableBySurveyor() && $survey->status !== SurveyStatus::NeedsCorrection) {
            throw ValidationException::withMessages([
                'evidence' => __('rml.survey.not_editable'),
            ]);
        }

        $disk = FilesystemDisk::uploads();
        $path = $file->store("lead-surveys/{$survey->id}", $disk);

        $evidence = LeadSurveyEvidence::query()->create([
            'lead_survey_id' => $survey->id,
            'measurement_section_id' => $meta['measurement_section_id'] ?? null,
            'category' => $meta['category'] ?? 'other',
            'survey_section' => $meta['survey_section'] ?? null,
            'uploaded_by_user_id' => $actor->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'caption' => $meta['caption'] ?? null,
            'for_ai_measurement' => (bool) ($meta['for_ai_measurement'] ?? false),
            'review_status' => 'pending',
        ]);

        $this->auditLogService->log(
            'survey.evidence_uploaded',
            $survey,
            null,
            ['evidence_id' => $evidence->id, 'category' => $evidence->category],
            $actor,
        );

        return $evidence;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(LeadSurvey $survey, bool $includeInternal = true): array
    {
        $survey->loadMissing([
            'measurementSections',
            'evidence',
            'surveyor:id,name',
            'lead.scheme',
            'lead.zone',
            'lead.sellerCompany',
            'lead.submittedBy:id,name',
            'lead.latestCatastroSnapshot',
            'versions' => fn ($q) => $q->latest('id')->limit(20),
        ]);

        $lead = $survey->lead;
        $catastro = $lead?->latestCatastroSnapshot;
        $submittedArea = $lead?->submitted_property_area_m2 ?? $lead?->size_m2;
        $cadastralArea = $includeInternal && $catastro?->constructed_area_m2 !== null
            ? (float) $catastro->constructed_area_m2
            : null;

        $comparison = [
            'submitted_area_m2' => $submittedArea !== null ? (float) $submittedArea : null,
            'cadastral_constructed_area_m2' => $cadastralArea,
            'surveyed_floor_area_m2' => $survey->surveyed_floor_area_m2 !== null
                ? (float) $survey->surveyed_floor_area_m2
                : null,
            'surveyed_installation_area_m2' => $survey->surveyed_installation_area_m2 !== null
                ? (float) $survey->surveyed_installation_area_m2
                : null,
            'auditor_approved_area_m2' => $survey->auditor_approved_area_m2 !== null
                ? (float) $survey->auditor_approved_area_m2
                : null,
            'ai_estimated_area_m2' => $includeInternal
                ? data_get($survey->ai_suggestions, 'estimated_area_m2')
                : null,
            'flags' => $this->comparisonFlags(
                $submittedArea !== null ? (float) $submittedArea : null,
                $cadastralArea,
                $survey->surveyed_floor_area_m2 !== null ? (float) $survey->surveyed_floor_area_m2 : null,
                $survey->surveyed_installation_area_m2 !== null ? (float) $survey->surveyed_installation_area_m2 : null,
            ),
        ];

        return [
            'id' => $survey->id,
            'lead_id' => $survey->lead_id,
            'status' => $survey->status?->value,
            'version' => $survey->version,
            'current_step' => $survey->current_step,
            'survey_date' => optional($survey->survey_date)->toDateString(),
            'last_saved_at' => optional($survey->last_saved_at)?->toIso8601String(),
            'submitted_at' => optional($survey->submitted_at)?->toIso8601String(),
            'surveyor' => $survey->surveyor
                ? ['id' => $survey->surveyor->id, 'name' => $survey->surveyor->name]
                : null,
            'submitted_lead' => [
                'lead_reference' => $lead?->lead_reference,
                'address_line_1' => $lead?->address_line_1,
                'address_line_2' => $lead?->address_line_2,
                'city' => $lead?->city,
                'postcode' => $lead?->postcode,
                'country' => $lead?->country,
                'latitude' => $lead?->latitude,
                'longitude' => $lead?->longitude,
                'property_type' => $lead?->property_type,
                'occupancy_type' => data_get($lead?->metricValues?->firstWhere('key', 'occupancy_type'), 'value'),
                'submitted_property_area_m2' => $submittedArea !== null ? (float) $submittedArea : null,
                'scheme' => $lead?->scheme?->name,
                'scheme_slug' => $lead?->scheme?->slug,
                'zone' => $lead?->zone?->code,
                'cadastral_reference' => $lead?->cadastral_reference,
                'seller' => $lead?->sellerCompany?->name ?? $lead?->submittedBy?->name,
                'submitted_at' => optional($lead?->created_at)?->toIso8601String(),
            ],
            'catastro' => $includeInternal
                ? $this->presentCatastroInternal($catastro)
                : $this->presentCatastroForSeller($lead, $catastro),
            'confirmed' => [
                'confirmed_address' => $survey->confirmed_address,
                'property_type' => $survey->property_type,
                'occupancy_type' => $survey->occupancy_type,
                'number_of_floors' => $survey->number_of_floors,
                'approx_construction_year' => $survey->approx_construction_year,
                'occupied' => $survey->occupied,
                'homeowner_present' => $survey->homeowner_present,
                'general_condition' => $survey->general_condition,
                'location_confirmed' => $survey->location_confirmed,
                'discrepancy_notes' => $survey->discrepancy_notes,
            ],
            'access' => $survey->access ?? [],
            'no_access' => $survey->no_access ?? [],
            'loft_hatch' => $survey->loft_hatch ?? [],
            'measurements' => [
                'surveyed_floor_area_m2' => $survey->surveyed_floor_area_m2 !== null
                    ? (float) $survey->surveyed_floor_area_m2
                    : null,
                'surveyed_installation_area_m2' => $survey->surveyed_installation_area_m2 !== null
                    ? (float) $survey->surveyed_installation_area_m2
                    : null,
                'measurement_method' => $survey->measurement_method?->value,
                'measurement_confidence' => $survey->measurement_confidence,
                'measurement_date' => optional($survey->measurement_date)?->toDateString(),
                'measurement_notes' => $survey->measurement_notes,
                'sections' => $survey->measurementSections->map(fn (LeadSurveyMeasurementSection $section) => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'section_type' => $section->section_type,
                    'length_m' => $section->length_m !== null ? (float) $section->length_m : null,
                    'width_m' => $section->width_m !== null ? (float) $section->width_m : null,
                    'height_m' => $section->height_m !== null ? (float) $section->height_m : null,
                    'calculated_area_m2' => $section->calculated_area_m2 !== null
                        ? (float) $section->calculated_area_m2
                        : null,
                    'manual_area_m2' => $section->manual_area_m2 !== null
                        ? (float) $section->manual_area_m2
                        : null,
                    'measurement_method' => $section->measurement_method?->value,
                    'confidence' => $section->confidence,
                    'is_estimate' => $section->is_estimate,
                    'area_not_accessed' => $section->area_not_accessed,
                    'notes' => $section->notes,
                    'sort_order' => $section->sort_order,
                ])->values()->all(),
            ],
            'comparison' => $comparison,
            'scheme_inspection' => $survey->scheme_inspection ?? [],
            'homeowner_confirmation' => $survey->homeowner_confirmation ?? [],
            'risks' => $survey->risks ?? [],
            'surveyor_recommendation' => $survey->surveyor_recommendation,
            'seller_visible_notes' => $survey->seller_visible_notes,
            'buyer_visible_notes' => $includeInternal ? $survey->buyer_visible_notes : $survey->buyer_visible_notes,
            'internal_audit_notes' => $includeInternal ? $survey->internal_audit_notes : null,
            'correction_request' => $survey->correction_request,
            'correction_sections' => $survey->correction_sections ?? [],
            'auditor_approved_area_m2' => $survey->auditor_approved_area_m2 !== null
                ? (float) $survey->auditor_approved_area_m2
                : null,
            'auditor_approved_installation_area_m2' => $survey->auditor_approved_installation_area_m2 !== null
                ? (float) $survey->auditor_approved_installation_area_m2
                : null,
            'auditor_decision_notes' => $includeInternal ? $survey->auditor_decision_notes : null,
            'ai_suggestions' => $includeInternal ? ($survey->ai_suggestions ?? null) : null,
            'evidence' => $survey->evidence->map(fn (LeadSurveyEvidence $item) => [
                'id' => $item->id,
                'category' => $item->category,
                'survey_section' => $item->survey_section,
                'measurement_section_id' => $item->measurement_section_id,
                'original_name' => $item->original_name,
                'caption' => $item->caption,
                'review_status' => $item->review_status,
                'auditor_comment' => $includeInternal ? $item->auditor_comment : null,
                'for_ai_measurement' => $item->for_ai_measurement,
                'url' => $item->temporaryUrl(),
                'created_at' => optional($item->created_at)?->toIso8601String(),
            ])->values()->all(),
            'versions' => $includeInternal
                ? $survey->versions->map(fn (LeadSurveyVersion $version) => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'status' => $version->status,
                    'event' => $version->event,
                    'notes' => $version->notes,
                    'created_at' => optional($version->created_at)?->toIso8601String(),
                ])->values()->all()
                : [],
            'eligibility_status' => $lead?->survey_eligibility_status
                ?? $this->eligibilityLabel($survey->status),
            'action' => $this->actionForStatus($survey->status),
            'step_progress' => $this->stepProgress($survey),
            'required_fields' => SurveyFieldConfig::requiredFieldsByStep($lead?->scheme?->slug),
        ];
    }

    public function actionForStatus(?SurveyStatus $status): string
    {
        return match ($status) {
            null, SurveyStatus::NotStarted => 'start',
            SurveyStatus::InProgress => 'continue',
            SurveyStatus::NeedsCorrection => 'correct',
            SurveyStatus::Approved => 'view_approved',
            SurveyStatus::Submitted, SurveyStatus::UnderReview, SurveyStatus::Resubmitted => 'review',
            SurveyStatus::Rejected => 'view_approved',
        };
    }

    public function eligibilityLabel(SurveyStatus $status): string
    {
        return match ($status) {
            SurveyStatus::NotStarted => 'survey_not_started',
            SurveyStatus::InProgress => 'survey_in_progress',
            SurveyStatus::Submitted => 'survey_pending_review',
            SurveyStatus::UnderReview => 'survey_under_review',
            SurveyStatus::NeedsCorrection => 'survey_needs_correction',
            SurveyStatus::Resubmitted => 'survey_pending_review',
            SurveyStatus::Rejected => 'survey_rejected',
            SurveyStatus::Approved => 'survey_approved',
        };
    }

    public function isEligibleForListing(?LeadSurvey $survey): bool
    {
        return $survey?->status === SurveyStatus::Approved;
    }

    /**
     * @return array{
     *     completed: list<string>,
     *     current: string,
     *     earliest_incomplete: string,
     *     max_reachable_index: int,
     *     reachable: list<string>
     * }
     */
    public function stepProgress(LeadSurvey $survey): array
    {
        $completed = $this->completedSteps($survey);
        $earliest = $this->earliestIncompleteStep($survey);
        $maxIndex = $this->maxReachableStepIndex($survey);
        $stored = (string) ($survey->current_step ?: 'property');
        $current = $this->canAccessStep($survey, $stored) ? $stored : $earliest;

        return [
            'completed' => $completed,
            'current' => $current,
            'earliest_incomplete' => $earliest,
            'max_reachable_index' => $maxIndex,
            'reachable' => array_values(array_slice(self::STEPS, 0, $maxIndex + 1)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>|mixed  $sections
     */
    private function syncMeasurementSections(LeadSurvey $survey, mixed $sections): void
    {
        if (! is_array($sections)) {
            return;
        }

        $keepIds = [];
        foreach (array_values($sections) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $length = isset($row['length_m']) && $row['length_m'] !== '' ? (float) $row['length_m'] : null;
            $width = isset($row['width_m']) && $row['width_m'] !== '' ? (float) $row['width_m'] : null;
            $calculated = $length !== null && $width !== null ? round($length * $width, 2) : null;
            if (isset($row['calculated_area_m2']) && $row['calculated_area_m2'] !== '') {
                $calculated = (float) $row['calculated_area_m2'];
            }

            $payload = [
                'name' => (string) ($row['name'] ?? ('Section '.($index + 1))),
                'section_type' => $row['section_type'] ?? null,
                'length_m' => $length,
                'width_m' => $width,
                'height_m' => isset($row['height_m']) && $row['height_m'] !== '' ? (float) $row['height_m'] : null,
                'calculated_area_m2' => $calculated,
                'manual_area_m2' => isset($row['manual_area_m2']) && $row['manual_area_m2'] !== ''
                    ? (float) $row['manual_area_m2']
                    : null,
                'measurement_method' => $row['measurement_method'] ?? null,
                'confidence' => $row['confidence'] ?? null,
                'is_estimate' => (bool) ($row['is_estimate'] ?? false),
                'area_not_accessed' => (bool) ($row['area_not_accessed'] ?? false),
                'notes' => $row['notes'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ];

            if (! empty($row['id'])) {
                $section = LeadSurveyMeasurementSection::query()
                    ->where('lead_survey_id', $survey->id)
                    ->whereKey((int) $row['id'])
                    ->first();
                if ($section) {
                    $section->update($payload);
                    $keepIds[] = $section->id;
                    continue;
                }
            }

            $created = $survey->measurementSections()->create($payload);
            $keepIds[] = $created->id;
        }

        LeadSurveyMeasurementSection::query()
            ->where('lead_survey_id', $survey->id)
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->when($keepIds === [], fn ($q) => $q)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyPayload(LeadSurvey $survey, array $data, User $actor, bool $allowStepWrite = true): void
    {
        if ($allowStepWrite && isset($data['current_step']) && in_array($data['current_step'], self::STEPS, true)) {
            $survey->current_step = $data['current_step'];
        }

        foreach ([
            'survey_date',
            'confirmed_address',
            'property_type',
            'occupancy_type',
            'number_of_floors',
            'approx_construction_year',
            'occupied',
            'homeowner_present',
            'general_condition',
            'location_confirmed',
            'discrepancy_notes',
            'surveyed_floor_area_m2',
            'surveyed_installation_area_m2',
            'measurement_method',
            'measurement_confidence',
            'measurement_date',
            'measurement_notes',
            'surveyor_recommendation',
            'seller_visible_notes',
            'buyer_visible_notes',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $survey->{$field} = $data[$field];
            }
        }

        foreach (['access', 'no_access', 'loft_hatch', 'scheme_inspection', 'homeowner_confirmation', 'risks'] as $jsonField) {
            if (array_key_exists($jsonField, $data)) {
                $survey->{$jsonField} = $data[$jsonField];
            }
        }

        if ($actor->can(\App\Support\Permissions::REVIEW_SURVEYS) || $actor->hasRole('super_admin')) {
            if (array_key_exists('internal_audit_notes', $data)) {
                $survey->internal_audit_notes = $data['internal_audit_notes'];
            }
        }

        $survey->surveyor_user_id ??= $actor->id;
    }

    /**
     * @return array<string, string>
     */
    private function validatePropertyStep(LeadSurvey $survey): array
    {
        $errors = [];
        if (! $this->filled($survey->survey_date)) {
            $errors['survey_date'] = __('rml.survey.errors.field_required', ['field' => __('rml.survey.fields.survey_date')]);
        }
        if (! $this->filled($survey->confirmed_address)) {
            $errors['confirmed_address'] = __('rml.survey.errors.confirmed_address_required');
        }
        if (! $this->filled($survey->property_type)) {
            $errors['property_type'] = __('rml.survey.errors.field_required', ['field' => __('rml.survey.fields.property_type')]);
        }
        if (! $this->filled($survey->occupancy_type)) {
            $errors['occupancy_type'] = __('rml.survey.errors.field_required', ['field' => __('rml.survey.fields.occupancy_type')]);
        }
        if ($survey->occupied === null) {
            $errors['occupied'] = __('rml.survey.errors.field_required', ['field' => __('rml.survey.fields.occupied')]);
        }
        if ($survey->homeowner_present === null) {
            $errors['homeowner_present'] = __('rml.survey.errors.field_required', ['field' => __('rml.survey.fields.homeowner_present')]);
        }
        if (! $this->filled($survey->general_condition)) {
            $errors['general_condition'] = __('rml.survey.errors.field_required', ['field' => __('rml.survey.fields.general_condition')]);
        }
        if ($survey->location_confirmed !== true) {
            $errors['location_confirmed'] = __('rml.survey.errors.field_required', ['field' => __('rml.survey.fields.location_confirmed')]);
        }

        $access = is_array($survey->access) ? $survey->access : [];
        foreach (['internal_access', 'external_access', 'access_safe'] as $key) {
            if (! array_key_exists($key, $access) || $access[$key] === null || $access[$key] === '') {
                $errors["access.$key"] = __('rml.survey.errors.field_required', [
                    'field' => __('rml.survey.access_fields.'.$key),
                ]);
            }
        }

        $blocked = (($access['access_safe'] ?? true) === false)
            || (bool) ($access['keys_unavailable'] ?? false)
            || (($access['internal_access'] ?? true) === false && ($access['external_access'] ?? true) === false);

        if ($blocked || (($access['loft_access'] ?? null) === false)) {
            $noAccess = is_array($survey->no_access) ? $survey->no_access : [];
            if (! $this->filled($noAccess['area'] ?? null)) {
                $errors['no_access.area'] = __('rml.survey.errors.no_access_details_required');
            }
            if (! $this->filled($noAccess['reason'] ?? null)) {
                $errors['no_access.reason'] = __('rml.survey.errors.no_access_details_required');
            }
            if (! $this->filled($noAccess['refused_or_impossible'] ?? null)) {
                $errors['no_access.refused_or_impossible'] = __('rml.survey.errors.no_access_details_required');
            }
        }

        if ((bool) ($access['return_visit_required'] ?? false)) {
            $noAccess = is_array($survey->no_access) ? $survey->no_access : [];
            if (! $this->filled($noAccess['explanation'] ?? null) && ! $this->filled($noAccess['next_action'] ?? null)) {
                $errors['no_access.explanation'] = __('rml.survey.errors.return_visit_explanation_required');
            }
        }

        $slug = $survey->lead?->scheme?->slug;
        if ($slug === 'insulation') {
            $hatch = is_array($survey->loft_hatch) ? $survey->loft_hatch : [];
            if (! $this->filled($hatch['existing_hatch'] ?? null)) {
                $errors['loft_hatch.existing_hatch'] = __('rml.survey.errors.field_required', [
                    'field' => __('rml.survey.fields.existing_hatch'),
                ]);
            }
            if (($hatch['new_hatch_required'] ?? false) === true || ($hatch['new_hatch_required'] ?? '') === 'yes') {
                if (! $this->filled($hatch['proposed_location'] ?? null)) {
                    $errors['loft_hatch.proposed_location'] = __('rml.survey.errors.hatch_location_required');
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateMeasurementsStep(LeadSurvey $survey): array
    {
        $errors = [];
        $install = $survey->surveyed_installation_area_m2;
        if ($install === null || (float) $install <= 0) {
            $errors['surveyed_installation_area_m2'] = __('rml.survey.errors.measurement_required');
        }
        if (! $this->filled($survey->measurement_method?->value ?? $survey->measurement_method)) {
            $errors['measurement_method'] = __('rml.survey.errors.field_required', [
                'field' => __('rml.survey.fields.measurement_method'),
            ]);
        }
        if (! $this->filled($survey->measurement_confidence)) {
            $errors['measurement_confidence'] = __('rml.survey.errors.field_required', [
                'field' => __('rml.survey.fields.measurement_confidence'),
            ]);
        }
        if (! $this->filled($survey->measurement_date)) {
            $errors['measurement_date'] = __('rml.survey.errors.field_required', [
                'field' => __('rml.survey.fields.measurement_date'),
            ]);
        }

        if ($survey->measurementSections->isEmpty()) {
            $errors['measurement_sections'] = __('rml.survey.errors.measurement_section_required');
        } else {
            foreach ($survey->measurementSections as $index => $section) {
                if ($section->area_not_accessed && ! $section->is_estimate) {
                    $errors["measurement_sections.$index.is_estimate"] = __('rml.survey.errors.estimate_required_when_not_accessed');
                }
                $area = $section->manual_area_m2 ?? $section->calculated_area_m2;
                if (! $section->area_not_accessed && ($area === null || (float) $area <= 0)) {
                    $errors["measurement_sections.$index.calculated_area_m2"] = __('rml.survey.errors.measurement_required');
                }
                if ($section->manual_area_m2 !== null && ! $this->filled($section->notes)) {
                    $errors["measurement_sections.$index.notes"] = __('rml.survey.errors.manual_area_reason_required');
                }
                if (! $this->filled($section->section_type)) {
                    $errors["measurement_sections.$index.section_type"] = __('rml.survey.errors.field_required', [
                        'field' => __('rml.survey.fields.section_type'),
                    ]);
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateSchemeStep(LeadSurvey $survey): array
    {
        $errors = [];
        $slug = $survey->lead?->scheme?->slug;
        $inspection = is_array($survey->scheme_inspection) ? $survey->scheme_inspection : [];

        if ($slug === 'insulation') {
            if (! $this->filled($inspection['proposed_insulation_type'] ?? null)) {
                $errors['scheme_inspection.proposed_insulation_type'] = __('rml.survey.errors.insulation_type_required');
            }
            if (! $this->filled($inspection['existing_insulation_present'] ?? null)) {
                $errors['scheme_inspection.existing_insulation_present'] = __('rml.survey.errors.field_required', [
                    'field' => __('rml.survey.fields.existing_insulation_present'),
                ]);
            }
            if (! $this->filled($inspection['suitable_for_installation'] ?? null)) {
                $errors['scheme_inspection.suitable_for_installation'] = __('rml.survey.errors.field_required', [
                    'field' => __('rml.survey.fields.installation_suitability'),
                ]);
            }
        } elseif ($slug === 'double-glazing') {
            if (! $this->filled($inspection['current_glazing_type'] ?? null)) {
                $errors['scheme_inspection.current_glazing_type'] = __('rml.survey.errors.glazing_type_required');
            }
            if (! $this->filled($inspection['frame_material'] ?? null)) {
                $errors['scheme_inspection.frame_material'] = __('rml.survey.errors.field_required', [
                    'field' => __('rml.survey.fields.frame_material'),
                ]);
            }
            if (! $this->filled($inspection['replacement_suitability'] ?? null)) {
                $errors['scheme_inspection.replacement_suitability'] = __('rml.survey.errors.field_required', [
                    'field' => __('rml.survey.fields.replacement_suitability'),
                ]);
            }
        } elseif ($slug === 'heat-pumps') {
            if (! $this->filled($inspection['current_heating_system'] ?? null)) {
                $errors['scheme_inspection.current_heating_system'] = __('rml.survey.errors.heating_system_required');
            }
            if (! $this->filled($inspection['proposed_heat_pump_type'] ?? null)) {
                $errors['scheme_inspection.proposed_heat_pump_type'] = __('rml.survey.errors.field_required', [
                    'field' => __('rml.survey.fields.proposed_heat_pump_type'),
                ]);
            }
            if (! $this->filled($inspection['suitable_for_installation'] ?? null)) {
                $errors['scheme_inspection.suitable_for_installation'] = __('rml.survey.errors.field_required', [
                    'field' => __('rml.survey.fields.installation_suitability'),
                ]);
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateEvidenceStep(LeadSurvey $survey): array
    {
        $errors = [];
        $requiredCategories = ['front_exterior', 'installation_area'];
        $hatch = is_array($survey->loft_hatch) ? $survey->loft_hatch : [];
        if (($hatch['new_hatch_required'] ?? false) === true || ($hatch['new_hatch_required'] ?? '') === 'yes') {
            $requiredCategories[] = 'loft_hatch';
        }

        $access = is_array($survey->access) ? $survey->access : [];
        $blocked = (($access['access_safe'] ?? true) === false)
            || (bool) ($access['keys_unavailable'] ?? false);
        if ($blocked) {
            $requiredCategories[] = 'property_access';
        }

        $present = $survey->evidence->pluck('category')->unique()->all();
        foreach (array_unique($requiredCategories) as $category) {
            if (! in_array($category, $present, true)) {
                $errors["evidence.$category"] = __('rml.survey.errors.evidence_required', ['category' => $category]);
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateHomeownerStep(LeadSurvey $survey): array
    {
        $errors = [];
        $homeowner = is_array($survey->homeowner_confirmation) ? $survey->homeowner_confirmation : [];
        foreach (['name', 'relationship', 'confirmation_date'] as $key) {
            if (! $this->filled($homeowner[$key] ?? null)) {
                $errors["homeowner_confirmation.$key"] = __('rml.survey.errors.field_required', [
                    'field' => __('rml.survey.fields.'.($key === 'name' ? 'homeowner_name' : $key)),
                ]);
            }
        }
        if (empty($homeowner['permission_to_inspect'])) {
            $errors['homeowner_confirmation.permission_to_inspect'] = __('rml.survey.errors.homeowner_permission_required');
        }
        if (empty($homeowner['permission_evidence'])) {
            $errors['homeowner_confirmation.permission_evidence'] = __('rml.survey.errors.field_required', [
                'field' => __('rml.survey.fields.permission_evidence'),
            ]);
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateRisksStep(LeadSurvey $survey): array
    {
        $errors = [];
        if (! $this->filled($survey->surveyor_recommendation)) {
            $errors['surveyor_recommendation'] = __('rml.survey.errors.field_required', [
                'field' => __('rml.survey.fields.surveyor_recommendation'),
            ]);
        }

        $risks = is_array($survey->risks) ? $survey->risks : [];
        $codes = $risks['codes'] ?? [];
        if (is_array($codes) && $codes !== []) {
            if (! $this->filled($risks['severity'] ?? null)) {
                $errors['risks.severity'] = __('rml.survey.errors.risk_severity_required');
            }
            if (! $this->filled($risks['affected_section'] ?? null)) {
                $errors['risks.affected_section'] = __('rml.survey.errors.risk_section_required');
            }
        }

        return $errors;
    }

    private function filled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function validateForSubmit(LeadSurvey $survey): array
    {
        $errors = [];
        foreach (['property', 'measurements', 'scheme', 'evidence', 'homeowner', 'risks'] as $step) {
            $errors = array_merge($errors, $this->validateStep($survey, $step));
        }

        return $errors;
    }

    private function recordVersion(LeadSurvey $survey, User $actor, string $event, ?string $notes): void
    {
        LeadSurveyVersion::query()->create([
            'lead_survey_id' => $survey->id,
            'version' => $survey->version,
            'status' => $survey->status->value,
            'event' => $event,
            'snapshot' => $survey->withoutRelations()->toArray(),
            'actor_user_id' => $actor->id,
            'notes' => $notes,
        ]);
    }

    /**
     * @return list<string>
     */
    /**
     * Full Catastro snapshot for admin / auditor survey review.
     *
     * @return array<string, mixed>
     */
    private function presentCatastroInternal(?LeadCatastroSnapshot $catastro): array
    {
        return [
            'provider' => $catastro?->provider?->value ?? CatastroProvider::Unknown->value,
            'verification_status' => $catastro?->verification_status?->value
                ?? CatastroVerificationStatus::NotChecked->value,
            'cadastral_reference' => $catastro?->cadastral_reference,
            'cadastral_address' => $catastro?->cadastral_address,
            'property_use' => $catastro?->property_use,
            'constructed_area_m2' => $catastro?->constructed_area_m2 !== null
                ? (float) $catastro->constructed_area_m2
                : null,
            'parcel_area_m2' => $catastro?->parcel_area_m2 !== null
                ? (float) $catastro->parcel_area_m2
                : null,
            'construction_year' => $catastro?->construction_year,
            'province' => $catastro?->province,
            'municipality' => $catastro?->municipality,
            'floor' => $catastro?->floor,
            'door' => $catastro?->door,
            'geometry' => $catastro?->geometry,
            'match_summary' => $catastro?->match_summary,
            'warnings' => $catastro?->warnings ?? [],
            'lookup_at' => optional($catastro?->lookup_at)?->toIso8601String(),
            'is_official_ownership_proof' => false,
            'read_only' => true,
        ];
    }

    /**
     * Seller survey: simplified status + submitted reference only.
     *
     * @return array<string, mixed>
     */
    private function presentCatastroForSeller(?Lead $lead, ?LeadCatastroSnapshot $catastro): array
    {
        $sellerStatus = $lead
            ? app(CatastroLookupService::class)->sellerFacingStatus($lead, $catastro)
            : ['key' => 'not_checked', 'label_key' => 'not_checked'];

        return [
            'seller_status' => $sellerStatus,
            'cadastral_reference' => $lead?->cadastral_reference,
            'is_official_ownership_proof' => false,
            'read_only' => true,
        ];
    }

    private function comparisonFlags(
        ?float $submitted,
        ?float $cadastral,
        ?float $floor,
        ?float $installation,
    ): array {
        $flags = [];
        $threshold = 0.15;

        $pairs = [
            'submitted_vs_cadastral' => [$submitted, $cadastral],
            'submitted_vs_survey_floor' => [$submitted, $floor],
            'cadastral_vs_survey_floor' => [$cadastral, $floor],
            'survey_floor_vs_installation' => [$floor, $installation],
        ];

        foreach ($pairs as $key => [$a, $b]) {
            if ($a === null || $b === null || $a <= 0) {
                continue;
            }
            $delta = abs($a - $b) / $a;
            if ($delta >= $threshold) {
                $flags[] = $key;
            }
        }

        return $flags;
    }
}
