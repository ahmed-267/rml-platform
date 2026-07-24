import type { PageProps } from '@/types';

export type SellerPayoutSummary = {
    visible?: boolean;
    managed_by_admin?: boolean;
    kind?: string | null;
    display?: string | null;
    amount?: number | null;
    estimated_amount?: number | null;
    final_amount?: number | null;
    currency?: string | null;
    status?: string | null;
    is_estimated?: boolean;
    is_confirmed?: boolean;
    rate_percent?: number | null;
    payout_reference?: string | null;
    metric?: { key: string; value: string | null } | null;
};

type PayoutLabels = Record<string, string>;

export function formatSellerMoney(
    value: number | null | undefined,
    currency = 'EUR',
    locale?: string,
): string {
    if (value == null) {
        return '—';
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

export function sellerPayoutTableLabel(
    payout: SellerPayoutSummary | null | undefined,
    labels: PayoutLabels,
    locale?: string,
): string {
    if (!payout) {
        return '—';
    }

    if (payout.managed_by_admin || payout.display === 'managed_by_admin') {
        return labels.managed_by_admin || '—';
    }

    if (payout.visible === false) {
        return '—';
    }

    switch (payout.display) {
        case 'pending_review':
            return labels.pending_review || '—';
        case 'estimated':
            return `${labels.estimated_short} ${formatSellerMoney(payout.amount, payout.currency ?? 'EUR', locale)}`.trim();
        case 'confirmed':
            return `${formatSellerMoney(payout.amount, payout.currency ?? 'EUR', locale)} ${labels.confirmed_suffix || ''}`.trim();
        case 'dash':
        default:
            return '—';
    }
}

export function sellerPayoutStatusLabel(
    status: string | null | undefined,
    labels: PayoutLabels,
): string {
    if (!status) {
        return '—';
    }

    return labels[`status_${status}`] || labels[status] || status;
}

export function sellerPayoutMetricLabel(
    metric: SellerPayoutSummary['metric'],
    labels: PayoutLabels,
): string | null {
    if (!metric?.key) {
        return null;
    }

    const keyLabel = labels[`metric_${metric.key}`] || metric.key;

    return metric.value != null ? `${keyLabel}: ${metric.value}` : keyLabel;
}

export function sellerPayoutLabels(
    translations: PageProps['translations'],
): PayoutLabels {
    const payout = translations.seller.payout ?? {};
    const payments = translations.seller.payments ?? {};

    return {
        ...payments,
        ...payout,
        status_not_due: payout.status_not_due,
        status_pending_review: payout.pending_review,
        status_pending_sale: payout.pending_sale,
        status_due: payout.status_due,
        status_paid: payout.status_paid,
        status_pending_payout: payout.pending_payout,
        status_managed_by_admin: payout.managed_by_admin,
    };
}
