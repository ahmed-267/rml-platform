<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\LeadEvidenceFile;

final class AdminLeadPresenter
{
    /**
     * Full internal lead view for admin portals only.
     *
     * @return array<string, mixed>
     */
    public static function present(Lead $lead): array
    {
        $lead->loadMissing([
            'scheme:id,name,slug',
            'zone:id,code,name,scheme_id',
            'submittedBy:id,name,email,phone,approval_status',
            'submittedBy.roles:id,name',
            'sellerCompany:id,name',
            'evidenceFiles',
            'metricValues',
            'audits' => fn ($q) => $q->latest('id')->limit(5),
            'purchaseItems.purchase.buyerCompany:id,name',
            'purchaseItems.purchase.buyerUser:id,name,email',
            'purchaseItems.purchase.payment:id,payment_reference,status,amount,paid_at,method,currency',
            'commissions',
        ]);

        /** @var LeadAudit|null $latestAudit */
        $latestAudit = $lead->audits->first();

        $purchaseItem = $lead->purchaseItems
            ->sortByDesc(fn ($item) => $item->purchase?->purchased_at ?? $item->created_at)
            ->first();

        $purchase = $purchaseItem?->purchase;

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
            'latitude' => $lead->latitude !== null ? (float) $lead->latitude : null,
            'longitude' => $lead->longitude !== null ? (float) $lead->longitude : null,
            'property_type' => $lead->property_type,
            'epc_rating' => $lead->epc_rating,
            'size_m2' => $lead->size_m2 !== null ? (float) $lead->size_m2 : null,
            'distance_km' => $lead->distance_km !== null ? (float) $lead->distance_km : null,
            'buying_price' => $lead->buying_price !== null ? (float) $lead->buying_price : null,
            'selling_price' => $lead->selling_price !== null ? (float) $lead->selling_price : null,
            'expected_margin' => $lead->expected_margin !== null ? (float) $lead->expected_margin : null,
            'notes' => $lead->notes,
            'rejection_reason' => $lead->rejection_reason ?? $latestAudit?->rejection_reason,
            'requested_info' => $latestAudit?->requested_info,
            'seller' => $lead->submittedBy ? [
                'id' => $lead->submittedBy->id,
                'name' => $lead->submittedBy->name,
                'email' => $lead->submittedBy->email,
                'phone' => $lead->submittedBy->phone,
                'role_label' => $lead->submittedBy->primaryRole()?->label(),
                'approval_status' => $lead->submittedBy->approval_status?->value,
            ] : null,
            'seller_company' => $lead->sellerCompany ? [
                'id' => $lead->sellerCompany->id,
                'name' => $lead->sellerCompany->name,
            ] : null,
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
                ->map(function (LeadEvidenceFile $file) {
                    $meta = SellerLeadPresenter::evidenceMeta($file);
                    $meta['view_url'] = route('admin.leads.evidence.view', $file);
                    $meta['download_url'] = route('admin.leads.evidence.download', $file);
                    $meta['is_image'] = is_string($file->mime_type)
                        && str_starts_with($file->mime_type, 'image/');

                    return $meta;
                })
                ->values()
                ->all(),
            'audits' => $lead->audits->map(fn (LeadAudit $audit) => [
                'id' => $audit->id,
                'status' => $audit->status?->value,
                'audit_notes' => $audit->audit_notes,
                'rejection_reason' => $audit->rejection_reason,
                'requested_info' => $audit->requested_info,
                'buying_price' => $audit->buying_price !== null ? (float) $audit->buying_price : null,
                'selling_price' => $audit->selling_price !== null ? (float) $audit->selling_price : null,
                'expected_margin' => $audit->expected_margin !== null ? (float) $audit->expected_margin : null,
                'override_reason' => $audit->override_reason,
                'completed_at' => $audit->completed_at?->toIso8601String(),
            ])->values()->all(),
            'buyer' => $purchase ? [
                'company_name' => $purchase->buyerCompany?->name,
                'user_name' => $purchase->buyerUser?->name,
                'user_email' => $purchase->buyerUser?->email,
                'purchase_reference' => $purchase->purchase_reference,
                'purchase_status' => $purchase->status?->value,
                'purchased_at' => $purchase->purchased_at?->toIso8601String(),
                'payment_reference' => $purchase->payment?->payment_reference,
                'payment_status' => $purchase->payment?->status?->value,
                'payment_method' => $purchase->payment?->method?->value,
                'paid_at' => $purchase->payment?->paid_at?->toIso8601String(),
                'amount_paid' => $purchase->payment?->amount !== null ? (float) $purchase->payment->amount : null,
                'currency' => $purchase->payment?->currency ?? 'EUR',
                'release_status' => $purchase->payment?->status?->value === 'paid' ? 'released' : 'pending_release',
            ] : null,
            'commission_due' => $lead->commissions
                ->sum(fn ($commission) => (float) ($commission->commission_amount ?? 0)),
            'payout_due' => $lead->buying_price !== null ? (float) $lead->buying_price : null,
            'commissions' => $lead->commissions->map(fn ($commission) => [
                'id' => $commission->id,
                'commission_reference' => $commission->commission_reference,
                'status' => $commission->status?->value,
                'percentage' => $commission->percentage !== null ? (float) $commission->percentage : null,
                'commission_amount' => $commission->commission_amount !== null ? (float) $commission->commission_amount : null,
            ])->values()->all(),
            'accepted_at' => $lead->accepted_at?->toIso8601String(),
            'rejected_at' => $lead->rejected_at?->toIso8601String(),
            'listed_at' => $lead->listed_at?->toIso8601String(),
            'sold_at' => $lead->sold_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),
            'updated_at' => $lead->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  iterable<Lead>  $leads
     * @return list<array<string, mixed>>
     */
    public static function collection(iterable $leads): array
    {
        $items = [];

        foreach ($leads as $lead) {
            $items[] = self::present($lead);
        }

        return $items;
    }

    /**
     * Compact row for admin list tables.
     *
     * @return array<string, mixed>
     */
    public static function listRow(Lead $lead): array
    {
        $lead->loadMissing([
            'scheme:id,name',
            'zone:id,code',
            'submittedBy:id,name',
            'sellerCompany:id,name,type',
        ]);

        $hasCompany = $lead->sellerCompany !== null;

        return [
            'id' => $lead->id,
            'lead_reference' => $lead->lead_reference,
            'status' => $lead->status?->value,
            'scheme' => $lead->scheme?->name,
            'zone' => ZoneDisplay::code($lead->zone?->code),
            'size_m2' => $lead->size_m2 !== null ? (float) $lead->size_m2 : null,
            'buying_price' => $lead->buying_price !== null ? (float) $lead->buying_price : null,
            'selling_price' => $lead->selling_price !== null ? (float) $lead->selling_price : null,
            'expected_margin' => $lead->expected_margin !== null ? (float) $lead->expected_margin : null,
            'seller_name' => $lead->submittedBy?->name,
            'seller_company' => $lead->sellerCompany?->name,
            'seller_display' => $hasCompany
                ? $lead->sellerCompany?->name
                : $lead->submittedBy?->name,
            'seller_is_company' => $hasCompany,
            'submitted_at' => $lead->created_at?->toIso8601String(),
            'sold_at' => $lead->sold_at?->toIso8601String(),
        ];
    }
}
