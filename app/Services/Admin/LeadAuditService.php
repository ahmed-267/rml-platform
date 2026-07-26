<?php

namespace App\Services\Admin;

use App\Enums\AuditDecisionStatus;
use App\Enums\LeadStatus;
use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Enums\UserRole;
use App\Mail\SellerLeadStatusMail;
use App\Models\AuditChecklistItem;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\LeadAuditChecklistResult;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\LeadPricingService;
use App\Support\Permissions;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class LeadAuditService
{
    public function __construct(
        private readonly LeadPricingService $leadPricingService = new LeadPricingService,
        private readonly AuditLogService $auditLogService = new AuditLogService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function checklistItems(?int $schemeId = null): array
    {
        $query = AuditChecklistItem::query()->where('active', true);

        if ($schemeId) {
            $schemeItems = (clone $query)
                ->where('scheme_id', $schemeId)
                ->orderBy('sort_order')
                ->get();

            if ($schemeItems->isNotEmpty()) {
                return $schemeItems
                    ->map(fn (AuditChecklistItem $item) => [
                        'id' => $item->id,
                        'key' => $item->key,
                        'label' => $item->label,
                        'required' => (bool) $item->required,
                    ])
                    ->values()
                    ->all();
            }
        }

        return $query
            ->whereNull('scheme_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (AuditChecklistItem $item) => [
                'id' => $item->id,
                'key' => $item->key,
                'label' => $item->label,
                'required' => (bool) $item->required,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function pricingPreview(Lead $lead): array
    {
        $calc = $this->leadPricingService->calculate($lead);

        $suggestedBuying = $lead->buying_price !== null
            ? (float) $lead->buying_price
            : $this->leadPricingService->suggestedSellerPayout($lead);
        $suggestedSelling = $lead->selling_price !== null
            ? (float) $lead->selling_price
            : $calc['selling_price'];

        return [
            'suggested_selling_price' => $calc['selling_price'],
            'suggested_buying_price' => $this->leadPricingService->suggestedSellerPayout($lead),
            'seller_payout_per_m2' => LeadPricingService::SELLER_PAYOUT_PER_M2,
            'price_per_m2' => $calc['price_per_m2'],
            'size_m2' => $calc['size_m2'],
            'zone_code' => $calc['zone_code'],
            'formula' => $calc['formula'],
            'buying_price' => $suggestedBuying,
            'selling_price' => $suggestedSelling,
            'expected_margin' => $lead->expected_margin !== null
                ? (float) $lead->expected_margin
                : (
                    $suggestedSelling !== null && $suggestedBuying !== null
                        ? round((float) $suggestedSelling - (float) $suggestedBuying, 2)
                        : null
                ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function accept(User $actor, Lead $lead, array $data): LeadAudit
    {
        $this->assertCanDecide($actor);

        return DB::transaction(function () use ($actor, $lead, $data) {
            $lead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $pricing = $this->resolvePrices($actor, $lead, $data);

            $audit = $this->upsertAudit($actor, $lead, [
                'status' => AuditDecisionStatus::Accepted,
                'audit_notes' => $data['audit_notes'] ?? null,
                'buying_price' => $pricing['buying_price'],
                'selling_price' => $pricing['selling_price'],
                'suggested_price' => $pricing['suggested_price'],
                'expected_margin' => $pricing['expected_margin'],
                'override_reason' => $pricing['override_reason'],
                'rejection_reason' => null,
                'requested_info' => null,
                'final_decision_by_user_id' => $actor->id,
                'completed_at' => now(),
            ]);

            $this->assertRequiredChecklist($audit, $lead, $data['checklist'] ?? []);

            $lead->fill([
                'status' => LeadStatus::Listed,
                'buying_price' => $pricing['buying_price'],
                'selling_price' => $pricing['selling_price'],
                'expected_margin' => $pricing['expected_margin'],
                'accepted_at' => $lead->accepted_at ?? now(),
                'listed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->auditLogService->log(
                'lead.audit_accepted',
                $lead,
                null,
                [
                    'status' => LeadStatus::Listed->value,
                    'buying_price' => $pricing['buying_price'],
                    'selling_price' => $pricing['selling_price'],
                    'audit_id' => $audit->id,
                ],
                $actor,
            );

            $this->emailSeller($lead, 'accepted');

            return $audit->fresh(['checklistResults', 'lead']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reject(User $actor, Lead $lead, array $data): LeadAudit
    {
        $this->assertCanDecide($actor);

        $reason = trim((string) ($data['rejection_reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => __('rml.admin.audit.rejection_required'),
            ]);
        }

        return DB::transaction(function () use ($actor, $lead, $data, $reason) {
            $lead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            $audit = $this->upsertAudit($actor, $lead, [
                'status' => AuditDecisionStatus::Rejected,
                'audit_notes' => $data['audit_notes'] ?? null,
                'rejection_reason' => $reason,
                'requested_info' => null,
                'final_decision_by_user_id' => $actor->id,
                'completed_at' => now(),
            ]);

            $this->syncChecklist($audit, $data['checklist'] ?? []);

            $lead->fill([
                'status' => LeadStatus::Rejected,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
            ])->save();

            $this->notifySeller($actor, $lead, __('rml.admin.audit.rejected_thread_subject'), $reason, MessageThreadCategory::SellerIssue);
            $this->emailSeller($lead, 'rejected', $reason);

            $this->auditLogService->log(
                'lead.audit_rejected',
                $lead,
                null,
                ['status' => LeadStatus::Rejected->value, 'reason' => $reason, 'audit_id' => $audit->id],
                $actor,
            );

            return $audit->fresh(['checklistResults', 'lead']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestMoreInformation(User $actor, Lead $lead, array $data): LeadAudit
    {
        $this->assertCanDecide($actor);

        $info = trim((string) ($data['requested_info'] ?? ''));
        if ($info === '') {
            throw ValidationException::withMessages([
                'requested_info' => __('rml.admin.audit.info_required'),
            ]);
        }

        return DB::transaction(function () use ($actor, $lead, $data, $info) {
            $lead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            $audit = $this->upsertAudit($actor, $lead, [
                'status' => AuditDecisionStatus::NeedsMoreInformation,
                'audit_notes' => $data['audit_notes'] ?? null,
                'requested_info' => $info,
                'rejection_reason' => null,
                'final_decision_by_user_id' => $actor->id,
                'completed_at' => now(),
            ]);

            $this->syncChecklist($audit, $data['checklist'] ?? []);

            $lead->fill([
                'status' => LeadStatus::NeedsMoreInformation,
            ])->save();

            $this->notifySeller(
                $actor,
                $lead,
                __('rml.admin.audit.info_requested_thread_subject'),
                $info,
                MessageThreadCategory::InformationRequest,
            );
            $this->emailSeller($lead, 'info_requested', $info);

            $this->auditLogService->log(
                'lead.audit_info_requested',
                $lead,
                null,
                ['status' => LeadStatus::NeedsMoreInformation->value, 'requested_info' => $info, 'audit_id' => $audit->id],
                $actor,
            );

            return $audit->fresh(['checklistResults', 'lead']);
        });
    }

    private function assertCanDecide(User $actor): void
    {
        if (
            ! $actor->can(Permissions::ACCEPT_REJECT_LEADS)
            && ! $actor->can(Permissions::AUDIT_LEADS)
            && ! $actor->hasRole(UserRole::SuperAdmin->value)
        ) {
            abort(403);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{buying_price: float|null, selling_price: float|null, suggested_price: float|null, expected_margin: float|null, override_reason: string|null}
     */
    private function resolvePrices(User $actor, Lead $lead, array $data): array
    {
        $preview = $this->pricingPreview($lead);
        $suggested = $preview['suggested_selling_price'];
        $suggestedBuying = $preview['suggested_buying_price'] ?? $this->leadPricingService->suggestedSellerPayout($lead);
        $buying = array_key_exists('buying_price', $data) && $data['buying_price'] !== null && $data['buying_price'] !== ''
            ? (float) $data['buying_price']
            : ($lead->buying_price !== null
                ? (float) $lead->buying_price
                : ($suggestedBuying !== null ? (float) $suggestedBuying : null));
        $selling = array_key_exists('selling_price', $data) && $data['selling_price'] !== null && $data['selling_price'] !== ''
            ? (float) $data['selling_price']
            : ($suggested !== null ? (float) $suggested : ($lead->selling_price !== null ? (float) $lead->selling_price : null));

        $overrideReason = isset($data['override_reason']) ? trim((string) $data['override_reason']) : null;
        $isOverride = ($suggested !== null && $selling !== null && abs($selling - (float) $suggested) > 0.009)
            || ($suggestedBuying !== null && $buying !== null && abs($buying - (float) $suggestedBuying) > 0.009);

        if ($isOverride) {
            if (! $actor->can(Permissions::OVERRIDE_PRICING) && ! $actor->hasRole(UserRole::SuperAdmin->value)) {
                throw ValidationException::withMessages([
                    'override_reason' => __('rml.admin.audit.override_forbidden'),
                ]);
            }

            if ($overrideReason === null || $overrideReason === '') {
                throw ValidationException::withMessages([
                    'override_reason' => __('rml.admin.audit.override_required'),
                ]);
            }
        }

        $margin = ($selling !== null && $buying !== null)
            ? round($selling - $buying, 2)
            : null;

        return [
            'buying_price' => $buying,
            'selling_price' => $selling,
            'suggested_price' => $suggested !== null ? (float) $suggested : null,
            'expected_margin' => $margin,
            'override_reason' => $isOverride ? $overrideReason : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertAudit(User $actor, Lead $lead, array $attributes): LeadAudit
    {
        $audit = LeadAudit::query()
            ->where('lead_id', $lead->id)
            ->latest('id')
            ->first();

        if (! $audit) {
            $audit = new LeadAudit(['lead_id' => $lead->id]);
        }

        $audit->fill($attributes);

        if (! $audit->auditor_user_id) {
            $audit->auditor_user_id = $actor->id;
        }

        $audit->save();

        return $audit;
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

    /**
     * @param  array<int|string, mixed>  $checklist
     */
    private function assertRequiredChecklist(LeadAudit $audit, Lead $lead, array $checklist): void
    {
        $this->syncChecklist($audit, $checklist);
        $audit->load('checklistResults');

        $schemeId = $lead->scheme_id;

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
                'checklist' => __('rml.admin.audit.checklist_incomplete'),
            ]);
        }
    }

    /**
     * @param  'accepted'|'rejected'|'info_requested'  $event
     */
    private function emailSeller(Lead $lead, string $event, ?string $details = null): void
    {
        $lead->loadMissing('submittedBy:id,email,name');
        $seller = $lead->submittedBy;

        if (! $seller?->email) {
            return;
        }

        Mail::to($seller->email)->queue(new SellerLeadStatusMail($lead, $event, $details));
    }

    private function notifySeller(
        User $actor,
        Lead $lead,
        string $subject,
        string $body,
        MessageThreadCategory $category,
    ): void {
        $sellerId = $lead->submitted_by_user_id;
        if (! $sellerId) {
            return;
        }

        $thread = MessageThread::query()->create([
            'thread_reference' => ReferenceGenerator::thread(),
            'subject' => $subject.' — '.$lead->lead_reference,
            'category' => $category,
            'status' => MessageThreadStatus::Open,
            'created_by_user_id' => $actor->id,
            'assigned_to_user_id' => null,
            'related_lead_id' => $lead->id,
        ]);

        // Seller must be able to see the thread — set created_by to seller for seller portal scope,
        // or keep admin as creator and allow admin manage_messages. Seller MessageController scopes
        // to created_by_user_id = seller. So create as seller-owned with admin reply.
        $thread->update(['created_by_user_id' => $sellerId]);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'sender_user_id' => $actor->id,
            'body' => $body,
        ]);
    }
}
