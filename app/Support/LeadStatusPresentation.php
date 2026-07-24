<?php

namespace App\Support;

use App\Enums\LeadStatus;

/**
 * Maps internal lead statuses to the simplified visible status set used in UI, filters, and reports.
 */
class LeadStatusPresentation
{
    public const DRAFT = 'draft';

    public const PENDING_REVIEW = 'pending_review';

    public const NEEDS_INFORMATION = 'needs_information';

    public const LISTED = 'listed';

    public const SOLD = 'sold';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const DISPUTED = 'disputed';

    /**
     * Visible filter / pipeline keys in display order (excluding draft for admin queues).
     *
     * @return list<string>
     */
    public static function visibleValues(bool $includeDraft = true): array
    {
        $values = [
            self::PENDING_REVIEW,
            self::NEEDS_INFORMATION,
            self::LISTED,
            self::SOLD,
            self::REJECTED,
            self::CANCELLED,
            self::DISPUTED,
        ];

        if ($includeDraft) {
            array_unshift($values, self::DRAFT);
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    public static function expand(string $visibleOrInternal): array
    {
        return match ($visibleOrInternal) {
            self::DRAFT, LeadStatus::Draft->value => [LeadStatus::Draft->value],
            self::PENDING_REVIEW,
            LeadStatus::Submitted->value,
            LeadStatus::PendingValidation->value,
            LeadStatus::Validating->value => [
                LeadStatus::Submitted->value,
                LeadStatus::PendingValidation->value,
                LeadStatus::Validating->value,
            ],
            self::NEEDS_INFORMATION,
            LeadStatus::PendingEvidence->value,
            LeadStatus::NeedsMoreInformation->value => [
                LeadStatus::PendingEvidence->value,
                LeadStatus::NeedsMoreInformation->value,
            ],
            self::LISTED,
            LeadStatus::Accepted->value,
            LeadStatus::Priced->value,
            LeadStatus::Listed->value => [
                LeadStatus::Accepted->value,
                LeadStatus::Priced->value,
                LeadStatus::Listed->value,
            ],
            self::SOLD, LeadStatus::Sold->value => [LeadStatus::Sold->value],
            self::REJECTED, LeadStatus::Rejected->value => [LeadStatus::Rejected->value],
            self::CANCELLED, LeadStatus::Cancelled->value => [LeadStatus::Cancelled->value],
            self::DISPUTED, LeadStatus::Disputed->value => [LeadStatus::Disputed->value],
            default => [$visibleOrInternal],
        };
    }

    public static function visibleKey(?string $internalStatus): ?string
    {
        if ($internalStatus === null || $internalStatus === '') {
            return null;
        }

        return match ($internalStatus) {
            LeadStatus::Draft->value => self::DRAFT,
            LeadStatus::Submitted->value,
            LeadStatus::PendingValidation->value,
            LeadStatus::Validating->value => self::PENDING_REVIEW,
            LeadStatus::PendingEvidence->value,
            LeadStatus::NeedsMoreInformation->value => self::NEEDS_INFORMATION,
            LeadStatus::Accepted->value,
            LeadStatus::Priced->value,
            LeadStatus::Listed->value => self::LISTED,
            LeadStatus::Sold->value => self::SOLD,
            LeadStatus::Rejected->value => self::REJECTED,
            LeadStatus::Cancelled->value => self::CANCELLED,
            LeadStatus::Disputed->value => self::DISPUTED,
            default => $internalStatus,
        };
    }

    /**
     * Collapse raw status => count map into visible status buckets.
     *
     * @param  array<string, int|string>  $rawCounts
     * @return array<string, int>
     */
    public static function groupCounts(array $rawCounts): array
    {
        $grouped = [];

        foreach ($rawCounts as $status => $count) {
            $key = self::visibleKey((string) $status) ?? (string) $status;
            $grouped[$key] = ($grouped[$key] ?? 0) + (int) $count;
        }

        $ordered = [];
        foreach (self::visibleValues(includeDraft: false) as $key) {
            if (($grouped[$key] ?? 0) > 0) {
                $ordered[$key] = $grouped[$key];
            }
        }

        foreach ($grouped as $key => $count) {
            if (! array_key_exists($key, $ordered) && $count > 0) {
                $ordered[$key] = $count;
            }
        }

        return $ordered;
    }
}
