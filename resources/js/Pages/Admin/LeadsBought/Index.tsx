import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { AuditLeadModal } from '@/Components/admin/AuditLeadModal';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    MobileFilterDrawer,
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

interface LeadRow {
    id: number;
    lead_reference: string;
    status: string | null;
    scheme: string | null;
    zone: string | null;
    buying_price: number | null;
    selling_price: number | null;
    expected_margin: number | null;
    seller_name: string | null;
    seller_company: string | null;
    submitted_at: string | null;
}

export default function LeadsBoughtIndex({
    leads,
    filters,
    filterOptions,
}: {
    leads: Paginator<LeadRow>;
    filters: {
        status?: string | null;
        scheme_id?: string | number | null;
        zone_id?: string | number | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        statuses: string[];
        schemes: Array<{ id: number; name: string }>;
        zones: Array<{ id: number; code: string; name: string }>;
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.leads_bought;
    const common = translations.admin.common;
    const leadStatuses = translations.lead_statuses;
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );
    const [zoneId, setZoneId] = useState(
        filters.zone_id != null ? String(filters.zone_id) : '',
    );
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [auditLeadId, setAuditLeadId] = useState<number | null>(null);
    const [auditOpen, setAuditOpen] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection =
        filters.direction === 'asc' ? 'asc' : 'desc';
    const { page, pageCount, perPage } = paginationMeta(leads);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        search: search || undefined,
        status: status || undefined,
        scheme_id: schemeId || undefined,
        zone_id: zoneId || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(route('admin.leads-bought.index'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setSchemeId('');
        setZoneId('');
        router.get(
            route('admin.leads-bought.index'),
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
            route('admin.leads-bought.index'),
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

    const openAudit = (leadId: number) => {
        setAuditLeadId(leadId);
        setAuditOpen(true);
    };

    const closeAudit = () => {
        setAuditOpen(false);
        setAuditLeadId(null);
        router.reload({ only: ['leads'] });
    };

    return (
        <AppLayout title={t.index_title} subtitle={t.index_subtitle}>
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
                        label={t.filter_status}
                        aria-label={t.filter_status}
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        options={[
                            { label: common.all_statuses, value: '' },
                            ...filterOptions.statuses.map((s) => ({
                                label: leadStatusLabel(s, leadStatuses),
                                value: s,
                            })),
                        ]}
                    />
                </div>
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={t.filter_scheme}
                        aria-label={t.filter_scheme}
                        value={schemeId}
                        onChange={(e) => setSchemeId(e.target.value)}
                        options={[
                            {
                                label: t.all_schemes ?? common.all_statuses,
                                value: '',
                            },
                            ...filterOptions.schemes.map((s) => ({
                                label: s.name,
                                value: String(s.id),
                            })),
                        ]}
                    />
                </div>
                <div className="w-full sm:w-[10rem]">
                    <Select
                        label={t.filter_zone}
                        aria-label={t.filter_zone}
                        value={zoneId}
                        onChange={(e) => setZoneId(e.target.value)}
                        options={[
                            {
                                label: t.all_zones ?? common.all,
                                value: '',
                            },
                            ...filterOptions.zones.map((z) => ({
                                label: z.code,
                                value: String(z.id),
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
                        { label: common.all_statuses, value: '' },
                        ...filterOptions.statuses.map((s) => ({
                            label: leadStatusLabel(s, leadStatuses),
                            value: s,
                        })),
                    ]}
                />
                <Select
                    label={t.filter_scheme}
                    aria-label={t.filter_scheme}
                    value={schemeId}
                    onChange={(e) => setSchemeId(e.target.value)}
                    options={[
                        {
                            label: t.all_schemes ?? common.all_statuses,
                            value: '',
                        },
                        ...filterOptions.schemes.map((s) => ({
                            label: s.name,
                            value: String(s.id),
                        })),
                    ]}
                />
                <Select
                    label={t.filter_zone}
                    aria-label={t.filter_zone}
                    value={zoneId}
                    onChange={(e) => setZoneId(e.target.value)}
                    options={[
                        {
                            label: t.all_zones ?? common.all,
                            value: '',
                        },
                        ...filterOptions.zones.map((z) => ({
                            label: z.code,
                            value: String(z.id),
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
                        subtitle: row.seller_name ?? row.seller_company ?? '—',
                        meta: row.status ? (
                            <StatusBadge
                                label={leadStatusLabel(row.status, leadStatuses)}
                                tone={leadStatusTone(row.status)}
                            />
                        ) : null,
                        body: (
                            <div className="space-y-1 text-rml-muted">
                                <p>
                                    {t.buying_price}:{' '}
                                    {formatMoney(row.buying_price)}
                                </p>
                                <p>
                                    {t.selling_price}:{' '}
                                    {formatMoney(row.selling_price)}
                                </p>
                            </div>
                        ),
                        actions: (
                            <div className="flex gap-3">
                                <Link
                                    href={route(
                                        'admin.leads-bought.show',
                                        row.id,
                                    )}
                                    className="text-sm font-semibold text-rml-primary"
                                >
                                    {common.view}
                                </Link>
                                <button
                                    type="button"
                                    className="text-sm font-semibold text-rml-primary"
                                    onClick={() => openAudit(row.id)}
                                >
                                    {t.audit_lead}
                                </button>
                            </div>
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
                                    href={route(
                                        'admin.leads-bought.show',
                                        row.id,
                                    )}
                                    className="font-mono text-sm font-medium text-rml-primary"
                                >
                                    {row.lead_reference}
                                </Link>
                            ),
                        },
                        {
                            id: 'seller',
                            header: sortableHeader(t.seller, 'seller'),
                            cell: (row) =>
                                row.seller_company ?? row.seller_name ?? '—',
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
                                t.buying_price,
                                'buying_price',
                            ),
                            cell: (row) => formatMoney(row.buying_price),
                        },
                        {
                            id: 'selling_price',
                            header: sortableHeader(
                                t.selling_price,
                                'selling_price',
                            ),
                            cell: (row) => formatMoney(row.selling_price),
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
                            id: 'submitted_at',
                            header: sortableHeader(common.date, 'date'),
                            cell: (row) =>
                                formatDate(row.submitted_at, app.locale),
                        },
                        {
                            id: 'actions',
                            header: common.actions,
                            cell: (row) => (
                                <div className="flex gap-2">
                                    <Link
                                        href={route(
                                            'admin.leads-bought.show',
                                            row.id,
                                        )}
                                        className="font-semibold text-rml-primary hover:underline"
                                    >
                                        {common.view}
                                    </Link>
                                    <button
                                        type="button"
                                        className="font-semibold text-rml-primary hover:underline"
                                        onClick={() => openAudit(row.id)}
                                    >
                                        {t.audit_lead}
                                    </button>
                                </div>
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
                        route('admin.leads-bought.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('admin.leads-bought.index'),
                        queryParams({ page: next }),
                        { preserveState: true, replace: true },
                    )
                }
                labels={paginationLabels(common)}
            />

            {auditLeadId != null && (
                <AuditLeadModal
                    open={auditOpen}
                    onClose={closeAudit}
                    leadId={auditLeadId}
                />
            )}
        </AppLayout>
    );
}
