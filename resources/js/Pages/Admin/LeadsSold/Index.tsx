import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    MobileFilterDrawer,
    KpiCard,
    MobileCardList,
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
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

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
    buyer_name: string | null;
    payment_status: string | null;
    margin: number | null;
    sold_at: string | null;
}

export default function LeadsSoldIndex({
    leads,
    filters,
    filterOptions,
    summary,
}: {
    leads: Paginator<SoldLeadRow>;
    filters: {
        scheme_id?: string | number | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        schemes: Array<{ id: number; name: string }>;
    };
    summary: {
        total_sold: number;
        total_revenue: number;
        total_margin: number;
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.leads_sold;
    const lb = translations.admin.leads_bought;
    const common = translations.admin.common;
    const leadStatuses = translations.lead_statuses;
    const paymentStatuses = translations.payment_statuses;
    const isMobile = useIsMobile();

    const [filtersOpen, setFiltersOpen] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection =
        filters.direction === 'asc' ? 'asc' : 'desc';
    const { page, pageCount, perPage } = paginationMeta(leads);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        search: search || undefined,
        scheme_id: schemeId || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(route('admin.leads-sold.index'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setSchemeId('');
        router.get(
            route('admin.leads-sold.index'),
            { per_page: currentPerPage },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        const nextDirection: SortDirection =
            currentSort === column && currentDirection === 'asc'
                ? 'desc'
                : 'asc';

        router.get(
            route('admin.leads-sold.index'),
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

    return (
        <AppLayout title={t.index_title} subtitle={t.index_subtitle}>
            <Head title={t.index_title} />

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
                <div className="w-full sm:w-[14rem]">
                    <Select
                        label={common.scheme}
                        aria-label={common.scheme}
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
            </FilterBar>

            <MobileFilterDrawer
                open={filtersOpen}
                onClose={() => setFiltersOpen(false)}
                onApply={applyFilters}
                onReset={resetFilters}
            >
                <Select
                    label={common.scheme}
                    aria-label={common.scheme}
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
                            <p className="text-rml-muted">
                                {t.margin}: {formatMoney(row.margin)}
                            </p>
                        ),
                        actions: (
                            <Link
                                href={route('admin.leads-sold.show', row.id)}
                                className="text-sm font-semibold text-rml-primary"
                            >
                                {common.view}
                            </Link>
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
                                <Link
                                    href={route('admin.leads-sold.show', row.id)}
                                    className="font-mono text-sm font-medium text-rml-primary"
                                >
                                    {row.lead_reference}
                                </Link>
                            ),
                        },
                        {
                            id: 'seller',
                            header: sortableHeader(lb.seller, 'seller'),
                            cell: (row) =>
                                row.seller_company ?? row.seller_name ?? '—',
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
                            id: 'zone',
                            header: sortableHeader(common.zone, 'zone'),
                            cell: (row) => row.zone ?? '—',
                        },
                        {
                            id: 'buying_price',
                            header: sortableHeader(
                                lb.buying_price,
                                'buying_price',
                            ),
                            cell: (row) => formatMoney(row.buying_price),
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
                            id: 'sold_at',
                            header: sortableHeader(common.date, 'date'),
                            cell: (row) => formatDate(row.sold_at, app.locale),
                        },
                        {
                            id: 'actions',
                            header: common.actions,
                            cell: (row) => (
                                <Link
                                    href={route('admin.leads-sold.show', row.id)}
                                    className="font-semibold text-rml-primary hover:underline"
                                >
                                    {common.view}
                                </Link>
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
                        route('admin.leads-sold.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('admin.leads-sold.index'),
                        queryParams({ page: next }),
                        { preserveState: true, replace: true },
                    )
                }
                labels={paginationLabels(common)}
            />
        </AppLayout>
    );
}
