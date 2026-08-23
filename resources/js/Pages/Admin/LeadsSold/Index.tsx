import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
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
    Pagination,
    Select,
    SortableHeader,
    StatusBadge,
    TableActionLink,
    TableActions,
    tableActionIcons,
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
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';

interface SoldLeadRow {
    id: number;
    lead_reference: string;
    status: string | null;
    scheme: string | null;
    zone: string | null;
    selling_price: number | null;
    buying_price: number | null;
    expected_margin: number | null;
    seller_name: string | null;
    seller_company: string | null;
    buyer_company: string | null;
    buyer_company_id?: number | null;
    buyer_user_id?: number | null;
    buyer_name: string | null;
    payment_id?: number | null;
    payment_reference?: string | null;
    payment_status: string | null;
    release_status?: string | null;
    margin: number | null;
    sold_at: string | null;
}

export default function LeadsSoldIndex({
    leads,
    filters,
    filterOptions,
    summary,
    embedded = false,
}: {
    leads: Paginator<SoldLeadRow>;
    filters: {
        scheme_id?: string | number | null;
        installer_id?: string | number | null;
        payment_status?: string | null;
        release_status?: string | null;
        sold_from?: string | null;
        sold_to?: string | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        schemes: Array<{ id: number; name: string }>;
        installers?: Array<{ id: number; name: string }>;
        payment_statuses?: string[];
        release_statuses?: string[];
    };
    summary: {
        total_sold: number;
        total_revenue: number;
        total_margin: number;
    };
    embedded?: boolean;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.leads_sold;
    const lb = translations.admin.leads_bought;
    const leadsT = translations.admin.leads;
    const common = translations.admin.common;
    const leadStatuses = translations.lead_statuses;
    const paymentStatuses = translations.payment_statuses;
    const isMobile = useIsMobile();

    const [filtersOpen, setFiltersOpen] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );
    const [installerId, setInstallerId] = useState(
        filters.installer_id != null ? String(filters.installer_id) : '',
    );
    const [paymentStatus, setPaymentStatus] = useState(
        filters.payment_status ?? '',
    );
    const [releaseStatus, setReleaseStatus] = useState(
        filters.release_status ?? '',
    );
    const [soldFrom, setSoldFrom] = useState(filters.sold_from ?? '');
    const [soldTo, setSoldTo] = useState(filters.sold_to ?? '');

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection =
        filters.direction === 'asc' ? 'asc' : 'desc';
    const { page, pageCount, perPage } = paginationMeta(leads);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const listHref = route('admin.leads.index');
    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        tab: 'sold',
        search: search || undefined,
        scheme_id: schemeId || undefined,
        installer_id: installerId || undefined,
        payment_status: paymentStatus || undefined,
        release_status: releaseStatus || undefined,
        sold_from: soldFrom || undefined,
        sold_to: soldTo || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(listHref, queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    };

    useInstantListFilters(applyFilters, search, [
        schemeId,
        installerId,
        paymentStatus,
        releaseStatus,
        soldFrom,
        soldTo,
    ]);

    const resetFilters = () => {
        setSearch('');
        setSchemeId('');
        setInstallerId('');
        setPaymentStatus('');
        setReleaseStatus('');
        setSoldFrom('');
        setSoldTo('');
        router.get(
            listHref,
            { tab: 'sold', per_page: currentPerPage },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        const nextDirection: SortDirection =
            currentSort === column && currentDirection === 'asc'
                ? 'desc'
                : 'asc';

        router.get(
            listHref,
            queryParams({
                sort: column,
                direction: nextDirection,
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

    const releaseLabel = (value: string | null | undefined) => {
        if (value === 'released') {
            return t.release_released ?? 'Released';
        }
        if (value === 'pending_release') {
            return t.release_pending ?? 'Pending release';
        }
        return value ?? '—';
    };

    const soldActions = (row: SoldLeadRow) => (
        <TableActions>
            <TableActionLink
                href={route('admin.leads-sold.show', row.id)}
                label={common.view}
                icon={tableActionIcons.view}
            />
            {row.buyer_user_id != null && (
                <TableActionLink
                    href={route('admin.buyers.show', row.buyer_user_id)}
                    label={t.view_buyer ?? 'View buyer'}
                    icon={tableActionIcons.buy}
                />
            )}
            {row.payment_reference && (
                <TableActionLink
                    href={route('admin.payments.index', {
                        tab: 'buyer',
                        search: row.payment_reference,
                    })}
                    label={t.view_payment ?? 'View payment'}
                    icon={tableActionIcons.pay}
                />
            )}
            {row.payment_status === 'paid' && row.payment_reference && (
                <TableActionLink
                    href={route('admin.payments.index', {
                        tab: 'buyer',
                        search: row.payment_reference,
                    })}
                    label={t.download_invoice ?? 'Download invoice'}
                    icon={tableActionIcons.download}
                />
            )}
        </TableActions>
    );

    const filterControls = (
        <>
            <div className="w-full sm:w-[14rem]">
                <Select
                    label={t.filter_installer ?? t.buyer}
                    value={installerId}
                    onChange={(e) => setInstallerId(e.target.value)}
                    options={[
                        {
                            label: t.all_installers ?? common.all,
                            value: '',
                        },
                        ...(filterOptions.installers ?? []).map((item) => ({
                            label: item.name,
                            value: String(item.id),
                        })),
                    ]}
                />
            </div>
            <div className="w-full sm:w-[14rem]">
                <Select
                    label={common.scheme}
                    value={schemeId}
                    onChange={(e) => setSchemeId(e.target.value)}
                    options={[
                        {
                            label:
                                t.all_schemes ??
                                lb.all_schemes ??
                                common.all,
                            value: '',
                        },
                        ...filterOptions.schemes.map((s) => ({
                            label: s.name,
                            value: String(s.id),
                        })),
                    ]}
                />
            </div>
            <div className="w-full sm:w-[12.5rem]">
                <Select
                    label={t.payment_status ?? 'Payment status'}
                    value={paymentStatus}
                    onChange={(e) => setPaymentStatus(e.target.value)}
                    options={[
                        {
                            label: common.all_payment_statuses ?? common.all,
                            value: '',
                        },
                        ...(filterOptions.payment_statuses ?? []).map((s) => ({
                            label: paymentStatuses[s] ?? s,
                            value: s,
                        })),
                    ]}
                />
            </div>
            <div className="w-full sm:w-[12.5rem]">
                <Select
                    label={t.release_status ?? 'Release status'}
                    value={releaseStatus}
                    onChange={(e) => setReleaseStatus(e.target.value)}
                    options={[
                        {
                            label: common.all_release_statuses ?? common.all,
                            value: '',
                        },
                        ...(filterOptions.release_statuses ?? []).map((s) => ({
                            label: releaseLabel(s),
                            value: s,
                        })),
                    ]}
                />
            </div>
            <div className="w-full sm:w-[11rem]">
                <FormInput
                    type="date"
                    label={t.filter_sold_from ?? t.sold_date ?? 'Sold from'}
                    value={soldFrom}
                    onChange={(e) => setSoldFrom(e.target.value)}
                />
            </div>
            <div className="w-full sm:w-[11rem]">
                <FormInput
                    type="date"
                    label={t.filter_sold_to ?? 'Sold to'}
                    value={soldTo}
                    onChange={(e) => setSoldTo(e.target.value)}
                />
            </div>
        </>
    );

    const body = (
        <div className="min-w-0 space-y-4 overflow-x-hidden">
            {!embedded && <Head title={t.index_title} />}

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <KpiCard
                    label={t.index_title}
                    value={summary.total_sold}
                    tone="info"
                />
                <KpiCard
                    label={t.total_revenue}
                    value={formatMoney(summary.total_revenue)}
                    tone="success"
                />
                <KpiCard
                    label={t.total_margin}
                    value={formatMoney(summary.total_margin)}
                    tone="default"
                />
            </div>

            <FilterBar
                compact
                search={search}
                onSearchChange={setSearch}
                searchLabel={common.search}
                searchPlaceholder={t.search_placeholder}
                onOpenMobileFilters={() => setFiltersOpen(true)}
                actions={
                    <Button size="sm" variant="ghost" onClick={resetFilters}>
                        {common.reset}
                    </Button>
                }
                endActions={
                    <div className="flex gap-2">
                        <Button size="sm" variant="primary">
                            {leadsT.view_table ?? 'Table'}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.get(
                                    listHref,
                                    queryParams({
                                        view: 'map',
                                        page: undefined,
                                    }),
                                    {
                                        preserveState: false,
                                        replace: true,
                                    },
                                )
                            }
                        >
                            {leadsT.view_map ?? 'Map'}
                        </Button>
                    </div>
                }
            >
                {!isMobile && filterControls}
            </FilterBar>

            <MobileFilterDrawer
                open={filtersOpen}
                onClose={() => setFiltersOpen(false)}
                onApply={applyFilters}
                onReset={resetFilters}
            >
                {filterControls}
            </MobileFilterDrawer>

            {leads.data.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={leads.data.map((row) => ({
                        id: String(row.id),
                        title: (
                            <span className="font-mono text-sm">
                                {row.lead_reference}
                            </span>
                        ),
                        subtitle: row.buyer_company ?? row.buyer_name ?? '—',
                        meta: (
                            <StatusBadge
                                label={leadStatusLabel('sold', leadStatuses)}
                                tone="success"
                            />
                        ),
                        body: (
                            <div className="space-y-1 text-rml-muted">
                                <p>
                                    {t.margin}: {formatMoney(row.margin)}
                                </p>
                                <p>
                                    {t.payment_status}:{' '}
                                    {row.payment_status
                                        ? (paymentStatuses[row.payment_status] ??
                                          row.payment_status)
                                        : '—'}
                                </p>
                                <p>
                                    {t.release_status}:{' '}
                                    {releaseLabel(row.release_status)}
                                </p>
                            </div>
                        ),
                        actions: soldActions(row),
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
                                <Link
                                    href={route('admin.leads-sold.show', row.id)}
                                    className="font-mono text-sm font-medium text-rml-primary"
                                >
                                    {row.lead_reference}
                                </Link>
                            ),
                        },
                        {
                            id: 'buyer',
                            header: sortableHeader(t.buyer, 'buyer'),
                            cell: (row) =>
                                row.buyer_company ?? row.buyer_name ?? '—',
                        },
                        {
                            id: 'scheme',
                            header: sortableHeader(common.scheme, 'scheme'),
                            cell: (row) => row.scheme ?? '—',
                        },
                        {
                            id: 'selling_price',
                            header: sortableHeader(
                                lb.selling_price,
                                'selling_price',
                            ),
                            cell: (row) => formatMoney(row.selling_price),
                        },
                        {
                            id: 'margin',
                            header: sortableHeader(t.margin, 'margin'),
                            cell: (row) => formatMoney(row.margin),
                        },
                        {
                            id: 'payment_status',
                            header: sortableHeader(
                                t.payment_status ?? common.status,
                                'payment_status',
                            ),
                            cell: (row) =>
                                row.payment_status ? (
                                    <StatusBadge
                                        label={
                                            paymentStatuses[
                                                row.payment_status
                                            ] ?? row.payment_status
                                        }
                                        tone={leadStatusTone(
                                            row.payment_status,
                                        )}
                                    />
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            id: 'release_status',
                            header: t.release_status ?? 'Release',
                            cell: (row) => (
                                <StatusBadge
                                    label={releaseLabel(row.release_status)}
                                    tone={
                                        row.release_status === 'released'
                                            ? 'success'
                                            : 'warning'
                                    }
                                />
                            ),
                        },
                        {
                            id: 'sold_at',
                            header: sortableHeader(
                                t.sold_date ?? common.date,
                                'date',
                            ),
                            cell: (row) => formatDate(row.sold_at, app.locale),
                        },
                        {
                            id: 'actions',
                            header: common.actions,
                            cell: (row) => soldActions(row),
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
                        listHref,
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        listHref,
                        queryParams({ page: next }),
                        { preserveState: true, replace: true },
                    )
                }
                labels={paginationLabels(common)}
            />
        </div>
    );

    if (embedded) {
        return body;
    }

    return (
        <AppLayout title={t.index_title} subtitle={t.index_subtitle}>
            {body}
        </AppLayout>
    );
}
