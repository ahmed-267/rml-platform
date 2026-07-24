<?php

namespace App\Support;

use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Models\Commission;
use App\Models\Lead;
use App\Models\Payout;
use App\Models\User;

/**
 * Seller-safe payout / commission summary for lead list & detail.
 * Never exposes selling_price, margin, or buyer details.
 */
final class SellerLeadPayoutSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function forLead(Lead $lead, ?User $viewer): array
    {
        if (! $viewer) {
            return self::empty();
        }

        $visibleKey = LeadStatusPresentation::visibleKey($lead->status?->value);

        if (self::isSellerStaff($viewer) && ! $viewer->can(Permissions::VIEW_COMPANY_LEADS)) {
            return self::forStaffMember($lead, $viewer, $visibleKey);
        }

        return self::forCompanyOrAgent($lead, $viewer, $visibleKey);
    }

    /**
     * @return array<string, mixed>
     */
    private static function forCompanyOrAgent(Lead $lead, User $viewer, ?string $visibleKey): array
    {
        $payout = self::findPayoutForLead($lead);
        $estimated = $lead->buying_price !== null ? round((float) $lead->buying_price, 2) : null;

        return self::buildPayload(
            lead: $lead,
            visibleKey: $visibleKey,
            kind: 'company_payout',
            estimatedAmount: $estimated,
            confirmedAmount: $payout?->amount !== null ? round((float) $payout->amount, 2) : null,
            payoutStatus: $payout?->status,
            ratePercent: null,
            payoutReference: $payout?->payout_reference,
            managedByAdmin: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function forStaffMember(Lead $lead, User $viewer, ?string $visibleKey): array
    {
        if ((int) $lead->submitted_by_user_id !== (int) $viewer->id) {
            return array_merge(self::empty(), [
                'visible' => false,
                'managed_by_admin' => true,
                'display' => 'managed_by_admin',
                'status' => 'managed_by_admin',
            ]);
        }

        $viewer->loadMissing('sellerProfile');
        $rate = $viewer->sellerProfile?->commission_rate !== null
            ? (float) $viewer->sellerProfile->commission_rate
            : null;

        $commission = Commission::query()
            ->where('lead_id', $lead->id)
            ->where('seller_user_id', $viewer->id)
            ->latest('id')
            ->first();

        $hasCommissionEnabled = ($rate !== null && $rate > 0) || $commission !== null;

        if (! $hasCommissionEnabled) {
            return array_merge(self::empty(), [
                'visible' => true,
                'managed_by_admin' => true,
                'display' => 'managed_by_admin',
                'status' => 'managed_by_admin',
                'kind' => 'staff_commission',
                'metric' => self::metricForLead($lead),
            ]);
        }

        $confirmed = $commission?->commission_amount !== null
            ? round((float) $commission->commission_amount, 2)
            : null;

        // Seller-safe estimate only — never use selling_price.
        $estimated = null;
        if ($confirmed === null && $rate !== null && $rate > 0 && $lead->buying_price !== null) {
            $estimated = round(((float) $lead->buying_price) * ($rate / 100), 2);
        } elseif ($confirmed !== null && $visibleKey !== LeadStatusPresentation::SOLD) {
            $estimated = $confirmed;
        }

        $payoutStatus = null;
        if ($commission?->status) {
            $payoutStatus = match ($commission->status->value) {
                'paid' => PayoutStatus::Paid,
                'due' => PayoutStatus::Pending,
                default => null,
            };
        }

        return self::buildPayload(
            lead: $lead,
            visibleKey: $visibleKey,
            kind: 'staff_commission',
            estimatedAmount: $estimated,
            confirmedAmount: $visibleKey === LeadStatusPresentation::SOLD ? $confirmed : null,
            payoutStatus: $payoutStatus,
            ratePercent: $rate ?? ($commission?->percentage !== null ? (float) $commission->percentage : null),
            payoutReference: $commission?->commission_reference,
            managedByAdmin: false,
            commissionStatus: $commission?->status?->value,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildPayload(
        Lead $lead,
        ?string $visibleKey,
        string $kind,
        ?float $estimatedAmount,
        ?float $confirmedAmount,
        ?PayoutStatus $payoutStatus,
        ?float $ratePercent,
        ?string $payoutReference,
        bool $managedByAdmin,
        ?string $commissionStatus = null,
    ): array {
        $display = 'dash';
        $status = 'not_due';
        $amount = null;
        $isEstimated = false;
        $isConfirmed = false;

        if (in_array($visibleKey, [LeadStatusPresentation::DRAFT, LeadStatusPresentation::REJECTED, LeadStatusPresentation::CANCELLED], true)) {
            $display = 'dash';
            $status = 'not_due';
        } elseif (in_array($visibleKey, [LeadStatusPresentation::PENDING_REVIEW, LeadStatusPresentation::NEEDS_INFORMATION], true)) {
            $display = 'pending_review';
            $status = 'pending_review';
        } elseif ($visibleKey === LeadStatusPresentation::LISTED) {
            if ($estimatedAmount !== null && $estimatedAmount > 0) {
                $display = 'estimated';
                $amount = $estimatedAmount;
                $isEstimated = true;
                $status = 'pending_sale';
            } else {
                $display = 'pending_review';
                $status = 'pending_review';
            }
        } elseif ($visibleKey === LeadStatusPresentation::SOLD) {
            $final = $confirmedAmount ?? $estimatedAmount;
            if ($final !== null) {
                $display = 'confirmed';
                $amount = $final;
                $isConfirmed = true;
                $status = match (true) {
                    $payoutStatus === PayoutStatus::Paid, $commissionStatus === 'paid' => 'paid',
                    $payoutStatus === PayoutStatus::Pending, $commissionStatus === 'due' => 'due',
                    default => 'pending_payout',
                };
            } else {
                $display = 'pending_review';
                $status = 'pending_payout';
            }
        } elseif ($visibleKey === LeadStatusPresentation::DISPUTED) {
            $display = 'pending_review';
            $status = 'pending_review';
        }

        return [
            'visible' => true,
            'managed_by_admin' => $managedByAdmin,
            'kind' => $kind,
            'display' => $display,
            'amount' => $amount,
            'estimated_amount' => $estimatedAmount,
            'final_amount' => $isConfirmed ? $amount : ($confirmedAmount),
            'currency' => 'EUR',
            'status' => $status,
            'is_estimated' => $isEstimated,
            'is_confirmed' => $isConfirmed,
            'rate_percent' => $ratePercent,
            'payout_reference' => $payoutReference,
            'metric' => self::metricForLead($lead),
        ];
    }

    private static function findPayoutForLead(Lead $lead): ?Payout
    {
        $ref = $lead->lead_reference;

        $query = Payout::query()->where('notes', 'like', '%'.$ref.'%');

        if ($lead->seller_company_id) {
            $query->where('seller_company_id', $lead->seller_company_id);
        } elseif ($lead->submitted_by_user_id) {
            $query->where('seller_user_id', $lead->submitted_by_user_id);
        }

        return $query->latest('id')->first();
    }

    /**
     * @return array{key: string, value: string|null}|null
     */
    private static function metricForLead(Lead $lead): ?array
    {
        $lead->loadMissing(['scheme:id,slug', 'metricValues']);
        $slug = $lead->scheme?->slug ?? '';
        $metrics = $lead->metricValues->mapWithKeys(fn ($m) => [$m->key => $m->value])->all();

        if (str_contains($slug, 'double') || str_contains($slug, 'glazing')) {
            $windows = $metrics['window_count'] ?? $metrics['windows'] ?? null;
            $area = $metrics['glazing_area'] ?? $metrics['area_m2'] ?? null;
            if ($windows !== null) {
                return ['key' => 'window_count', 'value' => (string) $windows];
            }
            if ($area !== null) {
                return ['key' => 'glazing_area', 'value' => (string) $area];
            }
        }

        if (str_contains($slug, 'heat')) {
            $kw = $metrics['estimated_kw'] ?? $metrics['kw'] ?? null;
            if ($kw !== null) {
                return ['key' => 'estimated_kw', 'value' => (string) $kw];
            }
            if ($lead->size_m2 !== null) {
                return ['key' => 'property_size', 'value' => (string) $lead->size_m2];
            }
        }

        if ($lead->size_m2 !== null) {
            return ['key' => 'area_m2', 'value' => (string) $lead->size_m2];
        }

        $area = $metrics['area_m2'] ?? $metrics['size_m2'] ?? null;
        if ($area !== null) {
            return ['key' => 'area_m2', 'value' => (string) $area];
        }

        return null;
    }

    private static function isSellerStaff(User $user): bool
    {
        return $user->hasRole(UserRole::SellerStaff->value);
    }

    /**
     * @return array<string, mixed>
     */
    private static function empty(): array
    {
        return [
            'visible' => false,
            'managed_by_admin' => false,
            'kind' => 'none',
            'display' => 'dash',
            'amount' => null,
            'estimated_amount' => null,
            'final_amount' => null,
            'currency' => 'EUR',
            'status' => 'not_due',
            'is_estimated' => false,
            'is_confirmed' => false,
            'rate_percent' => null,
            'payout_reference' => null,
            'metric' => null,
        ];
    }
}
