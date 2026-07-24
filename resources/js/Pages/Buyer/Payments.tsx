import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Alert,
    Button,
    DataTable,
    EmptyState,
    KpiCard,
    MobileCardList,
    Pagination,
    SortableHeader,
    StatusBadge,
    Tabs,
} from '@/Components/ui';
import type { SortDirection } from '@/Components/ui/SortableHeader';
import {
    nextSortDirection,
    paginationLabels,
    paginationMeta,
    resolveSortDirection,
    type Paginator,
} from '@/lib/list-helpers';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface BankInstructions {
    account_name?: string | null;
    iban?: string | null;
    bic?: string | null;
    bank_name?: string | null;
    instructions?: string | null;
    amount?: number;
    currency?: string;
    payment_reference?: string;
}

interface PaymentRow {
    id: number;
    payment_reference: string;
    amount: number | null;
    currency: string | null;
    method: string | null;
    status: string | null;
    due_date: string | null;
    paid_at: string | null;
    created_at: string | null;
    purchase_id: number | null;
    purchase_reference: string | null;
    display_reference: string | null;
    can_cancel: boolean;
    can_pay?: boolean;
    bank_instructions?: BankInstructions | null;
    invoice_id?: number | null;
    receipt_id?: number | null;
    can_download_invoice?: boolean;
    can_download_receipt?: boolean;
}

function formatMoney(
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

export default function BuyerPayments({
    kpis,
    payments,
    tab,
    tabCounts,
    filters,
    card_configured = true,
    mollie_configured,
}: {
    kpis: {
        pending_to_buy: number;
        pending_payments: number;
        leads_purchased: number;
        total_spent: number;
    };
    payments: Paginator<PaymentRow>;
    tab: 'pending' | 'history';
    tabCounts: {
        pending: number;
        history: number;
    };
    filters: {
        tab?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    card_configured?: boolean;
    mollie_configured?: boolean;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.buyer?.payments ?? {};
    const common = translations.buyer?.common ?? {};
    const docs = translations.documents ?? {};
    const paymentStatuses = translations.payment_statuses ?? {};
    const paymentMethods = translations.payment_methods ?? {};
    const statusLabels = { ...paymentStatuses, ...paymentMethods };
    const isMobile = useIsMobile();
    const cardConfigured = card_configured ?? mollie_configured ?? true;

    const currentTab = tab === 'history' ? 'history' : 'pending';
    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(payments);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        tab: currentTab,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const handleSort = (column: string) => {
        router.get(
            route('buyer.payments'),
            queryParams({
                sort: column,
                direction: nextSortDirection(
                    currentSort,
                    column,
                    currentDirection,
                ),
                page: 1,
            }),
            { preserveState: true, replace: true },
        );
    };

    const sortableHeader = (label: string, column: string) => (
        <SortableHeader
            label={label}
            column={column}
            currentSort={currentSort}
            currentDirection={currentDirection}
            onSort={handleSort}
            sortAscLabel={common.sort_asc}
            sortDescLabel={common.sort_desc}
        />
    );

    const cancelPayment = (paymentId: number) => {
        router.post(route('buyer.payments.cancel', paymentId), {}, {
            preserveScroll: true,
        });
    };

    const payNow = (paymentId: number) => {
        router.post(route('buyer.payments.pay', paymentId));
    };

    const renderActions = (row: PaymentRow) => (
        <div className="flex flex-wrap gap-2">
            {row.can_pay && (
                <Button size="sm" onClick={() => payNow(row.id)}>
                    {t.pay_now ?? t.continue_payment}
                </Button>
            )}
            {row.can_cancel && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => cancelPayment(row.id)}
                >
                    {t.cancel_payment}
                </Button>
            )}
            {row.can_download_invoice && row.invoice_id && (
                <a href={route('invoices.download', row.invoice_id)}>
                    <Button size="sm" variant="outline">
                        {t.download_invoice ?? docs.download_invoice}
                    </Button>
                </a>
            )}
            {row.can_download_receipt && row.receipt_id && (
                <a href={route('invoices.download', row.receipt_id)}>
                    <Button size="sm" variant="outline">
                        {t.download_receipt ?? docs.download_receipt}
                    </Button>
                </a>
            )}
            {row.purchase_id && (
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                        router.visit(
                            route('buyer.purchases.show', row.purchase_id!),
                        )
                    }
                >
                    {common.view}
                </Button>
            )}
        </div>
    );

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            {!cardConfigured && (
                <Alert variant="warning">
                    {t.provider_not_configured}
                </Alert>
            )}

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label={translations.buyer?.dashboard?.kpi_pending_to_buy ?? ''}
                    value={kpis.pending_to_buy}
                    tone="warning"
                />
                <KpiCard
                    label={
                        translations.buyer?.dashboard?.kpi_pending_payments ?? ''
                    }
                    value={kpis.pending_payments}
                    tone="warning"
                />
                <KpiCard
                    label={translations.buyer?.dashboard?.kpi_bought ?? ''}
                    value={kpis.leads_purchased}
                    tone="info"
                />
                <KpiCard
                    label={translations.buyer?.dashboard?.kpi_total_spent ?? ''}
                    value={formatMoney(kpis.total_spent)}
                    tone="default"
                />
            </div>

            <Tabs
                value={currentTab}
                onChange={(next) =>
                    router.get(
                        route('buyer.payments'),
                        queryParams({ tab: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                items={[
                    {
                        id: 'pending',
                        label: t.pending_table ?? 'Pending',
                        count: tabCounts.pending,
                    },
                    {
                        id: 'history',
                        label: t.history ?? 'History',
                        count: tabCounts.history,
                    },
                ]}
            >
                {payments.data.length === 0 ? (
                    <EmptyState title={t.empty} />
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.empty}
                        items={payments.data.map((row) => ({
                            id: String(row.id),
                            title: (
                                <span className="font-mono text-sm">
                                    {row.payment_reference}
                                </span>
                            ),
                            subtitle: row.display_reference ?? undefined,
                            meta: row.status ? (
                                <StatusBadge
                                    label={leadStatusLabel(
                                        row.status,
                                        statusLabels,
                                    )}
                                    tone={leadStatusTone(row.status)}
                                />
                            ) : null,
                            body: (
                                <div className="space-y-1 text-rml-muted">
                                    <p>
                                        {t.amount}:{' '}
                                        {formatMoney(
                                            row.amount,
                                            row.currency ?? 'EUR',
                                        )}
                                    </p>
                                    <p>
                                        {t.method}:{' '}
                                        {row.method
                                            ? leadStatusLabel(
                                                  row.method,
                                                  statusLabels,
                                              )
                                            : '—'}
                                    </p>
                                    {row.bank_instructions && (
                                        <div className="mt-2 rounded-lg border border-rml-border bg-rml-bg p-3 text-xs">
                                            <p className="font-medium text-rml-text">
                                                {t.bank_details}
                                            </p>
                                            <p>
                                                {t.iban}:{' '}
                                                {row.bank_instructions.iban}
                                            </p>
                                            <p>
                                                {t.payment_reference_label}:{' '}
                                                {
                                                    row.bank_instructions
                                                        .payment_reference
                                                }
                                            </p>
                                            <p className="mt-1">
                                                {t.release_after_confirm}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            ),
                            actions: renderActions(row),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={payments.data}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.empty}
                        columns={[
                            {
                                id: 'reference',
                                header: sortableHeader(
                                    t.reference ?? '',
                                    'reference',
                                ),
                                cell: (row) => (
                                    <span className="font-mono text-sm">
                                        {row.payment_reference}
                                    </span>
                                ),
                            },
                            {
                                id: 'display',
                                header:
                                    translations.buyer?.purchases
                                        ?.lead_or_package ?? '',
                                cell: (row) => row.display_reference ?? '—',
                            },
                            {
                                id: 'amount',
                                header: sortableHeader(t.amount ?? '', 'amount'),
                                cell: (row) =>
                                    formatMoney(
                                        row.amount,
                                        row.currency ?? 'EUR',
                                    ),
                            },
                            {
                                id: 'method',
                                header: sortableHeader(t.method ?? '', 'method'),
                                cell: (row) =>
                                    row.method
                                        ? leadStatusLabel(
                                              row.method,
                                              statusLabels,
                                          )
                                        : '—',
                            },
                            {
                                id: 'status',
                                header: sortableHeader(
                                    common.status ?? '',
                                    'status',
                                ),
                                cell: (row) =>
                                    row.status ? (
                                        <StatusBadge
                                            label={leadStatusLabel(
                                                row.status,
                                                statusLabels,
                                            )}
                                            tone={leadStatusTone(row.status)}
                                        />
                                    ) : (
                                        '—'
                                    ),
                            },
                            {
                                id: 'due',
                                header: sortableHeader(
                                    t.due_date ?? '',
                                    'due_date',
                                ),
                                cell: (row) =>
                                    row.due_date
                                        ? new Date(
                                              row.due_date,
                                          ).toLocaleDateString(app.locale)
                                        : '—',
                            },
                            {
                                id: 'actions',
                                header: common.actions,
                                cell: (row) => renderActions(row),
                            },
                        ]}
                    />
                )}

                <Pagination
                    page={page}
                    pageCount={pageCount}
                    perPage={currentPerPage}
                    onPerPageChange={(next) =>
                        router.get(
                            route('buyer.payments'),
                            queryParams({ per_page: next, page: 1 }),
                            { preserveState: true, replace: true },
                        )
                    }
                    onPageChange={(nextPage) =>
                        router.get(
                            route('buyer.payments'),
                            queryParams({ page: nextPage }),
                            { preserveState: true },
                        )
                    }
                    labels={paginationLabels(common)}
                />
            </Tabs>
        </AppLayout>
    );
}
