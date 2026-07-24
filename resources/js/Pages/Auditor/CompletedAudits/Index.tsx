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
    recommendation: string | null;
    final_decision: string | null;
    completed_at: string | null;
}

export default function CompletedAuditsIndex({
    audits,
    filters,
    filterOptions,
}: {
    audits: Paginator<AuditRow>;
    filters: {
        recommendation?: string | null;
        scheme_id?: string | number | null;
        zone_id?: string | number | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        recommendations: string[];
        schemes: Array<{ id: number; name: string }>;
        zones: Array<{ id: number; code: string; name: string }>;
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.auditor;
    const common = t.common;
    const auditStatuses = translations.audit_statuses ?? {};
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [recommendation, setRecommendation] = useState(
        filters.recommendation ?? '',
    );
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );
    const [zoneId, setZoneId] = useState(
        filters.zone_id != null ? String(filters.zone_id) : '',
    );

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
        recommendation: recommendation || undefined,
        scheme_id: schemeId || undefined,
        zone_id: zoneId || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(
            route('auditor.completed-audits.index'),
            queryParams({ page: 1 }),
            { preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        setRecommendation('');
        setSchemeId('');
        setZoneId('');
        router.get(
            route('auditor.completed-audits.index'),
            { sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('auditor.completed-audits.index'),
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

    const formatCompletedAt = (value: string | null) => {
        if (!value) {
            return '—';
        }

        return new Date(value).toLocaleString(app.locale);
    };

    return (
        <AppLayout
            title={t.completed.index_title}
            subtitle={t.completed.index_subtitle}
        >
            <Head title={t.completed.index_title} />

            <FilterBar
                compact
                search={search}
                onSearchChange={setSearch}
                searchLabel={common.search}
                searchPlaceholder={t.completed.search_placeholder}
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
                        label={t.completed.recommendation}
                        aria-label={t.completed.recommendation}
                        value={recommendation}
                        onChange={(e) => setRecommendation(e.target.value)}
                        options={[
                            { value: '', label: common.all },
                            ...filterOptions.recommendations.map((s) => ({
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
            </FilterBar>

            {audits.data.length === 0 ? (
                <EmptyState
                    title={common.empty}
                    description={t.completed.empty}
                />
            ) : isMobile ? (
                <MobileCardList
                    items={audits.data.map((row) => ({
                        id: String(row.id),
                        title: (
                            <span className="font-mono">{row.lead_reference}</span>
                        ),
                        subtitle: [row.scheme, row.zone].filter(Boolean).join(' · '),
                        meta: (
                            <StatusBadge
                                label={statusLabel(row.recommendation)}
                                tone={leadStatusTone(row.recommendation)}
                            />
                        ),
                        actions: (
                            <Link
                                href={route('auditor.audits.show', row.id)}
                                className="text-sm font-semibold text-rml-primary"
                            >
                                {common.view}
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
                            id: 'rec',
                            header: sortableHeader(
                                t.completed.recommendation,
                                'status',
                            ),
                            cell: (r) => (
                                <StatusBadge
                                    label={statusLabel(r.recommendation)}
                                    tone={leadStatusTone(r.recommendation)}
                                />
                            ),
                        },
                        {
                            id: 'final',
                            header: t.completed.final_decision,
                            cell: (r) =>
                                r.final_decision
                                    ? statusLabel(r.final_decision)
                                    : t.completed.pending_final,
                        },
                        {
                            id: 'completed',
                            header: sortableHeader(
                                t.completed.completed_at,
                                'date',
                            ),
                            cell: (r) => formatCompletedAt(r.completed_at),
                        },
                        {
                            id: 'action',
                            header: common.actions,
                            cell: (r) => (
                                <Link
                                    href={route('auditor.audits.show', r.id)}
                                    className="text-sm font-semibold text-rml-primary hover:underline"
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
                        route('auditor.completed-audits.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('auditor.completed-audits.index'),
                        queryParams({ page: next }),
                        { preserveState: true },
                    )
                }
                labels={paginationLabels(common)}
            />
        </AppLayout>
    );
}
