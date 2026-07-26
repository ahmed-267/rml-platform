import { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    FormInput,
    MobileFilterDrawer,
    KpiCard,
    MobileCardList,
    Modal,
    Pagination,
    Select,
    SortableHeader,
    StatusBadge,
    TableActionButton,
    TableActions,
    Tabs,
    tableActionIcons,
    Textarea,
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
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';

type PaymentTab = 'buyer' | 'seller_payouts';

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
    notes?: string | null;
    invoice_id?: number | null;
    receipt_id?: number | null;
    can_resend_email?: boolean;
    can_mark_paid?: boolean;
    can_cancel?: boolean;
    can_edit?: boolean;
    can_delete?: boolean;
}

interface SellerPayoutRow {
    id: number;
    source: 'payout' | 'commission';
    reference: string;
    recipient: string | null;
    recipient_type: 'company' | 'agent';
    lead_reference: string | null;
    scheme: string | null;
    metric: string | null;
    rate: string | null;
    total_payout: number | null;
    currency?: string | null;
    status: string | null;
    due_date: string | null;
    paid_date: string | null;
    notes?: string | null;
    statement_id?: number | null;
    can_mark_paid?: boolean;
    can_edit?: boolean;
    can_delete?: boolean;
}

type PaymentFilters = {
    tab?: string | null;
    payment_status?: string | null;
    payment_method?: string | null;
    payout_status?: string | null;
    search?: string | null;
    per_page?: number | string | null;
    payments_sort?: string | null;
    payments_direction?: string | null;
    payouts_sort?: string | null;
    payouts_direction?: string | null;
};

export default function PaymentsIndex({
    tab = 'buyer',
    buyerPayments,
    sellerPayouts,
    filters,
    summaries,
    can_delete_payments = false,
}: {
    tab?: PaymentTab;
    buyerPayments: Paginator<PaymentRow>;
    sellerPayouts: Paginator<SellerPayoutRow>;
    filters: PaymentFilters;
    can_delete_payments?: boolean;
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
    const { translations, app, auth } = usePage<PageProps>().props;
    const t = translations.admin.payments;
    const common = translations.admin.common;
    const paymentStatuses = translations.payment_statuses;
    const paymentMethods = translations.payment_methods;
    const isMobile = useIsMobile();

    const activeTab: PaymentTab =
        tab === 'seller_payouts' || filters.tab === 'seller_payouts'
            ? 'seller_payouts'
            : 'buyer';

    const isSuperAdmin =
        can_delete_payments ||
        (auth.user?.roles ?? []).includes('super_admin');

    const [markPaidTarget, setMarkPaidTarget] = useState<PaymentRow | null>(
        null,
    );
    const [markPaidProcessing, setMarkPaidProcessing] = useState(false);
    const [viewRow, setViewRow] = useState<
        PaymentRow | SellerPayoutRow | null
    >(null);
    const [editRow, setEditRow] = useState<
        PaymentRow | SellerPayoutRow | null
    >(null);
    const [deleteRow, setDeleteRow] = useState<
        PaymentRow | SellerPayoutRow | null
    >(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);
    const [editProcessing, setEditProcessing] = useState(false);

    const editForm = useForm({
        amount: '',
        due_date: '',
        status: '',
        notes: '',
    });

    const openEdit = (row: PaymentRow | SellerPayoutRow) => {
        const isBuyer = 'payment_reference' in row;
        editForm.setData({
            amount: String(
                isBuyer
                    ? (row.amount ?? '')
                    : ((row as SellerPayoutRow).total_payout ?? ''),
            ),
            due_date: row.due_date ?? '',
            status: row.status ?? '',
            notes: isBuyer
                ? ((row as PaymentRow).notes ?? '')
                : ((row as SellerPayoutRow).notes ?? ''),
        });
        setEditRow(row);
    };

    const submitEdit = () => {
        if (!editRow) {
            return;
        }

        const payload: Record<string, string | null> = {
            amount: editForm.data.amount,
            due_date: editForm.data.due_date || null,
            status: editForm.data.status || null,
        };

        let url: string;
        if ('payment_reference' in editRow) {
            payload.notes = editForm.data.notes || null;
            url = route('admin.payments.update', editRow.id);
        } else {
            const seller = editRow as SellerPayoutRow;
            url =
                seller.source === 'commission'
                    ? route('admin.commissions.update', seller.id)
                    : route('admin.payouts.update', seller.id);
        }

        setEditProcessing(true);
        router.put(url, payload, {
            preserveScroll: true,
            onSuccess: () => setEditRow(null),
            onFinish: () => setEditProcessing(false),
        });
    };

    const confirmDelete = () => {
        if (!deleteRow) {
            return;
        }

        setDeleteProcessing(true);

        let url: string;
        if ('payment_reference' in deleteRow) {
            url = route('admin.payments.destroy', deleteRow.id);
        } else {
            const seller = deleteRow as SellerPayoutRow;
            url =
                seller.source === 'commission'
                    ? route('admin.commissions.destroy', seller.id)
                    : route('admin.payouts.destroy', seller.id);
        }

        router.delete(url, {
            preserveScroll: true,
            onFinish: () => {
                setDeleteProcessing(false);
                setDeleteRow(null);
            },
        });
    };

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
    const [paymentMethod, setPaymentMethod] = useState(
        filters.payment_method ?? '',
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

    const buyerMeta = paginationMeta(buyerPayments);
    const payoutMeta = paginationMeta(sellerPayouts);
    const currentPerPage = Number(
        filters.per_page ??
            (activeTab === 'buyer' ? buyerMeta.perPage : payoutMeta.perPage) ??
            10,
    );

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        tab: activeTab,
        search: search || undefined,
        payment_status:
            activeTab === 'buyer' ? paymentStatus || undefined : undefined,
        payment_method:
            activeTab === 'buyer' ? paymentMethod || undefined : undefined,
        payout_status:
            activeTab === 'seller_payouts'
                ? payoutStatus || undefined
                : undefined,
        per_page: currentPerPage,
        payments_sort: paymentsSort,
        payments_direction: paymentsDirection,
        payouts_sort: payoutsSort,
        payouts_direction: payoutsDirection,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(
            route('admin.payments.index'),
            queryParams({
                payments_page: 1,
                payouts_page: 1,
            }),
            { preserveState: true, replace: true },
        );
    };

    useInstantListFilters(
        applyFilters,
        search,
        activeTab === 'buyer'
            ? [paymentStatus, paymentMethod]
            : [payoutStatus],
    );

    const resetFilters = () => {
        setSearch('');
        setPaymentStatus('');
        setPaymentMethod('');
        setPayoutStatus('');
        router.get(
            route('admin.payments.index'),
            {
                tab: activeTab,
                per_page: 10,
                payments_sort: 'date',
                payments_direction: 'desc',
                payouts_sort: 'date',
                payouts_direction: 'desc',
            },
            { preserveState: true, replace: true },
        );
    };

    const changeTab = (id: string) => {
        router.get(
            route('admin.payments.index'),
            { tab: id, per_page: currentPerPage },
            { preserveState: false, replace: true },
        );
    };

    const handleTableSort = (
        table: 'payments' | 'payouts',
        column: string,
    ) => {
        const current =
            table === 'payments'
                ? { sort: paymentsSort, direction: paymentsDirection }
                : { sort: payoutsSort, direction: payoutsDirection };

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
        table: 'payments' | 'payouts',
        label: string,
        column: string,
    ) => {
        const currentSort =
            table === 'payments' ? paymentsSort : payoutsSort;
        const currentDirection =
            table === 'payments' ? paymentsDirection : payoutsDirection;

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

    const recipientTypeLabel = (type: string | null | undefined) => {
        if (type === 'company') {
            return t.recipient_company;
        }
        if (type === 'agent') {
            return t.recipient_agent;
        }
        return '—';
    };

    const deleteConfirmBody = useMemo(() => {
        if (!deleteRow) {
            return '';
        }
        const ref =
            'payment_reference' in deleteRow
                ? deleteRow.payment_reference
                : deleteRow.reference;
        return (t.delete_confirm_body ?? '').replaceAll(':ref', ref);
    }, [deleteRow, t.delete_confirm_body]);

    const statusOptions = [
        { label: common.all_statuses, value: '' },
        ...Object.entries(paymentStatuses).map(([value, label]) => ({
            label,
            value,
        })),
    ];

    const methodOptions = [
        { label: common.all ?? 'All', value: '' },
        ...Object.entries(paymentMethods).map(([value, label]) => ({
            label,
            value,
        })),
    ];

    const buyerActions = (r: PaymentRow) => (
        <TableActions>
            <TableActionButton
                label={common.view}
                icon={tableActionIcons.view}
                onClick={() => setViewRow(r)}
            />
            {r.can_edit ? (
                <TableActionButton
                    label={t.edit_payment ?? common.edit}
                    icon={tableActionIcons.edit}
                    onClick={() => openEdit(r)}
                />
            ) : null}
            {r.can_mark_paid ? (
                <TableActionButton
                    label={t.mark_paid}
                    icon={tableActionIcons.markPaid}
                    tone="success"
                    onClick={() => setMarkPaidTarget(r)}
                />
            ) : null}
            {r.can_cancel ? (
                <TableActionButton
                    label={t.cancel}
                    icon={tableActionIcons.cancel}
                    tone="danger"
                    onClick={() =>
                        router.post(route('admin.payments.cancel', r.id))
                    }
                />
            ) : null}
            {r.can_resend_email && !r.can_mark_paid ? (
                <TableActionButton
                    label={t.resend_email}
                    icon={tableActionIcons.reinstate}
                    onClick={() =>
                        router.post(
                            route('admin.payments.resend-email', r.id),
                        )
                    }
                />
            ) : null}
            {(r.can_delete || isSuperAdmin) && (
                <TableActionButton
                    label={t.delete_payment ?? common.delete}
                    icon={tableActionIcons.delete}
                    tone="danger"
                    onClick={() => setDeleteRow(r)}
                />
            )}
        </TableActions>
    );

    const sellerActions = (r: SellerPayoutRow) => (
        <TableActions>
            <TableActionButton
                label={common.view}
                icon={tableActionIcons.view}
                onClick={() => setViewRow(r)}
            />
            {r.can_edit ? (
                <TableActionButton
                    label={t.edit_payment ?? common.edit}
                    icon={tableActionIcons.edit}
                    onClick={() => openEdit(r)}
                />
            ) : null}
            {r.can_mark_paid ? (
                <TableActionButton
                    label={
                        r.source === 'commission'
                            ? t.mark_commission_paid
                            : t.mark_payout_paid
                    }
                    icon={tableActionIcons.markPaid}
                    tone="success"
                    onClick={() =>
                        router.post(
                            r.source === 'commission'
                                ? route(
                                      'admin.commissions.mark-paid',
                                      r.id,
                                  )
                                : route('admin.payouts.mark-paid', r.id),
                        )
                    }
                />
            ) : null}
            {(r.can_delete || isSuperAdmin) && (
                <TableActionButton
                    label={t.delete_payment ?? common.delete}
                    icon={tableActionIcons.delete}
                    tone="danger"
                    onClick={() => setDeleteRow(r)}
                />
            )}
        </TableActions>
    );

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

            <Tabs
                items={[
                    { id: 'buyer', label: t.tab_buyer ?? t.buyer_payments },
                    {
                        id: 'seller_payouts',
                        label: t.tab_seller_payouts ?? t.seller_payouts,
                    },
                ]}
                value={activeTab}
                onChange={changeTab}
            >
                {activeTab === 'buyer' ? (
                    <div className="space-y-3">
                        <FilterBar
                            compact
                            search={search}
                            onSearchChange={setSearch}
                            searchLabel={common.search}
                            searchPlaceholder={common.search}
                            onOpenMobileFilters={() => setFiltersOpen(true)}
                            actions={
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={resetFilters}
                                >
                                    {common.reset}
                                </Button>
                            }
                        >
                            <div className="w-full sm:w-[12.5rem]">
                                <Select
                                    label={common.status}
                                    aria-label={common.status}
                                    value={paymentStatus}
                                    onChange={(e) =>
                                        setPaymentStatus(e.target.value)
                                    }
                                    options={statusOptions}
                                />
                            </div>
                            <div className="w-full sm:w-[12.5rem]">
                                <Select
                                    label={
                                        t.filter_method ??
                                        common.payment_method
                                    }
                                    aria-label={
                                        t.filter_method ??
                                        common.payment_method
                                    }
                                    value={paymentMethod}
                                    onChange={(e) =>
                                        setPaymentMethod(e.target.value)
                                    }
                                    options={methodOptions}
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
                                onChange={(e) =>
                                    setPaymentStatus(e.target.value)
                                }
                                options={statusOptions}
                            />
                            <Select
                                label={
                                    t.filter_method ?? common.payment_method
                                }
                                aria-label={
                                    t.filter_method ?? common.payment_method
                                }
                                value={paymentMethod}
                                onChange={(e) =>
                                    setPaymentMethod(e.target.value)
                                }
                                options={methodOptions}
                            />
                        </MobileFilterDrawer>

                        {buyerPayments.data.length === 0 ? (
                            <EmptyState title={common.empty} />
                        ) : isMobile ? (
                            <MobileCardList
                                items={buyerPayments.data.map((row) => ({
                                    id: String(row.id),
                                    title: row.payment_reference,
                                    subtitle:
                                        row.payer_company ??
                                        row.payer_name ??
                                        '—',
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
                                    actions: buyerActions(row),
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
                                            r.payer_company ??
                                            r.payer_name ??
                                            '—',
                                    },
                                    {
                                        id: 'amount',
                                        header: sortableHeader(
                                            'payments',
                                            common.amount,
                                            'amount',
                                        ),
                                        cell: (r) =>
                                            formatMoney(
                                                r.amount,
                                                r.currency ?? 'EUR',
                                            ),
                                    },
                                    {
                                        id: 'method',
                                        header: common.payment_method,
                                        cell: (r) =>
                                            r.method
                                                ? (paymentMethods[r.method] ??
                                                  r.method)
                                                : '—',
                                    },
                                    {
                                        id: 'provider',
                                        header: t.provider ?? 'Provider',
                                        cell: (r) => {
                                            const providers =
                                                translations.payment_providers ??
                                                {};
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
                                                r.provider ===
                                                    'manual_bank_transfer' ||
                                                r.provider === 'manual'
                                            ) {
                                                return (
                                                    providers.manual ??
                                                    t.provider_manual
                                                );
                                            }
                                            return (
                                                providers[r.provider] ??
                                                r.provider
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
                                                        paymentStatuses[
                                                            r.status
                                                        ] ?? r.status
                                                    }
                                                    tone={leadStatusTone(
                                                        r.status,
                                                    )}
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
                                                ? formatDate(
                                                      r.paid_at,
                                                      app.locale,
                                                  )
                                                : formatDate(
                                                      r.due_date,
                                                      app.locale,
                                                  ),
                                    },
                                    {
                                        id: 'actions',
                                        header: common.actions,
                                        cell: (r) => buyerActions(r),
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
                    </div>
                ) : (
                    <div className="space-y-3">
                        <FilterBar
                            compact
                            search={search}
                            onSearchChange={setSearch}
                            searchLabel={common.search}
                            searchPlaceholder={common.search}
                            onOpenMobileFilters={() => setFiltersOpen(true)}
                            actions={
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={resetFilters}
                                >
                                    {common.reset}
                                </Button>
                            }
                        >
                            <div className="w-full sm:w-[12.5rem]">
                                <Select
                                    label={common.status}
                                    aria-label={common.status}
                                    value={payoutStatus}
                                    onChange={(e) =>
                                        setPayoutStatus(e.target.value)
                                    }
                                    options={statusOptions}
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
                                value={payoutStatus}
                                onChange={(e) =>
                                    setPayoutStatus(e.target.value)
                                }
                                options={statusOptions}
                            />
                        </MobileFilterDrawer>

                        {sellerPayouts.data.length === 0 ? (
                            <EmptyState title={common.empty} />
                        ) : isMobile ? (
                            <MobileCardList
                                items={sellerPayouts.data.map((row) => ({
                                    id: `${row.source}-${row.id}`,
                                    title: row.reference,
                                    subtitle: row.recipient ?? '—',
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
                                                row.total_payout,
                                                row.currency ?? 'EUR',
                                            )}
                                            {row.lead_reference
                                                ? ` · ${row.lead_reference}`
                                                : ''}
                                        </p>
                                    ),
                                    actions: sellerActions(row),
                                }))}
                            />
                        ) : (
                            <DataTable
                                data={sellerPayouts.data}
                                getRowId={(r) => `${r.source}-${r.id}`}
                                columns={[
                                    {
                                        id: 'ref',
                                        header: sortableHeader(
                                            'payouts',
                                            common.reference,
                                            'reference',
                                        ),
                                        cell: (r) => r.reference,
                                    },
                                    {
                                        id: 'recipient',
                                        header: sortableHeader(
                                            'payouts',
                                            t.recipient,
                                            'name',
                                        ),
                                        cell: (r) => r.recipient ?? '—',
                                    },
                                    {
                                        id: 'recipient_type',
                                        header: t.recipient_type,
                                        cell: (r) =>
                                            recipientTypeLabel(
                                                r.recipient_type,
                                            ),
                                    },
                                    {
                                        id: 'lead',
                                        header: 'Lead',
                                        cell: (r) =>
                                            r.lead_reference ?? '—',
                                    },
                                    {
                                        id: 'scheme',
                                        header:
                                            translations.admin.leads_bought
                                                ?.filter_scheme ?? 'Scheme',
                                        cell: (r) => r.scheme ?? '—',
                                    },
                                    {
                                        id: 'metric',
                                        header: t.metric,
                                        cell: (r) => r.metric ?? '—',
                                    },
                                    {
                                        id: 'rate',
                                        header: t.rate,
                                        cell: (r) => r.rate ?? '—',
                                    },
                                    {
                                        id: 'total',
                                        header: sortableHeader(
                                            'payouts',
                                            t.total_payout,
                                            'amount',
                                        ),
                                        cell: (r) =>
                                            formatMoney(
                                                r.total_payout,
                                                r.currency ?? 'EUR',
                                            ),
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
                                                        paymentStatuses[
                                                            r.status
                                                        ] ?? r.status
                                                    }
                                                    tone={leadStatusTone(
                                                        r.status,
                                                    )}
                                                />
                                            ) : (
                                                '—'
                                            ),
                                    },
                                    {
                                        id: 'due',
                                        header: sortableHeader(
                                            'payouts',
                                            t.due_date,
                                            'date',
                                        ),
                                        cell: (r) =>
                                            formatDate(
                                                r.due_date,
                                                app.locale,
                                            ),
                                    },
                                    {
                                        id: 'paid',
                                        header: t.paid_date,
                                        cell: (r) =>
                                            formatDate(
                                                r.paid_date,
                                                app.locale,
                                            ),
                                    },
                                    {
                                        id: 'actions',
                                        header: common.actions,
                                        cell: (r) => sellerActions(r),
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
                                        payouts_page: 1,
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
                    </div>
                )}
            </Tabs>

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

            <Modal
                open={viewRow != null}
                onClose={() => setViewRow(null)}
                title={common.view}
                size="md"
                footer={
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setViewRow(null)}
                    >
                        {common.cancel}
                    </Button>
                }
            >
                {viewRow && 'payment_reference' in viewRow ? (
                    <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <ViewField
                            label={common.reference}
                            value={viewRow.payment_reference}
                        />
                        <ViewField
                            label={common.status}
                            value={
                                viewRow.status
                                    ? (paymentStatuses[viewRow.status] ??
                                      viewRow.status)
                                    : '—'
                            }
                        />
                        <ViewField
                            label={common.amount}
                            value={formatMoney(
                                viewRow.amount,
                                viewRow.currency ?? 'EUR',
                            )}
                        />
                        <ViewField
                            label={t.due_date}
                            value={formatDate(viewRow.due_date, app.locale)}
                        />
                        <ViewField
                            label={t.paid_date}
                            value={formatDate(viewRow.paid_at, app.locale)}
                        />
                        <ViewField
                            label={common.company}
                            value={
                                viewRow.payer_company ??
                                viewRow.payer_name ??
                                '—'
                            }
                        />
                        <ViewField
                            label={common.payment_method}
                            value={
                                viewRow.method
                                    ? (paymentMethods[viewRow.method] ??
                                      viewRow.method)
                                    : '—'
                            }
                        />
                        <ViewField
                            label={common.notes ?? 'Notes'}
                            value={viewRow.notes ?? '—'}
                        />
                    </dl>
                ) : viewRow ? (
                    <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <ViewField
                            label={common.reference}
                            value={viewRow.reference}
                        />
                        <ViewField
                            label={t.recipient}
                            value={viewRow.recipient ?? '—'}
                        />
                        <ViewField
                            label={t.recipient_type}
                            value={recipientTypeLabel(viewRow.recipient_type)}
                        />
                        <ViewField
                            label="Lead"
                            value={viewRow.lead_reference ?? '—'}
                        />
                        <ViewField
                            label="Scheme"
                            value={viewRow.scheme ?? '—'}
                        />
                        <ViewField
                            label={t.metric}
                            value={viewRow.metric ?? '—'}
                        />
                        <ViewField
                            label={t.rate}
                            value={viewRow.rate ?? '—'}
                        />
                        <ViewField
                            label={t.total_payout}
                            value={formatMoney(
                                viewRow.total_payout,
                                viewRow.currency ?? 'EUR',
                            )}
                        />
                        <ViewField
                            label={common.status}
                            value={
                                viewRow.status
                                    ? (paymentStatuses[viewRow.status] ??
                                      viewRow.status)
                                    : '—'
                            }
                        />
                        <ViewField
                            label={t.due_date}
                            value={formatDate(viewRow.due_date, app.locale)}
                        />
                        <ViewField
                            label={t.paid_date}
                            value={formatDate(viewRow.paid_date, app.locale)}
                        />
                    </dl>
                ) : null}
            </Modal>

            <Modal
                open={editRow != null}
                onClose={() => {
                    if (!editProcessing) {
                        setEditRow(null);
                    }
                }}
                title={t.edit_payment}
                size="md"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={editProcessing}
                            onClick={() => setEditRow(null)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            size="sm"
                            disabled={editProcessing}
                            onClick={submitEdit}
                        >
                            {common.save}
                        </Button>
                    </>
                }
            >
                <div className="space-y-3">
                    <FormInput
                        label={common.amount}
                        type="number"
                        step="0.01"
                        min="0"
                        value={editForm.data.amount}
                        onChange={(e) =>
                            editForm.setData('amount', e.target.value)
                        }
                        error={editForm.errors.amount}
                    />
                    <FormInput
                        label={t.due_date}
                        type="date"
                        value={editForm.data.due_date}
                        onChange={(e) =>
                            editForm.setData('due_date', e.target.value)
                        }
                        error={editForm.errors.due_date}
                    />
                    <Select
                        label={common.status}
                        value={editForm.data.status}
                        onChange={(e) =>
                            editForm.setData('status', e.target.value)
                        }
                        options={Object.entries(paymentStatuses).map(
                            ([value, label]) => ({ label, value }),
                        )}
                    />
                    {'payment_reference' in (editRow ?? {}) ? (
                        <Textarea
                            label={common.notes ?? 'Notes'}
                            value={editForm.data.notes}
                            onChange={(e) =>
                                editForm.setData('notes', e.target.value)
                            }
                            error={editForm.errors.notes}
                        />
                    ) : null}
                </div>
            </Modal>

            <Modal
                open={deleteRow != null}
                onClose={() => {
                    if (!deleteProcessing) {
                        setDeleteRow(null);
                    }
                }}
                title={t.delete_payment}
                size="sm"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={deleteProcessing}
                            onClick={() => setDeleteRow(null)}
                        >
                            {common.confirm_no_cancel ?? common.cancel}
                        </Button>
                        <Button
                            size="sm"
                            variant="danger"
                            disabled={deleteProcessing}
                            onClick={confirmDelete}
                        >
                            {common.delete}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-rml-muted">{deleteConfirmBody}</p>
            </Modal>
        </AppLayout>
    );
}

function ViewField({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                {label}
            </dt>
            <dd className="mt-0.5 text-rml-text">{value || '—'}</dd>
        </div>
    );
}
