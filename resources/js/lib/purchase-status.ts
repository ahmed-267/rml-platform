/** Derive one display status for a purchase + payment pair (avoid duplicate Pending badges). */

export type PurchaseFlowStatus =
    | 'paid'
    | 'failed'
    | 'cancelled'
    | 'manual_transfer_pending'
    | 'pending';

export function purchaseFlowStatus(purchase: {
    status?: string | null;
    payment?: {
        status?: string | null;
        method?: string | null;
    } | null;
}): PurchaseFlowStatus {
    const purchaseStatus = purchase.status ?? null;
    const paymentStatus = purchase.payment?.status ?? null;
    const method = purchase.payment?.method ?? null;

    if (purchaseStatus === 'paid' || paymentStatus === 'paid') {
        return 'paid';
    }

    if (paymentStatus === 'failed') {
        return 'failed';
    }

    if (purchaseStatus === 'cancelled' || paymentStatus === 'cancelled') {
        return 'cancelled';
    }

    if (
        (method === 'manual_bank_transfer' || method === 'bank_transfer') &&
        (paymentStatus === 'pending' || purchaseStatus === 'pending')
    ) {
        return 'manual_transfer_pending';
    }

    return 'pending';
}

export function purchaseFlowStatusTone(
    status: PurchaseFlowStatus,
): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
    switch (status) {
        case 'paid':
            return 'success';
        case 'failed':
            return 'danger';
        case 'cancelled':
            return 'neutral';
        case 'manual_transfer_pending':
            return 'info';
        case 'pending':
        default:
            return 'warning';
    }
}
