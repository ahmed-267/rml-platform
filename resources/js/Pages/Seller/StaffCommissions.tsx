import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    DataTable,
    EmptyState,
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

interface StaffMember {
    user_id: number;
    name: string | null;
    email: string | null;
    seller_type: string | null;
    commission_rate: number | null;
}

interface CommissionRow {
    id: number;
    commission_reference: string | null;
    lead_reference: string | null;
    seller_name: string | null;
    seller_user_id: number | null;
    percentage: number | null;
    commission_amount: number | null;
    status: string | null;
    due_at: string | null;
    paid_at: string | null;
}

function formatMoney(value: number | null | undefined): string {
    if (value == null) {
        return '—';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 2,
    }).format(value);
}

export default function StaffCommissions({
    staff,
    commissions,
    filters,
}: {
    staff: StaffMember[];
    commissions: Paginator<CommissionRow>;
    filters: {
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.seller.staff;
    const payments = translations.seller.payments;
    const common = translations.seller.common;
    const statusLabels = {
        ...translations.statuses,
        ...translations.lead_statuses,
    };
    const isMobile = useIsMobile();

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(commissions);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const handleSort = (column: string) => {
        router.get(
            route('seller.staff-commissions'),
            queryParams({
                sort: column,
                direction: nextSortDirection(
                    currentSort,
                    column,
                    currentDirection,
                ),
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

    return (
        <AppLayout title={t.commissions_title} subtitle={t.commissions_subtitle}>
            <Head title={t.commissions_title} />

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.staff_member}
                </h2>
                {staff.length === 0 ? (
                    <EmptyState title={t.empty_commissions} />
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.empty_commissions}
                        items={staff.map((member) => ({
                            id: String(member.user_id),
                            title: member.name ?? member.email ?? '—',
                            subtitle: member.email ?? undefined,
                            body: (
                                <p className="text-rml-muted">
                                    {t.commission_rate}:{' '}
                                    {member.commission_rate != null
                                        ? `${member.commission_rate}%`
                                        : '—'}
                                </p>
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={staff}
                        getRowId={(row) => String(row.user_id)}
                        emptyMessage={t.empty_commissions}
                        columns={[
                            {
                                id: 'name',
                                header: t.staff_member,
                                cell: (row) => row.name ?? '—',
                            },
                            {
                                id: 'email',
                                header: translations.seller.profile.email,
                                cell: (row) => row.email ?? '—',
                            },
                            {
                                id: 'rate',
                                header: t.commission_rate,
                                cell: (row) =>
                                    row.commission_rate != null
                                        ? `${row.commission_rate}%`
                                        : '—',
                            },
                        ]}
                    />
                )}
            </section>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {payments.commissions}
                </h2>
                {commissions.data.length === 0 ? (
                    <EmptyState title={t.empty_commissions} />
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.empty_commissions}
                        items={commissions.data.map((row) => ({
                            id: String(row.id),
                            title: (
                                <span className="font-mono text-sm">
                                    {row.commission_reference}
                                </span>
                            ),
                            subtitle: row.seller_name ?? undefined,
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
                                        {common.lead_id}:{' '}
                                        {row.lead_reference ?? '—'}
                                    </p>
                                    <p>
                                        {payments.amount}:{' '}
                                        {formatMoney(row.commission_amount)}
                                    </p>
                                </div>
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={commissions.data}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.empty_commissions}
                        columns={[
                            {
                                id: 'reference',
                                header: sortableHeader(
                                    payments.reference,
                                    'reference',
                                ),
                                cell: (row) => (
                                    <span className="font-mono text-sm">
                                        {row.commission_reference}
                                    </span>
                                ),
                            },
                            {
                                id: 'seller',
                                header: t.staff_member,
                                cell: (row) => row.seller_name ?? '—',
                            },
                            {
                                id: 'lead',
                                header: common.lead_id,
                                cell: (row) => row.lead_reference ?? '—',
                            },
                            {
                                id: 'amount',
                                header: sortableHeader(
                                    payments.amount,
                                    'amount',
                                ),
                                cell: (row) =>
                                    formatMoney(row.commission_amount),
                            },
                            {
                                id: 'status',
                                header: sortableHeader(common.status, 'status'),
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
                                header: sortableHeader(payments.due_date, 'date'),
                                cell: (row) =>
                                    row.due_at
                                        ? new Date(
                                              row.due_at,
                                          ).toLocaleDateString(app.locale)
                                        : '—',
                            },
                            {
                                id: 'paid',
                                header: payments.paid_at,
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
                    page={page}
                    pageCount={pageCount}
                    perPage={currentPerPage}
                    onPerPageChange={(next) =>
                        router.get(
                            route('seller.staff-commissions'),
                            queryParams({ per_page: next, page: 1 }),
                            { preserveState: true, replace: true },
                        )
                    }
                    onPageChange={(next) =>
                        router.get(
                            route('seller.staff-commissions'),
                            queryParams({ page: next }),
                            { preserveState: true },
                        )
                    }
                    labels={paginationLabels(common)}
                />
            </section>
        </AppLayout>
    );
}
