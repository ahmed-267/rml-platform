import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    MobileFilterDrawer,
    KpiCard,
    MobileCardList,
    Modal,
    Pagination,
    Select,
    SortableHeader,
    StatusBadge,
} from '@/Components/ui';
import type { SortDirection } from '@/Components/ui/SortableHeader';
import {
    formatDate,
    formatMoney,
    paginationLabels,
    paginationMeta,
    type Paginator,
} from '@/lib/admin-helpers';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface PaymentRow {
    id: number;
    payment_reference: string;
    status: string | null;
    method: string | null;
    provider?: string | null;
    provider_status?: string | null;
    stripe_checkout_session_id?: string | null;
    stripe_payment_intent_id?: string | null;
    is_stripe?: boolean;
    amount: number | null;
    currency: string | null;
    payer_name: string | null;
    payer_company: string | null;
    due_date: string | null;
    paid_at: string | null;
    invoice_id?: number | null;
    receipt_id?: number | null;
    can_resend_email?: boolean;
    can_mark_paid?: boolean;
}

interface PayoutRow {
    id: number;
    payout_reference: string;
    status: string | null;
    amount: number | null;
    currency: string | null;
    seller_name: string | null;
    seller_company: string | null;
    due_date: string | null;
    paid_at: string | null;
}

interface CommissionRow {
    id: number;
    commission_reference: string;
    status: string | null;
    amount: number | null;
    percentage: number | null;
    seller_name: string | null;
    seller_company: string | null;
    lead_reference: string | null;
    due_at: string | null;
    paid_at: string | null;
    can_mark_paid?: boolean;
    statement_id?: number | null;
}

type PaymentFilters = {
    payment_status?: string | null;
    payment_method?: string | null;
    payout_status?: string | null;
    search?: string | null;
    per_page?: number | string | null;
    payments_sort?: string | null;
    payments_direction?: string | null;
    payouts_sort?: string | null;
    payouts_direction?: string | null;
    commissions_sort?: string | null;
    commissions_direction?: string | null;
};

export default function PaymentsIndex({
    buyerPayments,
    sellerPayouts,
    commissions,
    filters,
    summaries,
}: {
    buyerPayments: Paginator<PaymentRow>;
    sellerPayouts: Paginator<PayoutRow>;
    commissions: Paginator<CommissionRow>;
    filters: PaymentFilters;
    summaries: {
        buyer_pending_count: number;
        buyer_pending_sum: number;
        buyer_paid_sum: number;
        payout_pending_count: number;
        payout_pending_sum: number;
        payout_paid_sum: number;
        commission_due_sum?: number;
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.payments;
    const common = translations.admin.common;
    const paymentStatuses = translations.payment_statuses;
    const paymentMethods = translations.payment_methods;
    const isMobile = useIsMobile();
    const [markPaidTarget, setMarkPaidTarget] = useState<PaymentRow | null>(
        null,
    );
    const [markPaidProcessing, setMarkPaidProcessing] = useState(false);

    const confirmMarkPaid = () => {
        if (!markPaidTarget) {
            return;
        }

        setMarkPaidProcessing(true);
        router.post(
            route('admin.payments.mark-paid', markPaidTarget.id),
            {},
            {
                onFinish: () => {
                    setMarkPaidProcessing(false);
                    setMarkPaidTarget(null);
                },
            },
        );
    };

    const markPaidConfirmBody = (
        t.mark_paid_confirm_body ?? ''
    ).replaceAll(':reference', markPaidTarget?.payment_reference ?? '');

    const [search, setSearch] = useState(filters.search ?? '');
    const [paymentStatus, setPaymentStatus] = useState(
        filters.payment_status ?? '',
    );
    const [payoutStatus, setPayoutStatus] = useState(
        filters.payout_status ?? '',
    );
    const [filtersOpen, setFiltersOpen] = useState(false);

    const paymentsSort = filters.payments_sort ?? 'date';
    const paymentsDirection: SortDirection =
        filters.payments_direction === 'asc' ? 'asc' : 'desc';
    const payoutsSort = filters.payouts_sort ?? 'date';
    const payoutsDirection: SortDirection =
        filters.payouts_direction === 'asc' ? 'asc' : 'desc';
    const commissionsSort = filters.commissions_sort ?? 'date';
    const commissionsDirection: SortDirection =
        filters.commissions_direction === 'asc' ? 'asc' : 'desc';

    const buyerMeta = paginationMeta(buyerPayments);
    const payoutMeta = paginationMeta(sellerPayouts);
    const commissionMeta = paginationMeta(commissions);
    const currentPerPage = Number(
        filters.per_page ?? buyerMeta.perPage ?? 10,
    );

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        search: search || undefined,
        payment_status: paymentStatus || undefined,
        payout_status: payoutStatus || undefined,
        per_page: currentPerPage,
        payments_sort: paymentsSort,
        payments_direction: paymentsDirection,
        payouts_sort: payoutsSort,
        payouts_direction: payoutsDirection,
        commissions_sort: commissionsSort,
        commissions_direction: commissionsDirection,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(
            route('admin.payments.index'),
            queryParams({
                payments_page: 1,
                payouts_page: 1,
                commissions_page: 1,
            }),
            { preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        setPaymentStatus('');
        setPayoutStatus('');
        router.get(
            route('admin.payments.index'),
            {
                per_page: 10,
                payments_sort: 'date',
                payments_direction: 'desc',
                payouts_sort: 'date',
                payouts_direction: 'desc',
                commissions_sort: 'date',
                commissions_direction: 'desc',
            },
            { preserveState: true, replace: true },
        );
    };

    const handleTableSort = (
        table: 'payments' | 'payouts' | 'commissions',
        column: string,
    ) => {
        const current =
            table === 'payments'
                ? { sort: paymentsSort, direction: paymentsDirection }
                : table === 'payouts'
                  ? { sort: payoutsSort, direction: payoutsDirection }
                  : { sort: commissionsSort, direction: commissionsDirection };

        const nextDirection: SortDirection =
            current.sort === column && current.direction === 'asc'
                ? 'desc'
                : 'asc';

        router.get(
            route('admin.payments.index'),
            queryParams({
                [`${table}_sort`]: column,
                [`${table}_direction`]: nextDirection,
            }),
            { preserveState: true, replace: true },
        );
    };

    const sortableHeader = (
        table: 'payments' | 'payouts' | 'commissions',
        label: string,
        column: string,
    ) => {
        const currentSort =
            table === 'payments'
                ? paymentsSort
                : table === 'payouts'
                  ? payoutsSort
                  : commissionsSort;
        const currentDirection =
            table === 'payments'
                ? paymentsDirection
                : table === 'payouts'
                  ? payoutsDirection
                  : commissionsDirection;

        return (
            <SortableHeader
                label={label}
                column={column}
                currentSort={currentSort}
                currentDirection={currentDirection}
                onSort={(col) => handleTableSort(table, col)}
                sortAscLabel={common.sort_asc}
                sortDescLabel={common.sort_desc}
            />
        );
    };

    const pageLabels = paginationLabels(common);

    return (
        <AppLayout title={t.index_title} subtitle={t.index_subtitle}>
            <Head title={t.index_title} />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <KpiCard
                    label={t.pending_buyer}
                    value={summaries.buyer_pending_count}
                    hint={formatMoney(summaries.buyer_pending_sum)}
                    tone="warning"
                />
                <KpiCard
                    label={t.paid_total}
                    value={formatMoney(summaries.buyer_paid_sum)}
                    tone="success"
                />
                <KpiCard
                    label={t.pending_payouts}
                    value={summaries.payout_pending_count}
                    hint={formatMoney(summaries.payout_pending_sum)}
                    tone="warning"
                />
            </div>

            <FilterBar
                compact
                search={search}
                onSearchChange={setSearch}
                searchLabel={common.search}
                searchPlaceholder={common.search}
                onOpenMobileFilters={() => setFiltersOpen(true)}
                actions={
                    <>
                        <Button size="sm" onClick={applyFilters}>
                            {common.apply}
                        </Button>
                        <Button size="sm" variant="ghost" onClick={resetFilters}>
                            {common.reset}
                        </Button>
                    </>
                }
            >
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={common.status}
                        aria-label={common.status}
                        value={paymentStatus}
                        onChange={(e) => setPaymentStatus(e.target.value)}
                        options={[
                            { label: common.all_statuses, value: '' },
                            ...Object.entries(paymentStatuses).map(
                                ([value, label]) => ({
                                    label,
                                    value,
                                }),
                            ),
                        ]}
                    />
                </div>
            </FilterBar>

            <MobileFilterDrawer
                open={filtersOpen}
                onClose={() => setFiltersOpen(false)}
                onApply={applyFilters}
                onReset={resetFilters}
            >
                <Select
                    label={common.status}
                    aria-label={common.status}
                    value={paymentStatus}
                    onChange={(e) => setPaymentStatus(e.target.value)}
                    options={[
                        { label: common.all_statuses, value: '' },
                        ...Object.entries(paymentStatuses).map(
                            ([value, label]) => ({
                                label,
                                value,
                            }),
                        ),
                    ]}
                />
            </MobileFilterDrawer>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.buyer_payments}
                </h2>
                {buyerPayments.data.length === 0 ? (
                    <EmptyState title={common.empty} />
                ) : isMobile ? (
                    <MobileCardList
                        items={buyerPayments.data.map((row) => ({
                            id: String(row.id),
                            title: row.payment_reference,
                            subtitle: row.payer_company ?? row.payer_name ?? '—',
                            meta: row.status ? (
                                <StatusBadge
                                    label={
                                        paymentStatuses[row.status] ??
                                        row.status
                                    }
                                    tone={leadStatusTone(row.status)}
                                />
                            ) : null,
                            body: (
                                <p>
                                    {formatMoney(
                                        row.amount,
                                        row.currency ?? 'EUR',
                                    )}
                                </p>
                            ),
                            actions:
                                row.status === 'pending' ? (
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                setMarkPaidTarget(row)
                                            }
                                        >
                                            {t.mark_paid}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    route(
                                                        'admin.payments.cancel',
                                                        row.id,
                                                    ),
                                                )
                                            }
                                        >
                                            {t.cancel}
                                        </Button>
                                    </div>
                                ) : row.can_resend_email ? (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                route(
                                                    'admin.payments.resend-email',
                                                    row.id,
                                                ),
                                            )
                                        }
                                    >
                                        {t.resend_email}
                                    </Button>
                                ) : null,
                        }))}
                    />
                ) : (
                    <DataTable
                        data={buyerPayments.data}
                        getRowId={(r) => String(r.id)}
                        columns={[
                            {
                                id: 'ref',
                                header: sortableHeader(
                                    'payments',
                                    common.reference,
                                    'reference',
                                ),
                                cell: (r) => r.payment_reference,
                            },
                            {
                                id: 'payer',
                                header: sortableHeader(
                                    'payments',
                                    common.company,
                                    'company',
                                ),
                                cell: (r) =>
                                    r.payer_company ?? r.payer_name ?? '—',
                            },
                            {
                                id: 'amount',
                                header: sortableHeader(
                                    'payments',
                                    common.amount,
                                    'amount',
                                ),
                                cell: (r) =>
                                    formatMoney(r.amount, r.currency ?? 'EUR'),
                            },
                            {
                                id: 'method',
                                header: common.payment_method,
                                cell: (r) =>
                                    r.method
                                        ? (paymentMethods[r.method] ?? r.method)
                                        : '—',
                            },
                            {
                                id: 'provider',
                                header: t.provider ?? 'Provider',
                                cell: (r) => {
                                    const providers =
                                        translations.payment_providers ?? {};
                                    if (!r.provider) {
                                        return '—';
                                    }
                                    if (r.provider === 'stripe') {
                                        return (
                                            providers.stripe ??
                                            t.provider_stripe
                                        );
                                    }
                                    if (r.provider === 'mollie') {
                                        return (
                                            providers.mollie ??
                                            t.provider_mollie
                                        );
                                    }
                                    if (
                                        r.provider === 'manual_bank_transfer' ||
                                        r.provider === 'manual'
                                    ) {
                                        return (
                                            providers.manual ??
                                            t.provider_manual
                                        );
                                    }
                                    return (
                                        providers[r.provider] ?? r.provider
                                    );
                                },
                            },
                            {
                                id: 'status',
                                header: sortableHeader(
                                    'payments',
                                    common.status,
                                    'status',
                                ),
                                cell: (r) =>
                                    r.status ? (
                                        <StatusBadge
                                            label={
                                                paymentStatuses[r.status] ??
                                                r.status
                                            }
                                            tone={leadStatusTone(r.status)}
                                        />
                                    ) : (
                                        '—'
                                    ),
                            },
                            {
                                id: 'due',
                                header: sortableHeader(
                                    'payments',
                                    common.date,
                                    'date',
                                ),
                                cell: (r) =>
                                    r.paid_at
                                        ? formatDate(r.paid_at, app.locale)
                                        : formatDate(r.due_date, app.locale),
                            },
                            {
                                id: 'stripe_ref',
                                header: t.stripe_reference ?? 'Stripe',
                                cell: (r) => (
                                    <span className="font-mono text-xs">
                                        {r.stripe_payment_intent_id ||
                                            r.stripe_checkout_session_id ||
                                            '—'}
                                    </span>
                                ),
                            },
                            {
                                id: 'actions',
                                header: common.actions,
                                cell: (r) =>
                                    r.status === 'pending' ? (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    setMarkPaidTarget(r)
                                                }
                                            >
                                                {t.mark_paid}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'admin.payments.cancel',
                                                            r.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                {t.cancel}
                                            </Button>
                                            {r.invoice_id ? (
                                                <a
                                                    href={route(
                                                        'invoices.download',
                                                        r.invoice_id,
                                                    )}
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        {t.download_invoice}
                                                    </Button>
                                                </a>
                                            ) : null}
                                        </div>
                                    ) : (
                                        <div className="flex flex-wrap gap-2">
                                            {r.receipt_id ? (
                                                <a
                                                    href={route(
                                                        'invoices.download',
                                                        r.receipt_id,
                                                    )}
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        {t.download_receipt}
                                                    </Button>
                                                </a>
                                            ) : null}
                                            {r.can_resend_email ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'admin.payments.resend-email',
                                                                r.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    {t.resend_email}
                                                </Button>
                                            ) : null}
                                        </div>
                                    ),
                            },
                        ]}
                    />
                )}
                <Pagination
                    page={buyerMeta.page}
                    pageCount={buyerMeta.pageCount}
                    perPage={currentPerPage}
                    onPerPageChange={(next) =>
                        router.get(
                            route('admin.payments.index'),
                            queryParams({
                                per_page: next,
                                payments_page: 1,
                                payouts_page: 1,
                                commissions_page: 1,
                            }),
                            { preserveState: true, replace: true },
                        )
                    }
                    onPageChange={(next) =>
                        router.get(
                            route('admin.payments.index'),
                            queryParams({ payments_page: next }),
                            { preserveState: true },
                        )
                    }
                    labels={pageLabels}
                />
            </section>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.seller_payouts}
                </h2>
                {sellerPayouts.data.length === 0 ? (
                    <EmptyState title={common.empty} />
                ) : isMobile ? (
                    <MobileCardList
                        items={sellerPayouts.data.map((row) => ({
                            id: String(row.id),
                            title: row.payout_reference,
                            subtitle:
                                row.seller_company ?? row.seller_name ?? '—',
                            meta: row.status ? (
                                <StatusBadge
                                    label={
                                        paymentStatuses[row.status] ??
                                        row.status
                                    }
                                    tone={leadStatusTone(row.status)}
                                />
                            ) : null,
                            body: (
                                <p>
                                    {formatMoney(
                                        row.amount,
                                        row.currency ?? 'EUR',
                                    )}
                                </p>
                            ),
                            actions:
                                row.status === 'pending' ? (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                route(
                                                    'admin.payouts.mark-paid',
                                                    row.id,
                                                ),
                                            )
                                        }
                                    >
                                        {t.mark_payout_paid}
                                    </Button>
                                ) : null,
                        }))}
                    />
                ) : (
                    <DataTable
                        data={sellerPayouts.data}
                        getRowId={(r) => String(r.id)}
                        columns={[
                            {
                                id: 'ref',
                                header: sortableHeader(
                                    'payouts',
                                    common.reference,
                                    'reference',
                                ),
                                cell: (r) => r.payout_reference,
                            },
                            {
                                id: 'seller',
                                header: sortableHeader(
                                    'payouts',
                                    translations.admin.leads_bought.seller,
                                    'company',
                                ),
                                cell: (r) =>
                                    r.seller_company ?? r.seller_name ?? '—',
                            },
                            {
                                id: 'amount',
                                header: sortableHeader(
                                    'payouts',
                                    common.amount,
                                    'amount',
                                ),
                                cell: (r) =>
                                    formatMoney(r.amount, r.currency ?? 'EUR'),
                            },
                            {
                                id: 'status',
                                header: sortableHeader(
                                    'payouts',
                                    common.status,
                                    'status',
                                ),
                                cell: (r) =>
                                    r.status ? (
                                        <StatusBadge
                                            label={
                                                paymentStatuses[r.status] ??
                                                r.status
                                            }
                                            tone={leadStatusTone(r.status)}
                                        />
                                    ) : (
                                        '—'
                                    ),
                            },
                            {
                                id: 'due',
                                header: sortableHeader(
                                    'payouts',
                                    common.date,
                                    'date',
                                ),
                                cell: (r) =>
                                    formatDate(r.due_date, app.locale),
                            },
                            {
                                id: 'actions',
                                header: common.actions,
                                cell: (r) =>
                                    r.status === 'pending' ? (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    route(
                                                        'admin.payouts.mark-paid',
                                                        r.id,
                                                    ),
                                                )
                                            }
                                        >
                                            {t.mark_payout_paid}
                                        </Button>
                                    ) : (
                                        '—'
                                    ),
                            },
                        ]}
                    />
                )}
                <Pagination
                    page={payoutMeta.page}
                    pageCount={payoutMeta.pageCount}
                    perPage={currentPerPage}
                    onPerPageChange={(next) =>
                        router.get(
                            route('admin.payments.index'),
                            queryParams({
                                per_page: next,
                                payments_page: 1,
                                payouts_page: 1,
                                commissions_page: 1,
                            }),
                            { preserveState: true, replace: true },
                        )
                    }
                    onPageChange={(next) =>
                        router.get(
                            route('admin.payments.index'),
                            queryParams({ payouts_page: next }),
                            { preserveState: true },
                        )
                    }
                    labels={pageLabels}
                />
            </section>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.commissions_due}
                </h2>
                {commissions.data.length === 0 ? (
                    <EmptyState title={common.empty} />
                ) : isMobile ? (
                    <MobileCardList
                        items={commissions.data.map((row) => ({
                            id: String(row.id),
                            title: row.commission_reference,
                            subtitle:
                                row.seller_company ?? row.seller_name ?? '—',
                            meta: row.status ? (
                                <StatusBadge
                                    label={
                                        paymentStatuses[row.status] ??
                                        row.status
                                    }
                                    tone={leadStatusTone(row.status)}
                                />
                            ) : null,
                            body: (
                                <p>
                                    {formatMoney(row.amount, 'EUR')}
                                    {row.lead_reference
                                        ? ` · ${row.lead_reference}`
                                        : ''}
                                </p>
                            ),
                            actions: row.can_mark_paid ? (
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            route(
                                                'admin.commissions.mark-paid',
                                                row.id,
                                            ),
                                        )
                                    }
                                >
                                    {t.mark_commission_paid}
                                </Button>
                            ) : null,
                        }))}
                    />
                ) : (
                    <DataTable
                        data={commissions.data}
                        getRowId={(r) => String(r.id)}
                        columns={[
                            {
                                id: 'ref',
                                header: sortableHeader(
                                    'commissions',
                                    common.reference,
                                    'reference',
                                ),
                                cell: (r) => r.commission_reference,
                            },
                            {
                                id: 'seller',
                                header: sortableHeader(
                                    'commissions',
                                    translations.admin.leads_bought.seller,
                                    'company',
                                ),
                                cell: (r) =>
                                    r.seller_company ?? r.seller_name ?? '—',
                            },
                            {
                                id: 'amount',
                                header: sortableHeader(
                                    'commissions',
                                    common.amount,
                                    'amount',
                                ),
                                cell: (r) => formatMoney(r.amount, 'EUR'),
                            },
                            {
                                id: 'status',
                                header: sortableHeader(
                                    'commissions',
                                    common.status,
                                    'status',
                                ),
                                cell: (r) =>
                                    r.status ? (
                                        <StatusBadge
                                            label={
                                                paymentStatuses[r.status] ??
                                                r.status
                                            }
                                            tone={leadStatusTone(r.status)}
                                        />
                                    ) : (
                                        '—'
                                    ),
                            },
                            {
                                id: 'due',
                                header: sortableHeader(
                                    'commissions',
                                    common.date,
                                    'date',
                                ),
                                cell: (r) => formatDate(r.due_at, app.locale),
                            },
                            {
                                id: 'actions',
                                header: common.actions,
                                cell: (r) =>
                                    r.can_mark_paid ? (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    route(
                                                        'admin.commissions.mark-paid',
                                                        r.id,
                                                    ),
                                                )
                                            }
                                        >
                                            {t.mark_commission_paid}
                                        </Button>
                                    ) : (
                                        '—'
                                    ),
                            },
                        ]}
                    />
                )}
                <Pagination
                    page={commissionMeta.page}
                    pageCount={commissionMeta.pageCount}
                    perPage={currentPerPage}
                    onPerPageChange={(next) =>
                        router.get(
                            route('admin.payments.index'),
                            queryParams({
                                per_page: next,
                                payments_page: 1,
                                payouts_page: 1,
                                commissions_page: 1,
                            }),
                            { preserveState: true, replace: true },
                        )
                    }
                    onPageChange={(next) =>
                        router.get(
                            route('admin.payments.index'),
                            queryParams({ commissions_page: next }),
                            { preserveState: true },
                        )
                    }
                    labels={pageLabels}
                />
            </section>

            <Modal
                open={markPaidTarget != null}
                onClose={() => {
                    if (!markPaidProcessing) {
                        setMarkPaidTarget(null);
                    }
                }}
                title={t.mark_paid_confirm_title}
                size="sm"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={markPaidProcessing}
                            onClick={() => setMarkPaidTarget(null)}
                        >
                            {common.confirm_no_cancel ?? common.cancel}
                        </Button>
                        <Button
                            size="sm"
                            disabled={markPaidProcessing}
                            onClick={confirmMarkPaid}
                        >
                            {t.confirm_yes_mark_paid}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-rml-muted">{markPaidConfirmBody}</p>
                {markPaidTarget?.is_stripe ||
                markPaidTarget?.provider === 'stripe' ? (
                    <p className="mt-2 text-sm text-amber-800">
                        {t.mark_paid_stripe_override}
                    </p>
                ) : null}
            </Modal>
        </AppLayout>
    );
}
