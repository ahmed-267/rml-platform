<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\LeadEvidenceFile;

/**
 * Auditor-safe lead presentation — customer/property for validation,
 * no buyer details, no margin/payment/pricing internals.
 */
final class AuditorLeadPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Lead $lead): array
    {
        $lead->loadMissing([
            'scheme:id,name,slug',
            'zone:id,code,name',
            'submittedBy:id,name',
            'sellerCompany:id,name',
            'evidenceFiles',
            'metricValues',
            'audits' => fn ($q) => $q->with(['auditor:id,name', 'checklistResults'])->latest('id')->limit(5),
        ]);

        /** @var LeadAudit|null $latestAudit */
        $latestAudit = $lead->audits->first();

        return [
            'id' => $lead->id,
            'lead_reference' => $lead->lead_reference,
            'status' => $lead->status?->value,
            'customer_first_name' => $lead->customer_first_name,
            'customer_last_name' => $lead->customer_last_name,
            'customer_phone' => $lead->customer_phone,
            'customer_whatsapp' => $lead->customer_whatsapp,
            'customer_email' => $lead->customer_email,
            'address_line_1' => $lead->address_line_1,
            'address_line_2' => $lead->address_line_2,
            'city' => $lead->city,
            'postcode' => $lead->postcode,
            'country' => $lead->country,
            'property_type' => $lead->property_type,
            'epc_rating' => $lead->epc_rating,
            'size_m2' => $lead->size_m2 !== null ? (float) $lead->size_m2 : null,
            'distance_km' => $lead->distance_km !== null ? (float) $lead->distance_km : null,
            'notes' => $lead->notes,
            'rejection_reason' => $lead->rejection_reason ?? $latestAudit?->rejection_reason,
            'requested_info' => $latestAudit?->requested_info,
            'created_at' => $lead->created_at?->toIso8601String(),
            'seller_name' => $lead->sellerCompany?->name ?? $lead->submittedBy?->name,
            'scheme' => $lead->scheme ? [
                'id' => $lead->scheme->id,
                'name' => $lead->scheme->name,
                'slug' => $lead->scheme->slug,
            ] : null,
            'zone' => $lead->zone ? [
                'id' => $lead->zone->id,
                'code' => $lead->zone->code,
                'name' => $lead->zone->name,
            ] : null,
            'metrics' => $lead->metricValues
                ->map(fn ($metric) => [
                    'key' => $metric->key,
                    'value' => $metric->value,
                    'label' => $metric->key,
                ])
                ->values()
                ->all(),
            'evidence' => $lead->evidenceFiles
                ->map(fn (LeadEvidenceFile $file) => SellerLeadPresenter::evidenceMeta($file))
                ->values()
                ->all(),
            'audit' => $latestAudit ? self::presentAudit($latestAudit) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function presentAudit(LeadAudit $audit): array
    {
        $audit->loadMissing(['checklistResults', 'auditor:id,name', 'finalDecisionBy:id,name']);

        return [
            'id' => $audit->id,
            'status' => $audit->status?->value,
            'audit_notes' => $audit->audit_notes,
            'rejection_reason' => $audit->rejection_reason,
            'requested_info' => $audit->requested_info,
            'completed_at' => $audit->completed_at?->toIso8601String(),
            'auditor' => $audit->auditor ? [
                'id' => $audit->auditor->id,
                'name' => $audit->auditor->name,
            ] : null,
            'final_decision_by' => $audit->finalDecisionBy ? [
                'id' => $audit->finalDecisionBy->id,
                'name' => $audit->finalDecisionBy->name,
            ] : null,
            'checklist_results' => $audit->checklistResults
                ->mapWithKeys(fn ($result) => [
                    (string) $result->audit_checklist_item_id => [
                        'checked' => (bool) $result->checked,
                        'notes' => $result->notes,
                    ],
                ])
                ->all(),
            'is_final' => in_array($audit->status?->value, ['accepted', 'rejected'], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function listRow(Lead $lead, ?LeadAudit $audit = null): array
    {
        $lead->loadMissing(['scheme:id,name', 'zone:id,code', 'sellerCompany:id,name', 'submittedBy:id,name', 'evidenceFiles']);
        $audit ??= $lead->audits->sortByDesc('id')->first();

        $evidenceTotal = $lead->evidenceFiles->count();
        $evidenceComplete = $evidenceTotal > 0;

        return [
            'id' => $lead->id,
            'lead_reference' => $lead->lead_reference,
            'scheme' => $lead->scheme?->name,
            'zone' => ZoneDisplay::code($lead->zone?->code),
            'size_m2' => $lead->size_m2 !== null ? (float) $lead->size_m2 : null,
            'seller_name' => $lead->sellerCompany?->name ?? $lead->submittedBy?->name,
            'submitted_at' => $lead->created_at?->toDateString(),
            'lead_status' => $lead->status?->value,
            'audit_status' => $audit?->status?->value,
            'evidence_status' => $evidenceComplete ? 'available' : 'missing',
            'evidence_count' => $evidenceTotal,
            'completed_at' => $audit?->completed_at?->toIso8601String(),
            'recommendation' => $audit?->status?->value,
        ];
    }
}
