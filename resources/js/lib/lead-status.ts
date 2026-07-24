import type { BadgeTone } from '@/Components/ui/StatusBadge';

const PENDING_REVIEW = new Set([
    'submitted',
    'pending_validation',
    'validating',
    'pending_review',
]);

const NEEDS_INFORMATION = new Set([
    'pending_evidence',
    'needs_more_information',
    'needs_information',
]);

const LISTED = new Set(['accepted', 'priced', 'listed']);

export function leadStatusLabel(
    status: string | null | undefined,
    labels: Record<string, string>,
): string {
    if (!status) {
        return '—';
    }

    if (labels[status]) {
        return labels[status];
    }

    if (PENDING_REVIEW.has(status)) {
        return labels.pending_review ?? labels.pending_validation ?? status;
    }

    if (NEEDS_INFORMATION.has(status)) {
        return labels.needs_information ?? labels.needs_more_information ?? status;
    }

    if (LISTED.has(status)) {
        return labels.listed ?? status;
    }

    return status;
}

export function leadStatusTone(status: string | null | undefined): BadgeTone {
    switch (status) {
        case 'accepted':
        case 'sold':
        case 'priced':
        case 'listed':
        case 'paid':
        case 'approved':
            return 'success';
        case 'rejected':
        case 'cancelled':
        case 'disputed':
        case 'suspended':
            return 'danger';
        case 'needs_more_information':
        case 'needs_information':
        case 'pending_evidence':
        case 'pending_validation':
        case 'pending_review':
        case 'validating':
        case 'pending':
        case 'due':
            return 'warning';
        case 'submitted':
        case 'draft':
            return 'info';
        default:
            return 'neutral';
    }
}

export function zoneLabel(
    code: string | null | undefined,
    notSetLabel: string,
): string {
    if (!code || code.trim() === '') {
        return notSetLabel;
    }

    return code;
}
