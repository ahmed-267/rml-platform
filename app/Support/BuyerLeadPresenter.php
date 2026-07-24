<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\LeadEvidenceFile;
use App\Models\User;
use App\Services\Buyer\LeadReleaseService;
use App\Services\DistanceService;
use App\Services\LeadPricingService;

final class BuyerLeadPresenter
{
    /**
     * Marketplace-safe lead payload — never includes customer or seller PII.
     *
     * @return array<string, mixed>
     */
    public static function presentForMarketplace(
        Lead $lead,
        User $buyer,
        ?LeadPricingService $pricingService = null,
        ?DistanceService $distanceService = null,
    ): array {
        $pricingService ??= new LeadPricingService;
        $distanceService ??= new DistanceService;

        $lead->loadMissing(['scheme:id,name,slug', 'zone:id,code,name']);

        $pricing = $pricingService->calculate($lead);
        $company = $buyer->buyerProfile?->company;
        $distance = $distanceService->forLead($lead, $company);
        $total = $lead->selling_price !== null
            ? (float) $lead->selling_price
            : ($pricing['selling_price'] ?? null);

        return [
            'id' => $lead->id,
            'lead_reference' => $lead->lead_reference,
            'status' => $lead->status?->value,
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
            'size_m2' => $lead->size_m2 !== null ? (float) $lead->size_m2 : null,
            'distance_km' => $distance,
            'price_per_m2' => $pricing['price_per_m2'],
            'total_price' => $total,
            'availability' => 'available',
            'details_released' => false,
        ];
    }

    /**
     * Purchase/detail payload — customer details only when payment confirmed.
     *
     * @return array<string, mixed>
     */
    public static function presentForPurchase(
        Lead $lead,
        User $buyer,
        ?LeadReleaseService $releaseService = null,
        ?LeadPricingService $pricingService = null,
        ?DistanceService $distanceService = null,
    ): array {
        $releaseService ??= new LeadReleaseService;
        $base = self::presentForMarketplace($lead, $buyer, $pricingService, $distanceService);
        $released = $releaseService->canReleaseDetails($buyer, $lead);

        $base['details_released'] = $released;

        if (! $released) {
            return $base;
        }

        $lead->loadMissing('evidenceFiles');

        return array_merge($base, [
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
            'notes' => $lead->notes,
            'evidence' => $lead->evidenceFiles
                ->map(fn (LeadEvidenceFile $file) => [
                    'id' => $file->id,
                    'file_type' => $file->file_type?->value,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size' => $file->size,
                    'status' => $file->status?->value,
                    'created_at' => $file->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @param  iterable<Lead>  $leads
     * @return list<array<string, mixed>>
     */
    public static function marketplaceCollection(iterable $leads, User $buyer): array
    {
        $items = [];
        $pricing = new LeadPricingService;
        $distance = new DistanceService;

        foreach ($leads as $lead) {
            $items[] = self::presentForMarketplace($lead, $buyer, $pricing, $distance);
        }

        return $items;
    }
}
