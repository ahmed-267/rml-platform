<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\LeadEvidenceFile;
use App\Models\User;

final class SellerLeadPresenter
{
    /**
     * Transform a lead for seller Inertia pages.
     * Never includes buyer info, selling price, or RML margin.
     * May include seller-safe payout / commission summary only.
     *
     * @return array<string, mixed>
     */
    public static function present(Lead $lead, ?User $viewer = null): array
    {
        $lead->loadMissing([
            'scheme:id,name,slug',
            'zone:id,code,name,scheme_id',
            'evidenceFiles',
            'metricValues',
            'audits' => fn ($q) => $q->latest('id'),
        ]);

        /** @var LeadAudit|null $latestAudit */
        $latestAudit = $lead->audits->first();
        $viewer ??= auth()->user();

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
            'notes' => $lead->notes,
            'rejection_reason' => $lead->rejection_reason ?? $latestAudit?->rejection_reason,
            'requested_info' => $latestAudit?->requested_info,
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
                ->mapWithKeys(fn ($metric) => [$metric->key => $metric->value])
                ->all(),
            'evidence' => $lead->evidenceFiles
                ->map(fn (LeadEvidenceFile $file) => self::evidenceMeta($file))
                ->values()
                ->all(),
            'payout' => SellerLeadPayoutSummary::forLead($lead, $viewer instanceof User ? $viewer : null),
            'accepted_at' => $lead->accepted_at?->toIso8601String(),
            'rejected_at' => $lead->rejected_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),
            'updated_at' => $lead->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function evidenceMeta(LeadEvidenceFile $file): array
    {
        $meta = [
            'id' => $file->id,
            'file_type' => $file->file_type?->value,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'status' => $file->status?->value,
            'created_at' => $file->created_at?->toIso8601String(),
            'is_image' => is_string($file->mime_type)
                && str_starts_with($file->mime_type, 'image/'),
        ];

        $user = auth()->user();
        if ($user && LeadEvidenceAccess::canView($user, $file)) {
            $meta['view_url'] = route('seller.leads.evidence.view', $file);
            $meta['download_url'] = route('seller.leads.evidence.download', $file);
        }

        return $meta;
    }

    /**
     * @param  iterable<Lead>  $leads
     * @return list<array<string, mixed>>
     */
    public static function collection(iterable $leads, ?User $viewer = null): array
    {
        $items = [];

        foreach ($leads as $lead) {
            $items[] = self::present($lead, $viewer);
        }

        return $items;
    }
}
