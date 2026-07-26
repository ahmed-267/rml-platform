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
    TableActions,
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
    scheme: { id: number; name: string; slug: string } | null;
    zone: { id: number; code: string; name: string } | null;
    submitted_by?: {
        id: number | null;
        name: string | null;
        label: 'me' | 'admin' | 'staff';
    } | null;
    created_at: string | null;
    payout?: SellerPayoutSummary | null;
}

interface Filters {
    status?: string | null;
    scheme_id?: string | number | null;
    zone_code?: string | null;
    zone_id?: string | number | null;
    search?: string | null;
    sort?: string | null;
    direction?: string | null;
    per_page?: number | string | null;
}

interface FilterOptions {
    schemes: Array<{ id: number; name: string }>;
    zones: Array<{
        id: number;
        code: string;
        name: string;
        scheme_id: number;
    }>;
    statuses: string[];
}

function customerName(lead: SellerLeadRow): string {
    return `${lead.customer_first_name} ${lead.customer_last_name}`.trim();
}

export default function SellerLeadsIndex({
    leads,
    filters,
    filterOptions,
}: {
    leads: Paginator<SellerLeadRow>;
    filters: Filters;
    filterOptions: FilterOptions;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.seller.leads;
    const common = translations.seller.common;
    const staffT = translations.seller.staff;
    const payoutLabels = sellerPayoutLabels(translations);
    const leadStatuses = translations.lead_statuses;
    const isMobile = useIsMobile();

    const submittedByLabel = (
        lead: SellerLeadRow,
    ): string | null => {
        if (!lead.submitted_by) {
            return null;
        }
        if (lead.submitted_by.label === 'me') {
            return staffT.submitted_by_me ?? 'Submitted by me';
        }
        if (lead.submitted_by.label === 'admin') {
            return staffT.submitted_by_admin ?? 'Admin';
        }
        return lead.submitted_by.name ?? staffT.staff_member ?? '—';
    };

    const showSubmittedBy = leads.data.some((lead) => lead.submitted_by != null);

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );
    const [zoneCode, setZoneCode] = useState(filters.zone_code ?? '');
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
        scheme_id: schemeId || undefined,
        zone_code: zoneCode || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(route('seller.leads.index'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    }

    useInstantListFilters(applyFilters, search, [status, schemeId, zoneCode]);


    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setSchemeId('');
        setZoneCode('');
        router.get(
            route('seller.leads.index'),
            { sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('seller.leads.index'),
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

    const zoneOptions = Array.from(
        new Map(
            filterOptions.zones.map((zone) => [zone.code, zone]),
        ).values(),
    );

    return (
        <AppLayout
            title={t.index_title}
            subtitle={t.index_subtitle}
            headerActions={
                <Link href={route('seller.leads.create')}>
                    <Button size="sm">
                        {translations.seller.dashboard.submit_cta}
                    </Button>
                </Link>
            }
        >
            <Head title={t.index_title} />

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
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={t.filter_status}
                        aria-label={t.filter_status}
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
                <div className="w-full sm:w-[14rem]">
                    <Select
                        label={t.filter_scheme}
                        aria-label={t.filter_scheme}
                        value={schemeId}
                        onChange={(e) => {
                            setSchemeId(e.target.value);
                        }}
                        options={[
                            { value: '', label: common.all_schemes },
                            ...filterOptions.schemes.map((scheme) => ({
                                value: String(scheme.id),
                                label: scheme.name,
                            })),
                        ]}
                    />
                </div>
                <div className="w-full sm:w-[14rem]">
                    <Select
                        label={t.filter_zone}
                        aria-label={t.filter_zone}
                        value={zoneCode}
                        onChange={(e) => setZoneCode(e.target.value)}
                        options={[
                            { value: '', label: common.all_zones },
                            ...zoneOptions.map((zone) => ({
                                value: zone.code,
                                label: zone.code,
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
                    label={t.filter_status}
                    aria-label={t.filter_status}
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
                <Select
                    label={t.filter_scheme}
                    aria-label={t.filter_scheme}
                    value={schemeId}
                    onChange={(e) => {
                        setSchemeId(e.target.value);
                    }}
                    options={[
                        { value: '', label: common.all_schemes },
                        ...filterOptions.schemes.map((scheme) => ({
                            value: String(scheme.id),
                            label: scheme.name,
                        })),
                    ]}
                />
                <Select
                    label={t.filter_zone}
                    aria-label={t.filter_zone}
                    value={zoneCode}
                    onChange={(e) => setZoneCode(e.target.value)}
                    options={[
                        { value: '', label: common.all_zones },
                        ...zoneOptions.map((zone) => ({
                            value: zone.code,
                            label: zone.code,
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
                                {showSubmittedBy && (
                                    <p>
                                        {staffT.submitted_by ??
                                            staffT.staff_member}
                                        : {submittedByLabel(lead) ?? '—'}
                                    </p>
                                )}
                                <p>
                                    {payoutLabels.column}:{' '}
                                    {sellerPayoutTableLabel(
                                        lead.payout,
                                        payoutLabels,
                                        app.locale,
                                    )}
                                </p>
                                <p>
                                    {common.submitted}:{' '}
                                    {lead.created_at
                                        ? new Date(
                                              lead.created_at,
                                          ).toLocaleDateString(app.locale)
                                        : '—'}
                                </p>
                            </div>
                        ),
                        actions: (
                            <TableActions>
                                <TableActionLink
                                    href={route('seller.leads.show', lead.id)}
                                    label={common.view}
                                    icon={tableActionIcons.view}
                                />
                                {lead.status === 'draft' && (
                                    <TableActionLink
                                        href={route(
                                            'seller.leads.edit',
                                            lead.id,
                                        )}
                                        label={
                                            t.continue_draft ?? t.edit_draft
                                        }
                                        icon={tableActionIcons.edit}
                                    />
                                )}
                            </TableActions>
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
                        ...(showSubmittedBy
                            ? [
                                  {
                                      id: 'submitted_by',
                                      header:
                                          staffT.submitted_by ??
                                          staffT.staff_member,
                                      cell: (row: SellerLeadRow) =>
                                          submittedByLabel(row) ?? '—',
                                  },
                              ]
                            : []),
                        {
                            id: 'scheme',
                            header: sortableHeader(common.scheme, 'scheme'),
                            cell: (row) => row.scheme?.name ?? '—',
                        },
                        {
                            id: 'zone',
                            header: sortableHeader(common.zone, 'zone'),
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
                            id: 'actions',
                            header: common.actions,
                            cell: (row) => (
                                <TableActions>
                                    <TableActionLink
                                        href={route(
                                            'seller.leads.show',
                                            row.id,
                                        )}
                                        label={common.view}
                                        icon={tableActionIcons.view}
                                    />
                                    {row.status === 'draft' && (
                                        <TableActionLink
                                            href={route(
                                                'seller.leads.edit',
                                                row.id,
                                            )}
                                            label={
                                                t.continue_draft ?? t.edit_draft
                                            }
                                            icon={tableActionIcons.edit}
                                        />
                                    )}
                                </TableActions>
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
                        route('seller.leads.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('seller.leads.index'),
                        queryParams({ page: next }),
                        { preserveState: true },
                    )
                }
                labels={paginationLabels(common)}
            />
        </AppLayout>
    );
}
