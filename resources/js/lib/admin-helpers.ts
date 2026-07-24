import type { SortDirection } from '@/Components/ui/SortableHeader';

export type { Paginator } from '@/lib/list-helpers';

export {
    paginationMeta,
    paginationLabels,
    resolveSortDirection,
    nextSortDirection,
} from '@/lib/list-helpers';

export function accountActionLabels(common: Record<string, string>) {
    return {
        view: common.view,
        approve: common.approve,
        reject: common.reject,
        suspend: common.suspend,
        reinstate: common.reinstate,
        cancel: common.confirm_no_cancel,
        rejectTitle: common.reject_confirm_title,
        rejectBody: common.reject_confirm_body,
        rejectConfirm: common.confirm_yes_reject,
        suspendTitle: common.suspend_confirm_title,
        suspendBody: common.suspend_confirm_body,
        suspendConfirm: common.confirm_yes_suspend,
        reinstateTitle: common.reinstate_confirm_title,
        reinstateBody: common.reinstate_confirm_body,
        reinstateConfirm: common.confirm_yes_reinstate,
    };
}

export function formatMoney(
    value: number | null | undefined,
    currency = 'EUR',
): string {
    if (value == null) {
        return '—';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

export function formatDate(
    value: string | null | undefined,
    locale: string,
    options?: Intl.DateTimeFormatOptions,
): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        ...options,
    });
}

export function formatDateTime(
    value: string | null | undefined,
    locale: string,
): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(locale);
}

export function approvalStatusLabel(
    status: string,
    statuses: Record<string, string>,
): string {
    return statuses[status] ?? status;
}

/** @deprecated Prefer resolveSortDirection from list-helpers */
export type { SortDirection };
