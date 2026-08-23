<?php

namespace App\Services\Auditor;

use App\Enums\AuditDecisionStatus;
use App\Enums\LeadStatus;
use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Enums\UserRole;
use App\Models\AuditChecklistItem;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\LeadAuditChecklistResult;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Admin\LeadAuditService;
use App\Services\AuditLogService;
use App\Support\Permissions;
use App\Support\ReferenceGenerator;
use App\Support\RejectionReasons;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditWorkflowService
{
    public function __construct(
        private readonly LeadAuditService $leadAuditService = new LeadAuditService,
        private readonly AuditLogService $auditLogService = new AuditLogService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function checklistItems(?int $schemeId = null): array
    {
        return $this->leadAuditService->checklistItems($schemeId);
    }

    public function assignedAuditsQuery(User $actor): Builder
    {
        $query = LeadAudit::query()->with([
            'lead.scheme:id,name',
            'lead.zone:id,code',
            'lead.sellerCompany:id,name',
            'lead.submittedBy:id,name',
            'lead.evidenceFiles',
        ]);

        if ($this->canViewAllAssignments($actor)) {
            return $query;
        }

        return $query->where('auditor_user_id', $actor->id);
    }

    public function ensureAssignedAudit(User $actor, Lead $lead): LeadAudit
    {
        $audit = LeadAudit::query()
            ->where('lead_id', $lead->id)
            ->latest('id')
            ->first();

        if (! $audit) {
            if (! $this->canViewAllAssignments($actor)) {
                abort(403);
            }

            $audit = LeadAudit::query()->create([
                'lead_id' => $lead->id,
                'auditor_user_id' => $actor->id,
                'status' => AuditDecisionStatus::Pending,
            ]);
        }

        if (! $this->canAccessAudit($actor, $audit)) {
            abort(403);
        }

        if (
            $audit->status === AuditDecisionStatus::Pending
            && (int) $audit->auditor_user_id === (int) $actor->id
        ) {
            $audit->update(['status' => AuditDecisionStatus::InReview]);
        }

        return $audit->fresh(['checklistResults', 'auditor', 'finalDecisionBy']);
    }

    public function assignAuditor(User $actor, Lead $lead, User $auditor): LeadAudit
    {
        if (
            ! $actor->hasRole(UserRole::SuperAdmin->value)
            && ! $actor->can(Permissions::ACCEPT_REJECT_LEADS)
        ) {
            abort(403);
        }

        if (! $auditor->hasRole(UserRole::InternalAuditor->value) && ! $auditor->can(Permissions::AUDIT_LEADS)) {
            throw ValidationException::withMessages([
                'auditor_user_id' => __('rml.auditor.assignment.invalid_auditor'),
            ]);
        }

        return DB::transaction(function () use ($actor, $lead, $auditor) {
            $audit = LeadAudit::query()
                ->where('lead_id', $lead->id)
                ->latest('id')
                ->first();

            if (! $audit) {
                $audit = new LeadAudit(['lead_id' => $lead->id]);
            }

            $old = $audit->auditor_user_id;
            $audit->fill([
                'auditor_user_id' => $auditor->id,
                'status' => $audit->status ?? AuditDecisionStatus::Pending,
            ]);
            $audit->save();

            if ($lead->status === LeadStatus::Submitted) {
                $lead->update(['status' => LeadStatus::PendingValidation]);
            }

            $this->auditLogService->log(
                'lead.auditor_assigned',
                $lead,
                ['auditor_user_id' => $old],
                ['auditor_user_id' => $auditor->id, 'audit_id' => $audit->id],
                $actor,
            );

            return $audit->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveChecklist(User $actor, Lead $lead, array $data): LeadAudit
    {
        return DB::transaction(function () use ($actor, $lead, $data) {
            $audit = $this->ensureAssignedAudit($actor, $lead);
            $this->assertNotFinalised($audit);

            if (! empty($data['audit_notes'])) {
                $audit->audit_notes = $data['audit_notes'];
            }

            if ($audit->status === AuditDecisionStatus::Pending) {
                $audit->status = AuditDecisionStatus::InReview;
            }

            $audit->save();
            $this->syncChecklist($audit, $data['checklist'] ?? []);

            $this->auditLogService->log(
                'lead.audit_checklist_saved',
                $lead,
                null,
                [
                    'audit_id' => $audit->id,
                    'checklist_count' => is_countable($data['checklist'] ?? null)
                        ? count($data['checklist'])
                        : 0,
                ],
                $actor,
            );

            return $audit->fresh(['checklistResults']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recommendAccept(User $actor, Lead $lead, array $data): LeadAudit
    {
        return DB::transaction(function () use ($actor, $lead, $data) {
            $audit = $this->ensureAssignedAudit($actor, $lead);
            $this->assertNotFinalised($audit);
            $this->assertRequiredChecklist($audit, $data['checklist'] ?? []);

            $this->syncChecklist($audit, $data['checklist'] ?? []);

            $audit->fill([
                'status' => AuditDecisionStatus::RecommendedAccept,
                'audit_notes' => $data['audit_notes'] ?? $audit->audit_notes,
                'rejection_reason' => null,
                'requested_info' => null,
                'completed_at' => now(),
            ])->save();

            if (in_array($lead->status, [
                LeadStatus::Submitted,
                LeadStatus::PendingEvidence,
                LeadStatus::PendingValidation,
                LeadStatus::Validating,
                LeadStatus::NeedsMoreInformation,
            ], true)) {
                $lead->update(['status' => LeadStatus::Validating]);
            }

            $this->notifyInternal(
                $actor,
                $lead,
                'Audit recommendation: accept',
                'Auditor recommended accept for '.$lead->lead_reference,
                MessageThreadCategory::LeadReview,
            );

            $this->auditLogService->log(
                'lead.audit_recommended_accept',
                $lead,
                null,
                ['audit_id' => $audit->id, 'status' => AuditDecisionStatus::RecommendedAccept->value],
                $actor,
            );

            return $audit->fresh(['checklistResults']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recommendReject(User $actor, Lead $lead, array $data): LeadAudit
    {
        $reasonCode = trim((string) ($data['reason_code'] ?? ''));
        $comment = trim((string) ($data['comment'] ?? '')) ?: null;

        if ($reasonCode === '' || ! in_array($reasonCode, RejectionReasons::codes(RejectionReasons::CONTEXT_LEADS), true)) {
            throw ValidationException::withMessages([
                'reason_code' => __('rml.rejection.reason_required'),
            ]);
        }

        if ($reasonCode === RejectionReasons::OTHER && ($comment === null || $comment === '')) {
            throw ValidationException::withMessages([
                'comment' => __('rml.rejection.comment_required_other'),
            ]);
        }

        $label = RejectionReasons::label(RejectionReasons::CONTEXT_LEADS, $reasonCode);
        $message = RejectionReasons::composeMessage(
            RejectionReasons::CONTEXT_LEADS,
            $reasonCode,
            $comment,
        );

        return DB::transaction(function () use ($actor, $lead, $data, $reasonCode, $label, $comment, $message) {
            $audit = $this->ensureAssignedAudit($actor, $lead);
            $this->assertNotFinalised($audit);
            $this->syncChecklist($audit, $data['checklist'] ?? []);

            $audit->fill([
                'status' => AuditDecisionStatus::RecommendedReject,
                'audit_notes' => $data['audit_notes'] ?? $audit->audit_notes,
                'rejection_reason' => $message,
                'requested_info' => null,
                'completed_at' => now(),
            ])->save();

            $this->notifyInternal(
                $actor,
                $lead,
                __('rml.auditor.audit.recommend_reject_thread_subject'),
                $message,
                MessageThreadCategory::LeadReview,
            );

            $this->auditLogService->log(
                'lead.audit_recommended_reject',
                $lead,
                null,
                [
                    'audit_id' => $audit->id,
                    'status' => AuditDecisionStatus::RecommendedReject->value,
                    'reason_code' => $reasonCode,
                    'reason' => $label,
                    'comment' => $comment,
                ],
                $actor,
            );

            return $audit->fresh(['checklistResults']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestMoreInformation(User $actor, Lead $lead, array $data): LeadAudit
    {
        $info = trim((string) ($data['requested_info'] ?? ''));
        if ($info === '') {
            throw ValidationException::withMessages([
                'requested_info' => __('rml.auditor.audit.info_required'),
            ]);
        }

        return DB::transaction(function () use ($actor, $lead, $data, $info) {
            $audit = $this->ensureAssignedAudit($actor, $lead);
            $this->assertNotFinalised($audit);
            $this->syncChecklist($audit, $data['checklist'] ?? []);

            $audit->fill([
                'status' => AuditDecisionStatus::NeedsMoreInformation,
                'audit_notes' => $data['audit_notes'] ?? $audit->audit_notes,
                'requested_info' => $info,
                'rejection_reason' => null,
                'completed_at' => now(),
            ])->save();

            $lead->update(['status' => LeadStatus::NeedsMoreInformation]);

            $this->notifySeller($actor, $lead, $info);
            $this->notifyInternal(
                $actor,
                $lead,
                'More information requested',
                $info,
                MessageThreadCategory::EvidenceIssue,
            );

            $this->auditLogService->log(
                'lead.audit_info_requested',
                $lead,
                null,
                [
                    'audit_id' => $audit->id,
                    'status' => AuditDecisionStatus::NeedsMoreInformation->value,
                    'requested_info' => $info,
                    'by' => 'auditor',
                ],
                $actor,
            );

            return $audit->fresh(['checklistResults']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestReSurvey(User $actor, Lead $lead, array $data): LeadAudit
    {
        $reason = trim((string) ($data['requested_info'] ?? $data['comment'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'requested_info' => __('rml.auditor.audit.re_survey_reason_required'),
            ]);
        }

        return DB::transaction(function () use ($actor, $lead, $data, $reason) {
            $audit = $this->ensureAssignedAudit($actor, $lead);
            $previous = $audit->status?->value;
            $this->assertNotFinalised($audit);
            $this->syncChecklist($audit, $data['checklist'] ?? []);

            $audit->fill([
                'status' => AuditDecisionStatus::ReSurveyRequired,
                'audit_notes' => $data['audit_notes'] ?? $audit->audit_notes,
                'requested_info' => $reason,
                'rejection_reason' => null,
                'completed_at' => now(),
            ])->save();

            $lead->update(['status' => LeadStatus::NeedsMoreInformation]);

            $lead->loadMissing('survey');
            if ($lead->survey && in_array($lead->survey->status, [
                \App\Enums\SurveyStatus::Submitted,
                \App\Enums\SurveyStatus::UnderReview,
                \App\Enums\SurveyStatus::Resubmitted,
            ], true)) {
                app(\App\Services\Survey\LeadSurveyService::class)->requestCorrection($lead->survey, $actor, [
                    'correction_request' => $reason,
                    'correction_sections' => $data['correction_sections'] ?? ['measurements', 'evidence'],
                ]);
            } elseif ($lead->survey && $lead->survey->status->isEditableBySurveyor()) {
                $lead->survey->forceFill([
                    'correction_request' => $reason,
                    'correction_sections' => $data['correction_sections'] ?? ['measurements', 'evidence'],
                ])->save();
            }

            $this->notifySeller($actor, $lead, $reason);
            $this->notifyInternal(
                $actor,
                $lead,
                'Pre-installation re-survey required',
                $reason,
                MessageThreadCategory::EvidenceIssue,
            );

            $this->auditLogService->log(
                'lead.pre_installation_re_survey_required',
                $lead,
                null,
                [
                    'audit_id' => $audit->id,
                    'previous_status' => $previous,
                    'status' => AuditDecisionStatus::ReSurveyRequired->value,
                    'requested_info' => $reason,
                    'by' => 'auditor',
                ],
                $actor,
            );

            return $audit->fresh(['checklistResults']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestManualVerification(User $actor, Lead $lead, array $data): LeadAudit
    {
        $reason = trim((string) ($data['requested_info'] ?? $data['comment'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'requested_info' => __('rml.auditor.audit.manual_verification_reason_required'),
            ]);
        }

        return DB::transaction(function () use ($actor, $lead, $data, $reason) {
            $audit = $this->ensureAssignedAudit($actor, $lead);
            $previous = $audit->status?->value;
            $this->assertNotFinalised($audit);
            $this->syncChecklist($audit, $data['checklist'] ?? []);

            $audit->fill([
                'status' => AuditDecisionStatus::ManualVerificationRequired,
                'audit_notes' => $data['audit_notes'] ?? $audit->audit_notes,
                'requested_info' => $reason,
                'rejection_reason' => null,
                'completed_at' => now(),
            ])->save();

            $this->notifyInternal(
                $actor,
                $lead,
                'Pre-installation manual verification required',
                $reason,
                MessageThreadCategory::LeadReview,
            );

            $this->auditLogService->log(
                'lead.pre_installation_manual_verification_required',
                $lead,
                null,
                [
                    'audit_id' => $audit->id,
                    'previous_status' => $previous,
                    'status' => AuditDecisionStatus::ManualVerificationRequired->value,
                    'requested_info' => $reason,
                    'by' => 'auditor',
                ],
                $actor,
            );

            return $audit->fresh(['checklistResults']);
        });
    }

    public function canAccessAudit(User $actor, LeadAudit $audit): bool
    {
        if ($this->canViewAllAssignments($actor)) {
            return true;
        }

        return (int) $audit->auditor_user_id === (int) $actor->id;
    }

    public function canViewAllAssignments(User $actor): bool
    {
        return $actor->hasRole(UserRole::SuperAdmin->value)
            || ($actor->hasRole(UserRole::AdminStaff->value) && $actor->can(Permissions::AUDIT_LEADS));
    }

    private function assertNotFinalised(LeadAudit $audit): void
    {
        if (in_array($audit->status, [AuditDecisionStatus::Accepted, AuditDecisionStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'status' => __('rml.auditor.audit.finalised'),
            ]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $checklist
     */
    private function assertRequiredChecklist(LeadAudit $audit, array $checklist): void
    {
        $this->syncChecklist($audit, $checklist);
        $audit->load('checklistResults');

        $schemeId = $audit->lead?->scheme_id ?? Lead::query()->whereKey($audit->lead_id)->value('scheme_id');

        $requiredQuery = AuditChecklistItem::query()
            ->where('active', true)
            ->where('required', true);

        if ($schemeId) {
            $schemeRequired = (clone $requiredQuery)
                ->where('scheme_id', $schemeId)
                ->pluck('id');

            $requiredIds = $schemeRequired->isNotEmpty()
                ? $schemeRequired
                : $requiredQuery->whereNull('scheme_id')->pluck('id');
        } else {
            $requiredIds = $requiredQuery->whereNull('scheme_id')->pluck('id');
        }

        $checked = $audit->checklistResults
            ->where('checked', true)
            ->pluck('audit_checklist_item_id');

        $missing = $requiredIds->diff($checked);
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'checklist' => __('rml.auditor.audit.checklist_incomplete'),
            ]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $checklist
     */
    private function syncChecklist(LeadAudit $audit, array $checklist): void
    {
        foreach ($checklist as $itemId => $value) {
            $id = is_array($value) ? (int) ($value['id'] ?? $itemId) : (int) $itemId;
            $checked = is_array($value) ? (bool) ($value['checked'] ?? false) : (bool) $value;
            $notes = is_array($value) ? ($value['notes'] ?? null) : null;

            if ($id <= 0) {
                continue;
            }

            LeadAuditChecklistResult::query()->updateOrCreate(
                [
                    'lead_audit_id' => $audit->id,
                    'audit_checklist_item_id' => $id,
                ],
                [
                    'checked' => $checked,
                    'notes' => $notes,
                ],
            );
        }
    }

    private function notifySeller(User $actor, Lead $lead, string $body): void
    {
        $sellerId = $lead->submitted_by_user_id;
        if (! $sellerId) {
            return;
        }

        $thread = MessageThread::query()->create([
            'thread_reference' => ReferenceGenerator::thread(),
            'subject' => 'More information requested — '.$lead->lead_reference,
            'category' => MessageThreadCategory::InformationRequest,
            'status' => MessageThreadStatus::Open,
            'created_by_user_id' => $sellerId,
            'assigned_to_user_id' => $actor->id,
            'related_lead_id' => $lead->id,
        ]);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'sender_user_id' => $actor->id,
            'body' => $body,
        ]);
    }

    private function notifyInternal(
        User $actor,
        Lead $lead,
        string $subject,
        string $body,
        MessageThreadCategory $category,
    ): void {
        $admin = User::query()
            ->role(UserRole::SuperAdmin->value)
            ->orderBy('id')
            ->first();

        $thread = MessageThread::query()->create([
            'thread_reference' => ReferenceGenerator::thread(),
            'subject' => $subject.' — '.$lead->lead_reference,
            'category' => $category,
            'status' => MessageThreadStatus::Open,
            'created_by_user_id' => $actor->id,
            'assigned_to_user_id' => $admin?->id,
            'related_lead_id' => $lead->id,
        ]);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'sender_user_id' => $actor->id,
            'body' => $body,
        ]);
    }
}
