import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    FormInput,
    MobileCardList,
    MobileFilterDrawer,
    Pagination,
    Select,
    SortableHeader,
    StatusBadge,
    TableActionButton,
    TableActionLink,
    TableActions,
    Tooltip,
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
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';
import { useIsMobile } from '@/hooks/use-media-query';
import type { PageProps } from '@/types';

type PackageRow = {
    id: number;
    package_reference: string;
    name: string;
    status: string | null;
    buyer_company: string | null;
    schemes: string[];
    lead_count: number;
    total_size_m2: number;
    estimated_total: number | null;
    created_by: string | null;
    created_at: string | null;
    can_cancel?: boolean;
};

function Truncated({
    text,
    className = '',
    maxWidthClass = 'max-w-[12rem]',
}: {
    text: string;
    className?: string;
    maxWidthClass?: string;
}) {
    if (!text || text === '—') {
        return <span className={className}>—</span>;
    }

    return (
        <Tooltip label={text} side="bottom">
            <span
                className={`block truncate ${maxWidthClass} ${className}`.trim()}
            >
                {text}
            </span>
        </Tooltip>
    );
}

function compactSchemes(schemes: string[]): { label: string; full: string } {
    if (!schemes.length) {
        return { label: '—', full: '—' };
    }
    if (schemes.length === 1) {
        return { label: schemes[0], full: schemes[0] };
    }
    return {
        label: `${schemes[0]} + ${schemes.length - 1} more`,
        full: schemes.join(', '),
    };
}

export default function AdminPackagesIndex({
    packages,
    filters,
    filterOptions,
    can_manage = false,
    can_create = false,
    can_sell = false,
    embedded = false,
}: {
    packages: Paginator<PackageRow>;
    filters: {
        status?: string | null;
        scheme_id?: string | number | null;
        installer_id?: string | number | null;
        created_from?: string | null;
        created_to?: string | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        statuses: string[];
        schemes?: Array<{ id: number; name: string }>;
        installers?: Array<{ id: number; name: string }>;
    };
    can_manage?: boolean;
    can_create?: boolean;
    can_sell?: boolean;
    embedded?: boolean;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = (translations.admin as Record<string, any>).packages ?? {};
    const leadsT = translations.admin.leads;
    const common = translations.admin.common;
    const sold = translations.admin.leads_sold;
    const statusLabels =
        (translations as Record<string, any>).package_statuses ?? {};
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );
    const [installerId, setInstallerId] = useState(
        filters.installer_id != null ? String(filters.installer_id) : '',
    );
    const [createdFrom, setCreatedFrom] = useState(filters.created_from ?? '');
    const [createdTo, setCreatedTo] = useState(filters.created_to ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection =
        filters.direction === 'asc' ? 'asc' : 'desc';
    const { page, pageCount, perPage } = paginationMeta(packages);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);
    const listHref = route('admin.leads.index');

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        tab: 'packages',
        search: search || undefined,
        status: status || undefined,
        scheme_id: schemeId || undefined,
        installer_id: installerId || undefined,
        created_from: createdFrom || undefined,
        created_to: createdTo || undefined,
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
        status,
        schemeId,
        installerId,
        createdFrom,
        createdTo,
    ]);

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setSchemeId('');
        setInstallerId('');
        setCreatedFrom('');
        setCreatedTo('');
        router.get(
            listHref,
            { tab: 'packages', per_page: currentPerPage },
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

    const cancelPackage = (id: number) => {
        if (!confirm(t.cancel_confirm ?? 'Cancel this package?')) {
            return;
        }
        router.post(
            route('admin.packages.cancel', id),
            {},
            { preserveScroll: true },
        );
    };

    const packageActions = (row: PackageRow) => (
        <TableActions>
            <TableActionLink
                href={route('admin.packages.show', row.id)}
                label={common.view}
                icon={tableActionIcons.view}
            />
            {can_sell && row.status === 'available' && (
                <TableActionLink
                    href={route('admin.sales.create', {
                        type: 'package',
                        package_id: row.id,
                        return_to: 'packages',
                    })}
                    label={t.sell_package ?? t.buy_package ?? 'Buy package'}
                    icon={tableActionIcons.buy}
                />
            )}
            {can_manage && row.can_cancel && (
                <TableActionButton
                    label={t.cancel_package ?? 'Cancel'}
                    icon={tableActionIcons.cancel}
                    tone="danger"
                    onClick={() => cancelPackage(row.id)}
                />
            )}
        </TableActions>
    );

    const filterControls = (
        <>
            <div className="w-full sm:w-[11rem]">
                <Select
                    label={common.status}
                    value={status}
                    onChange={(e) => setStatus(e.target.value)}
                    options={[
                        { label: common.all_statuses, value: '' },
                        ...filterOptions.statuses.map((s) => ({
                            label: statusLabels[s] ?? s,
                            value: s,
                        })),
                    ]}
                />
            </div>
            <div className="w-full sm:w-[13rem]">
                <Select
                    label={sold.buyer ?? t.buyer ?? 'Buyer / installer'}
                    value={installerId}
                    onChange={(e) => setInstallerId(e.target.value)}
                    options={[
                        { label: common.all ?? 'All', value: '' },
                        ...(filterOptions.installers ?? []).map((item) => ({
                            label: item.name,
                            value: String(item.id),
                        })),
                    ]}
                />
            </div>
            <div className="w-full sm:w-[11rem]">
                <Select
                    label={common.scheme}
                    value={schemeId}
                    onChange={(e) => setSchemeId(e.target.value)}
                    options={[
                        {
                            label: common.all_schemes ?? 'All schemes',
                            value: '',
                        },
                        ...(filterOptions.schemes ?? []).map((scheme) => ({
                            label: scheme.name,
                            value: String(scheme.id),
                        })),
                    ]}
                />
            </div>
            <div className="w-full sm:w-[10.5rem]">
                <FormInput
                    type="date"
                    label={t.created_from ?? 'Created from'}
                    value={createdFrom}
                    onChange={(e) => setCreatedFrom(e.target.value)}
                />
            </div>
            <div className="w-full sm:w-[10.5rem]">
                <FormInput
                    type="date"
                    label={t.created_to ?? 'Created to'}
                    value={createdTo}
                    onChange={(e) => setCreatedTo(e.target.value)}
                />
            </div>
        </>
    );

    const body = (
        <div className="min-w-0 space-y-4">
            {!embedded && <Head title={t.index_title ?? 'Lead packages'} />}

            {(can_create || can_manage) && (
                <div className="flex flex-wrap justify-end gap-2">
                    {can_create && (
                        <Button
                            size="sm"
                            variant="primary"
                            onClick={() =>
                                router.get(route('admin.packages.create'))
                            }
                        >
                            {t.create_package ?? 'Create package'}
                        </Button>
                    )}
                </div>
            )}

            <FilterBar
                compact
                search={search}
                onSearchChange={setSearch}
                searchLabel={common.search}
                searchPlaceholder={
                    t.search_placeholder ?? 'Search packages'
                }
                onOpenMobileFilters={() => setFiltersOpen(true)}
                actions={
                    <Button size="sm" variant="ghost" onClick={resetFilters}>
                        {common.reset}
                    </Button>
                }
            >
                {!isMobile && filterControls}
            </FilterBar>

            <MobileFilterDrawer
                open={filtersOpen}
                onClose={() => setFiltersOpen(false)}
                title={common.filters}
                onApply={() => {
                    setFiltersOpen(false);
                    applyFilters();
                }}
                onReset={resetFilters}
            >
                {filterControls}
            </MobileFilterDrawer>

            {packages.data.length === 0 ? (
                <EmptyState
                    title={t.empty_title ?? common.empty}
                    description={
                        t.empty_description ??
                        'No packages match the current filters.'
                    }
                />
            ) : isMobile ? (
                <MobileCardList
                    items={packages.data.map((row) => ({
                        id: String(row.id),
                        title: (
                            <span className="font-mono text-sm">
                                {row.package_reference}
                            </span>
                        ),
                        subtitle: row.name,
                        meta: row.status ? (
                            <StatusBadge
                                label={statusLabels[row.status] ?? row.status}
                                tone="neutral"
                            />
                        ) : null,
                        body: (
                            <div className="space-y-1 text-rml-muted">
                                <p>{row.buyer_company ?? '—'}</p>
                                <p>
                                    {row.lead_count} leads · {row.total_size_m2}{' '}
                                    m²
                                </p>
                                <p>{formatMoney(row.estimated_total)}</p>
                            </div>
                        ),
                        actions: packageActions(row),
                    }))}
                />
            ) : (
                <DataTable
                    dense
                    data={packages.data}
                    getRowId={(row) => String(row.id)}
                    emptyMessage={common.empty}
                    className="min-w-0"
                    tableClassName="min-w-[68rem]"
                    columns={[
                        {
                            id: 'reference',
                            header: sortableHeader(
                                t.reference ?? 'Reference',
                                'reference',
                            ),
                            className: 'min-w-[8rem]',
                            cell: (row) => (
                                <Link
                                    href={route(
                                        'admin.packages.show',
                                        row.id,
                                    )}
                                    className="font-mono text-xs font-medium text-rml-primary"
                                >
                                    {row.package_reference}
                                </Link>
                            ),
                        },
                        {
                            id: 'name',
                            header: sortableHeader(t.name ?? 'Name', 'name'),
                            className: 'min-w-[10rem]',
                            cell: (row) => (
                                <Truncated
                                    text={row.name}
                                    maxWidthClass="max-w-[14rem]"
                                />
                            ),
                        },
                        {
                            id: 'buyer',
                            header: sortableHeader(
                                t.buyer ?? 'Installer',
                                'buyer',
                            ),
                            className: 'min-w-[9rem]',
                            cell: (row) => (
                                <Truncated
                                    text={row.buyer_company ?? '—'}
                                    maxWidthClass="max-w-[11rem]"
                                />
                            ),
                        },
                        {
                            id: 'leads',
                            header: sortableHeader(
                                t.lead_count ?? 'Leads',
                                'leads',
                            ),
                            className: 'min-w-[4rem] text-center',
                            cell: (row) => (
                                <span className="tabular-nums">
                                    {row.lead_count}
                                </span>
                            ),
                        },
                        {
                            id: 'schemes',
                            header: sortableHeader(
                                t.schemes ??
                                    leadsT.map_schemes_included ??
                                    'Schemes',
                                'schemes',
                            ),
                            className: 'min-w-[8rem]',
                            cell: (row) => {
                                const schemes = compactSchemes(
                                    row.schemes ?? [],
                                );
                                return (
                                    <Tooltip label={schemes.full} side="bottom">
                                        <span className="block max-w-[10rem] truncate">
                                            {schemes.label}
                                        </span>
                                    </Tooltip>
                                );
                            },
                        },
                        {
                            id: 'size',
                            header: sortableHeader(
                                t.total_size ?? 'Size',
                                'size',
                            ),
                            className: 'min-w-[5.5rem]',
                            cell: (row) => (
                                <span className="whitespace-nowrap tabular-nums text-xs">
                                    {row.total_size_m2} m²
                                </span>
                            ),
                        },
                        {
                            id: 'total',
                            header: sortableHeader(
                                t.total_price ?? 'Total',
                                'price',
                            ),
                            className: 'min-w-[6.5rem]',
                            cell: (row) => (
                                <span className="whitespace-nowrap tabular-nums text-xs font-medium">
                                    {formatMoney(row.estimated_total)}
                                </span>
                            ),
                        },
                        {
                            id: 'status',
                            header: sortableHeader(common.status, 'status'),
                            className: 'min-w-[6.5rem]',
                            cell: (row) =>
                                row.status ? (
                                    <StatusBadge
                                        label={
                                            statusLabels[row.status] ??
                                            row.status
                                        }
                                        tone="neutral"
                                    />
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            id: 'created',
                            header: sortableHeader(common.date, 'date'),
                            className: 'min-w-[6rem]',
                            cell: (row) => (
                                <span className="whitespace-nowrap text-xs text-rml-muted">
                                    {formatDate(row.created_at, app.locale)}
                                </span>
                            ),
                        },
                        {
                            id: 'actions',
                            header: common.actions,
                            className: 'min-w-[6rem] sticky right-0 bg-white',
                            cell: (row) => packageActions(row),
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
        <AppLayout
            title={t.index_title ?? 'Lead packages'}
            subtitle={t.index_subtitle ?? ''}
        >
            {body}
        </AppLayout>
    );
}
