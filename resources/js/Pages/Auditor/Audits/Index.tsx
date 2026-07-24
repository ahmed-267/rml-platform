import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    MobileCardList,
    Pagination,
    Select,
    SortableHeader,
    StatusBadge,
} from '@/Components/ui';
import type { SortDirection } from '@/Components/ui/SortableHeader';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusTone } from '@/lib/lead-status';
import {
    nextSortDirection,
    paginationLabels,
    paginationMeta,
    resolveSortDirection,
    type Paginator,
} from '@/lib/list-helpers';
import type { PageProps } from '@/types';

interface AuditRow {
    id: number;
    lead_reference: string;
    scheme: string | null;
    zone: string | null;
    size_m2: number | null;
    seller_name: string | null;
    submitted_at: string | null;
    audit_status: string | null;
    evidence_status: string | null;
}

export default function AuditLeadsIndex({
    audits,
    filters,
    filterOptions,
}: {
    audits: Paginator<AuditRow>;
    filters: {
        status?: string | null;
        scheme_id?: string | number | null;
        zone_id?: string | number | null;
        evidence?: string | null;
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
    const { translations } = usePage<PageProps>().props;
    const t = translations.auditor;
    const pageCopy = t.leads;
    const common = t.common;
    const auditStatuses = translations.audit_statuses ?? {};
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );
    const [zoneId, setZoneId] = useState(
        filters.zone_id != null ? String(filters.zone_id) : '',
    );
    const [evidence, setEvidence] = useState(filters.evidence ?? '');

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(audits);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        search: search || undefined,
        status: status || undefined,
        scheme_id: schemeId || undefined,
        zone_id: zoneId || undefined,
        evidence: evidence || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(route('auditor.audits.index'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setSchemeId('');
        setZoneId('');
        setEvidence('');
        router.get(
            route('auditor.audits.index'),
            { sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('auditor.audits.index'),
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

    const statusLabel = (value: string | null) =>
        value ? (auditStatuses[value] ?? value) : '—';

    return (
        <AppLayout
            title={pageCopy.index_title}
            subtitle={pageCopy.index_subtitle}
        >
            <Head title={pageCopy.index_title} />

            <FilterBar
                compact
                search={search}
                onSearchChange={setSearch}
                searchLabel={common.search}
                searchPlaceholder={pageCopy.search_placeholder}
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
                        label={common.status}
                        aria-label={common.status}
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        options={[
                            { value: '', label: common.all },
                            ...filterOptions.statuses.map((s) => ({
                                value: s,
                                label: statusLabel(s),
                            })),
                        ]}
                    />
                </div>
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={common.scheme}
                        aria-label={common.scheme}
                        value={schemeId}
                        onChange={(e) => setSchemeId(e.target.value)}
                        options={[
                            { value: '', label: common.all },
                            ...filterOptions.schemes.map((s) => ({
                                value: String(s.id),
                                label: s.name,
                            })),
                        ]}
                    />
                </div>
                <div className="w-full sm:w-[10rem]">
                    <Select
                        label={common.zone}
                        aria-label={common.zone}
                        value={zoneId}
                        onChange={(e) => setZoneId(e.target.value)}
                        options={[
                            { value: '', label: common.all },
                            ...filterOptions.zones.map((z) => ({
                                value: String(z.id),
                                label: z.code,
                            })),
                        ]}
                    />
                </div>
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={pageCopy.evidence_status}
                        aria-label={pageCopy.evidence_status}
                        value={evidence}
                        onChange={(e) => setEvidence(e.target.value)}
                        options={[
                            { value: '', label: common.all },
                            {
                                value: 'available',
                                label: pageCopy.evidence_available,
                            },
                            {
                                value: 'missing',
                                label: pageCopy.evidence_missing,
                            },
                        ]}
                    />
                </div>
            </FilterBar>

            {audits.data.length === 0 ? (
                <EmptyState title={common.empty} description={pageCopy.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={audits.data.map((row) => ({
                        id: String(row.id),
                        title: (
                            <span className="font-mono">{row.lead_reference}</span>
                        ),
                        subtitle: [row.scheme, row.zone, row.seller_name]
                            .filter(Boolean)
                            .join(' · '),
                        meta: (
                            <StatusBadge
                                label={statusLabel(row.audit_status)}
                                tone={leadStatusTone(row.audit_status)}
                            />
                        ),
                        body: (
                            <p className="text-rml-muted">
                                {pageCopy.evidence_status}:{' '}
                                {row.evidence_status === 'missing'
                                    ? pageCopy.evidence_missing
                                    : pageCopy.evidence_available}
                            </p>
                        ),
                        actions: (
                            <Link
                                href={route('auditor.audits.show', row.id)}
                                className="text-sm font-semibold text-rml-primary"
                            >
                                {common.open_audit}
                            </Link>
                        ),
                    }))}
                />
            ) : (
                <DataTable
                    data={audits.data}
                    getRowId={(r) => String(r.id)}
                    emptyMessage={common.empty}
                    columns={[
                        {
                            id: 'ref',
                            header: sortableHeader(
                                common.lead_id,
                                'lead_reference',
                            ),
                            cell: (r) => (
                                <span className="font-mono text-sm font-semibold">
                                    {r.lead_reference}
                                </span>
                            ),
                        },
                        {
                            id: 'scheme',
                            header: sortableHeader(common.scheme, 'scheme'),
                            cell: (r) => r.scheme ?? '—',
                        },
                        {
                            id: 'zone',
                            header: sortableHeader(common.zone, 'zone'),
                            cell: (r) => r.zone ?? '—',
                        },
                        {
                            id: 'size',
                            header: common.size,
                            cell: (r) =>
                                r.size_m2 != null ? `${r.size_m2} m²` : '—',
                        },
                        {
                            id: 'seller',
                            header: common.seller,
                            cell: (r) => r.seller_name ?? '—',
                        },
                        {
                            id: 'submitted',
                            header: sortableHeader(common.submitted, 'date'),
                            cell: (r) => r.submitted_at ?? '—',
                        },
                        {
                            id: 'audit',
                            header: sortableHeader(
                                pageCopy.audit_status,
                                'status',
                            ),
                            cell: (r) => (
                                <StatusBadge
                                    label={statusLabel(r.audit_status)}
                                    tone={leadStatusTone(r.audit_status)}
                                />
                            ),
                        },
                        {
                            id: 'evidence',
                            header: pageCopy.evidence_status,
                            cell: (r) =>
                                r.evidence_status === 'missing'
                                    ? pageCopy.evidence_missing
                                    : pageCopy.evidence_available,
                        },
                        {
                            id: 'action',
                            header: common.actions,
                            cell: (r) => (
                                <Link
                                    href={route('auditor.audits.show', r.id)}
                                    className="text-sm font-semibold text-rml-primary hover:underline"
                                >
                                    {common.open_audit}
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
                        route('auditor.audits.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('auditor.audits.index'),
                        queryParams({ page: next }),
                        { preserveState: true },
                    )
                }
                labels={paginationLabels(common)}
            />
        </AppLayout>
    );
}
