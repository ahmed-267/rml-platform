import { FormEvent } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FormInput,
    KpiCard,
    MobileCardList,
    Pagination,
    SortableHeader,
    StatusBadge,
} from '@/Components/ui';
import type { SortDirection } from '@/Components/ui/SortableHeader';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import {
    nextSortDirection,
    paginationLabels,
    paginationMeta,
    resolveSortDirection,
    type Paginator,
} from '@/lib/list-helpers';
import type { PageProps } from '@/types';

interface BankDetails {
    bank_account_iban: string | null;
    bank_account_name: string | null;
    payout_method: string | null;
}

interface CommissionRow {
    id: number;
    commission_reference: string | null;
    lead_reference: string | null;
    percentage: number | null;
    commission_amount: number | null;
    status: string | null;
    due_at: string | null;
    paid_at: string | null;
    seller_name: string | null;
    statement_id?: number | null;
}

interface PayoutRow {
    id: number;
    payout_reference: string | null;
    lead_reference?: string | null;
    amount: number | null;
    currency: string | null;
    status: string | null;
    due_date: string | null;
    paid_at: string | null;
    notes: string | null;
    statement_id?: number | null;
}

interface Filters {
    commission_sort?: string | null;
    commission_direction?: string | null;
    commission_per_page?: number | string | null;
    payout_sort?: string | null;
    payout_direction?: string | null;
    payout_per_page?: number | string | null;
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

export default function SellerPayments({
    bank_details,
    commissions,
    payouts,
    summaries,
    filters,
}: {
    bank_details: BankDetails;
    commissions: Paginator<CommissionRow>;
    payouts: Paginator<PayoutRow>;
    summaries: {
        pending_payout: number;
        paid_out: number;
        commission_due: number;
        commission_paid: number;
    };
    filters: Filters;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.seller.payments;
    const common = translations.seller.common;
    const statuses = translations.statuses;
    const leadStatuses = translations.lead_statuses;
    const isMobile = useIsMobile();
    const statusLabels = { ...statuses, ...leadStatuses };

    const commissionSort = filters.commission_sort ?? 'date';
    const commissionDirection: SortDirection = resolveSortDirection(
        filters.commission_direction,
    );
    const commissionMeta = paginationMeta(commissions);
    const commissionPerPage = Number(
        filters.commission_per_page ?? commissionMeta.perPage ?? 10,
    );

    const payoutSort = filters.payout_sort ?? 'date';
    const payoutDirection: SortDirection = resolveSortDirection(
        filters.payout_direction,
    );
    const payoutMeta = paginationMeta(payouts);
    const payoutPerPage = Number(
        filters.payout_per_page ?? payoutMeta.perPage ?? 10,
    );

    const baseParams = () => ({
        commission_sort: commissionSort,
        commission_direction: commissionDirection,
        commission_per_page: commissionPerPage,
        payout_sort: payoutSort,
        payout_direction: payoutDirection,
        payout_per_page: payoutPerPage,
        commissions_page: commissionMeta.page,
        payouts_page: payoutMeta.page,
    });

    const visitPayments = (
        overrides: Record<string, string | number | undefined> = {},
    ) => {
        router.get(route('seller.payments'), { ...baseParams(), ...overrides }, {
            preserveState: true,
            replace: true,
        });
    };

    const commissionHeader = (label: string, column: string) => (
        <SortableHeader
            label={label}
            column={column}
            currentSort={commissionSort}
            currentDirection={commissionDirection}
            onSort={(next) =>
                visitPayments({
                    commission_sort: next,
                    commission_direction: nextSortDirection(
                        commissionSort,
                        next,
                        commissionDirection,
                    ),
                    commissions_page: 1,
                })
            }
            sortAscLabel={common.sort_asc}
            sortDescLabel={common.sort_desc}
        />
    );

    const payoutHeader = (label: string, column: string) => (
        <SortableHeader
            label={label}
            column={column}
            currentSort={payoutSort}
            currentDirection={payoutDirection}
            onSort={(next) =>
                visitPayments({
                    payout_sort: next,
                    payout_direction: nextSortDirection(
                        payoutSort,
                        next,
                        payoutDirection,
                    ),
                    payouts_page: 1,
                })
            }
            sortAscLabel={common.sort_asc}
            sortDescLabel={common.sort_desc}
        />
    );

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            bank_account_iban: bank_details.bank_account_iban ?? '',
            bank_account_name: bank_details.bank_account_name ?? '',
            payout_method: bank_details.payout_method ?? '',
        });

    const saveBank = (event: FormEvent) => {
        event.preventDefault();
        put(route('seller.payments.bank-details'));
    };

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label={t.pending_payout}
                    value={formatMoney(summaries.pending_payout)}
                    tone="warning"
                />
                <KpiCard
                    label={t.paid_out}
                    value={formatMoney(summaries.paid_out)}
                    tone="success"
                />
                <KpiCard
                    label={t.commission_due}
                    value={formatMoney(summaries.commission_due)}
                    tone="info"
                />
                <KpiCard
                    label={t.commission_paid}
                    value={formatMoney(summaries.commission_paid)}
                    tone="default"
                />
            </div>

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.bank_details}
                </h2>
                <form
                    onSubmit={saveBank}
                    className="grid grid-cols-1 gap-4 sm:grid-cols-2"
                >
                    <FormInput
                        label={t.iban}
                        name="bank_account_iban"
                        mono
                        value={data.bank_account_iban}
                        error={errors.bank_account_iban}
                        onChange={(e) =>
                            setData('bank_account_iban', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.account_name}
                        name="bank_account_name"
                        value={data.bank_account_name}
                        error={errors.bank_account_name}
                        onChange={(e) =>
                            setData('bank_account_name', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.payout_method}
                        name="payout_method"
                        value={data.payout_method}
                        error={errors.payout_method}
                        onChange={(e) =>
                            setData('payout_method', e.target.value)
                        }
                    />
                    <div className="flex items-end gap-3">
                        <Button type="submit" disabled={processing}>
                            {t.save_bank}
                        </Button>
                        {recentlySuccessful && (
                            <p className="text-sm text-rml-primary">
                                {t.bank_saved}
                            </p>
                        )}
                    </div>
                </form>
            </section>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.commissions}
                </h2>
                {commissions.data.length === 0 ? (
                    <EmptyState title={t.empty} />
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.empty}
                        items={commissions.data.map((row) => ({
                            id: String(row.id),
                            title: (
                                <span className="font-mono text-sm">
                                    {row.commission_reference}
                                </span>
                            ),
                            subtitle: row.lead_reference ?? undefined,
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
                                        {formatMoney(row.commission_amount)}
                                    </p>
                                    {row.seller_name && <p>{row.seller_name}</p>}
                                </div>
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={commissions.data}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.empty}
                        columns={[
                            {
                                id: 'reference',
                                header: commissionHeader(t.reference, 'reference'),
                                cell: (row) => (
                                    <span className="font-mono text-sm">
                                        {row.commission_reference}
                                    </span>
                                ),
                            },
                            {
                                id: 'lead',
                                header: common.lead_id,
                                cell: (row) => row.lead_reference ?? '—',
                            },
                            {
                                id: 'seller',
                                header: translations.seller.staff.staff_member,
                                cell: (row) => row.seller_name ?? '—',
                            },
                            {
                                id: 'amount',
                                header: commissionHeader(t.amount, 'amount'),
                                cell: (row) =>
                                    formatMoney(row.commission_amount),
                            },
                            {
                                id: 'status',
                                header: commissionHeader(common.status, 'status'),
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
                                header: commissionHeader(t.due_date, 'date'),
                                cell: (row) =>
                                    row.due_at
                                        ? new Date(
                                              row.due_at,
                                          ).toLocaleDateString(app.locale)
                                        : '—',
                            },
                            {
                                id: 'paid',
                                header: t.paid_at,
                                cell: (row) =>
                                    row.paid_at
                                        ? new Date(
                                              row.paid_at,
                                          ).toLocaleDateString(app.locale)
                                        : '—',
                            },
                        ]}
                    />
                )}
                <Pagination
                    page={commissionMeta.page}
                    pageCount={commissionMeta.pageCount}
                    perPage={commissionPerPage}
                    onPerPageChange={(next) =>
                        visitPayments({
                            commission_per_page: next,
                            commissions_page: 1,
                        })
                    }
                    onPageChange={(next) =>
                        visitPayments({ commissions_page: next })
                    }
                    labels={paginationLabels(common)}
                />
            </section>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.history}
                </h2>
                {payouts.data.length === 0 ? (
                    <EmptyState title={t.empty} />
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.empty}
                        items={payouts.data.map((row) => ({
                            id: String(row.id),
                            title: (
                                <span className="font-mono text-sm">
                                    {row.payout_reference}
                                </span>
                            ),
                            subtitle: formatMoney(
                                row.amount,
                                row.currency ?? 'EUR',
                            ),
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
                                        {t.lead_reference}:{' '}
                                        {row.lead_reference ?? '—'}
                                    </p>
                                    <p>
                                        {t.due_date}:{' '}
                                        {row.due_date
                                            ? new Date(
                                                  row.due_date,
                                              ).toLocaleDateString(app.locale)
                                            : '—'}
                                    </p>
                                    {row.notes && <p>{row.notes}</p>}
                                </div>
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={payouts.data}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.empty}
                        columns={[
                            {
                                id: 'reference',
                                header: payoutHeader(t.reference, 'reference'),
                                cell: (row) => (
                                    <span className="font-mono text-sm">
                                        {row.payout_reference}
                                    </span>
                                ),
                            },
                            {
                                id: 'lead',
                                header: t.lead_reference,
                                cell: (row) => (
                                    <span className="font-mono text-sm">
                                        {row.lead_reference ?? '—'}
                                    </span>
                                ),
                            },
                            {
                                id: 'amount',
                                header: payoutHeader(t.amount, 'amount'),
                                cell: (row) =>
                                    formatMoney(
                                        row.amount,
                                        row.currency ?? 'EUR',
                                    ),
                            },
                            {
                                id: 'status',
                                header: payoutHeader(common.status, 'status'),
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
                                header: payoutHeader(t.due_date, 'date'),
                                cell: (row) =>
                                    row.due_date
                                        ? new Date(
                                              row.due_date,
                                          ).toLocaleDateString(app.locale)
                                        : '—',
                            },
                            {
                                id: 'paid',
                                header: t.paid_at,
                                cell: (row) =>
                                    row.paid_at
                                        ? new Date(
                                              row.paid_at,
                                          ).toLocaleDateString(app.locale)
                                        : '—',
                            },
                            {
                                id: 'statement',
                                header: common.actions,
                                cell: (row) =>
                                    row.statement_id ? (
                                        <a
                                            href={route(
                                                'invoices.download',
                                                row.statement_id,
                                            )}
                                        >
                                            <Button size="sm" variant="outline">
                                                {t.download_statement}
                                            </Button>
                                        </a>
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
                    perPage={payoutPerPage}
                    onPerPageChange={(next) =>
                        visitPayments({
                            payout_per_page: next,
                            payouts_page: 1,
                        })
                    }
                    onPageChange={(next) =>
                        visitPayments({ payouts_page: next })
                    }
                    labels={paginationLabels(common)}
                />
            </section>
        </AppLayout>
    );
}
