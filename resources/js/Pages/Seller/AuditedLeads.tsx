import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    MobileCardList,
    MobileFilterDrawer,
    Pagination,
    Select,
    SortableHeader,
    StatusBadge,
    TableActionLink,
    tableActionIcons,
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
import {
    sellerPayoutLabels,
    sellerPayoutTableLabel,
    type SellerPayoutSummary,
} from '@/lib/seller-payout';
import type { PageProps } from '@/types';
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';

interface SellerLeadRow {
    id: number;
    lead_reference: string;
    status: string | null;
    customer_first_name: string;
    customer_last_name: string;
    scheme: { id: number; name: string } | null;
    zone: { id: number; code: string; name: string } | null;
    rejection_reason?: string | null;
    requested_info?: string | null;
    created_at: string | null;
    payout?: SellerPayoutSummary | null;
}

function customerName(lead: SellerLeadRow): string {
    return `${lead.customer_first_name} ${lead.customer_last_name}`.trim();
}

function auditNote(lead: SellerLeadRow): string | null {
    if (lead.status === 'needs_more_information') {
        return lead.requested_info ?? null;
    }
    if (lead.status === 'rejected') {
        return lead.rejection_reason ?? null;
    }
    return null;
}

export default function AuditedLeads({
    leads,
    filters,
    filterOptions,
}: {
    leads: Paginator<SellerLeadRow>;
    filters: {
        status?: string | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        statuses: string[];
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.seller.leads;
    const common = translations.seller.common;
    const payoutLabels = sellerPayoutLabels(translations);
    const leadStatuses = translations.lead_statuses;
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(leads);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        search: search || undefined,
        status: status || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(route('seller.audited-leads'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    }

    useInstantListFilters(applyFilters, search, [status]);


    const resetFilters = () => {
        setSearch('');
        setStatus('');
        router.get(
            route('seller.audited-leads'),
            { sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('seller.audited-leads'),
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
        <AppLayout title={t.audited_title} subtitle={t.audited_subtitle}>
            <Head title={t.audited_title} />

            <FilterBar
                compact
                search={search}
                onSearchChange={setSearch}
                searchLabel={common.search}
                searchPlaceholder={t.search_placeholder}
                onOpenMobileFilters={() => setFiltersOpen(true)}
                actions={
                    <>
<Button size="sm" variant="ghost" onClick={resetFilters}>
                            {common.reset}
                        </Button>
                    </>
                }
            >
                <div className="w-full sm:w-[14rem]">
                    <Select
                        label={common.status}
                        aria-label={common.status}
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        options={[
                            { value: '', label: common.all_statuses },
                            ...filterOptions.statuses.map((value) => ({
                                value,
                                label: leadStatusLabel(value, leadStatuses),
                            })),
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
                    value={status}
                    onChange={(e) => setStatus(e.target.value)}
                    options={[
                        { value: '', label: common.all_statuses },
                        ...filterOptions.statuses.map((value) => ({
                            value,
                            label: leadStatusLabel(value, leadStatuses),
                        })),
                    ]}
                />
            </MobileFilterDrawer>

            {leads.data.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    emptyMessage={common.empty}
                    items={leads.data.map((lead) => ({
                        id: String(lead.id),
                        title: (
                            <span className="font-mono text-sm">
                                {lead.lead_reference}
                            </span>
                        ),
                        subtitle: customerName(lead),
                        meta: lead.status ? (
                            <StatusBadge
                                label={leadStatusLabel(
                                    lead.status,
                                    leadStatuses,
                                )}
                                tone={leadStatusTone(lead.status)}
                            />
                        ) : null,
                        body: (
                            <div className="space-y-1 text-rml-muted">
                                <p>
                                    {common.scheme}: {lead.scheme?.name ?? '—'}
                                </p>
                                <p>
                                    {common.zone}: {lead.zone?.code ?? '—'}
                                </p>
                                <p>
                                    {payoutLabels.column}:{' '}
                                    {sellerPayoutTableLabel(
                                        lead.payout,
                                        payoutLabels,
                                        app.locale,
                                    )}
                                </p>
                                {auditNote(lead) && <p>{auditNote(lead)}</p>}
                            </div>
                        ),
                        actions: (
                            <TableActionLink href={route('seller.leads.show', lead.id)} label={common.view} icon={tableActionIcons.view} />
                        ),
                    }))}
                />
            ) : (
                <DataTable
                    data={leads.data}
                    getRowId={(row) => String(row.id)}
                    emptyMessage={common.empty}
                    columns={[
                        {
                            id: 'lead_reference',
                            header: sortableHeader(common.lead_id, 'reference'),
                            cell: (row) => (
                                <span className="font-mono text-sm font-medium">
                                    {row.lead_reference}
                                </span>
                            ),
                        },
                        {
                            id: 'customer',
                            header: common.customer,
                            cell: (row) => customerName(row),
                        },
                        {
                            id: 'scheme',
                            header: sortableHeader(common.scheme, 'scheme'),
                            cell: (row) => row.scheme?.name ?? '—',
                        },
                        {
                            id: 'zone',
                            header: common.zone,
                            cell: (row) => row.zone?.code ?? '—',
                        },
                        {
                            id: 'created_at',
                            header: sortableHeader(common.submitted, 'date'),
                            cell: (row) =>
                                row.created_at
                                    ? new Date(
                                          row.created_at,
                                      ).toLocaleDateString(app.locale)
                                    : '—',
                        },
                        {
                            id: 'status',
                            header: sortableHeader(common.status, 'status'),
                            cell: (row) =>
                                row.status ? (
                                    <StatusBadge
                                        label={leadStatusLabel(
                                            row.status,
                                            leadStatuses,
                                        )}
                                        tone={leadStatusTone(row.status)}
                                    />
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            id: 'payout',
                            header: sortableHeader(
                                payoutLabels.column,
                                'payout',
                            ),
                            cell: (row) =>
                                sellerPayoutTableLabel(
                                    row.payout,
                                    payoutLabels,
                                    app.locale,
                                ),
                        },
                        {
                            id: 'extra',
                            header: t.audit_status,
                            cell: (row) => auditNote(row) ?? '—',
                        },
                        {
                            id: 'actions',
                            header: common.actions,
                            cell: (row) => (
                                <TableActionLink href={route('seller.leads.show', row.id)} label={common.view} icon={tableActionIcons.view} />
                            ),
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
                        route('seller.audited-leads'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('seller.audited-leads'),
                        queryParams({ page: next }),
                        { preserveState: true },
                    )
                }
                labels={paginationLabels(common)}
            />
        </AppLayout>
    );
}
