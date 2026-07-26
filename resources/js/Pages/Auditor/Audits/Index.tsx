import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
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
    TableActionLink,
    Tabs,
    tableActionIcons,
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
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';

type AuditTab = 'my-audits' | 'audit-queue' | 'completed';

interface ActiveAuditRow {
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

interface CompletedAuditRow {
    id: number;
    lead_reference: string;
    scheme: string | null;
    zone: string | null;
    size_m2: number | null;
    recommendation: string | null;
    final_decision: string | null;
    completed_at: string | null;
}

export default function AuditorAuditsIndex({
    tab,
    tabCounts,
    audits,
    filters,
    filterOptions,
}: {
    tab: AuditTab;
    tabCounts: Record<AuditTab, number>;
    audits: Paginator<ActiveAuditRow & Partial<CompletedAuditRow>>;
    filters: {
        tab?: string | null;
        status?: string | null;
        recommendation?: string | null;
        scheme_id?: string | number | null;
        zone_id?: string | number | null;
        evidence?: string | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        statuses?: string[];
        recommendations?: string[];
        schemes: Array<{ id: number; name: string }>;
        zones: Array<{ id: number; code: string; name: string }>;
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.auditor;
    const common = t.common;
    const hub = t.audits_hub ?? {};
    const assignedCopy = t.assigned ?? {};
    const queueCopy = t.leads ?? {};
    const completedCopy = t.completed ?? {};
    const auditStatuses = translations.audit_statuses ?? {};
    const isMobile = useIsMobile();
    const isCompleted = tab === 'completed';

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [recommendation, setRecommendation] = useState(
        filters.recommendation ?? '',
    );
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );
    const [zoneId, setZoneId] = useState(
        filters.zone_id != null ? String(filters.zone_id) : '',
    );
    const [evidence, setEvidence] = useState(filters.evidence ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(audits);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const pageTitle = hub.index_title ?? 'Audits';
    const pageSubtitle =
        tab === 'my-audits'
            ? (hub.my_audits_subtitle ?? assignedCopy.index_subtitle)
            : tab === 'completed'
              ? (hub.completed_subtitle ?? completedCopy.index_subtitle)
              : (hub.queue_subtitle ?? queueCopy.index_subtitle);

    const emptyDescription =
        tab === 'my-audits'
            ? (assignedCopy.empty ?? common.empty)
            : tab === 'completed'
              ? (completedCopy.empty ?? common.empty)
              : (queueCopy.empty ?? common.empty);

    const openAuditLabel =
        common.open_audit ?? common.view_audit ?? 'Open audit';

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        tab,
        search: search || undefined,
        ...(isCompleted
            ? { recommendation: recommendation || undefined }
            : {
                  status: status || undefined,
                  evidence: evidence || undefined,
              }),
        scheme_id: schemeId || undefined,
        zone_id: zoneId || undefined,
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

    useInstantListFilters(
        applyFilters,
        search,
        isCompleted
            ? [recommendation, schemeId, zoneId]
            : [status, schemeId, zoneId, evidence],
    );

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setRecommendation('');
        setSchemeId('');
        setZoneId('');
        setEvidence('');
        router.get(
            route('auditor.audits.index'),
            {
                tab,
                sort: 'date',
                direction: 'desc',
                per_page: 10,
            },
            { preserveState: true, replace: true },
        );
    };

    const changeTab = (nextTab: string) => {
        router.get(
            route('auditor.audits.index'),
            {
                tab: nextTab,
                sort: 'date',
                direction: 'desc',
                per_page: currentPerPage,
            },
            { preserveState: false, replace: true },
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

    const statusLabel = (value: string | null | undefined) =>
        value ? (auditStatuses[value] ?? value) : '—';

    const formatCompletedAt = (value: string | null | undefined) => {
        if (!value) {
            return '—';
        }

        return new Date(value).toLocaleString(app.locale);
    };

    const leadLink = (row: { id: number; lead_reference: string }) => (
        <Link
            href={route('auditor.audits.show', row.id)}
            className="font-mono text-sm font-semibold text-rml-primary hover:underline"
        >
            {row.lead_reference}
        </Link>
    );

    const openAction = (rowId: number) => (
        <TableActionLink
            href={route('auditor.audits.show', rowId)}
            label={openAuditLabel}
            icon={tableActionIcons.audit}
        />
    );

    return (
        <AppLayout title={pageTitle} subtitle={pageSubtitle}>
            <Head title={pageTitle} />

            <Tabs
                items={[
                    {
                        id: 'my-audits',
                        label: hub.tab_my_audits ?? 'My Audits',
                        count: tabCounts['my-audits'],
                    },
                    {
                        id: 'audit-queue',
                        label: hub.tab_audit_queue ?? 'Audit Queue',
                        count: tabCounts['audit-queue'],
                    },
                    {
                        id: 'completed',
                        label: hub.tab_completed ?? 'Completed',
                        count: tabCounts.completed,
                    },
                ]}
                value={tab}
                onChange={changeTab}
            >
                <FilterBar
                    compact
                    search={search}
                    onSearchChange={setSearch}
                    searchLabel={common.search}
                    searchPlaceholder={
                        isCompleted
                            ? completedCopy.search_placeholder
                            : tab === 'my-audits'
                              ? assignedCopy.search_placeholder
                              : queueCopy.search_placeholder
                    }
                    onOpenMobileFilters={() => setFiltersOpen(true)}
                    actions={
                        <Button size="sm" variant="ghost" onClick={resetFilters}>
                            {common.reset}
                        </Button>
                    }
                >
                    {isCompleted ? (
                        <div className="w-full sm:w-[14rem]">
                            <Select
                                label={completedCopy.recommendation}
                                aria-label={completedCopy.recommendation}
                                value={recommendation}
                                onChange={(e) =>
                                    setRecommendation(e.target.value)
                                }
                                options={[
                                    { value: '', label: common.all },
                                    ...(filterOptions.recommendations ?? []).map(
                                        (s) => ({
                                            value: s,
                                            label: statusLabel(s),
                                        }),
                                    ),
                                ]}
                            />
                        </div>
                    ) : (
                        <>
                            <div className="w-full sm:w-[12.5rem]">
                                <Select
                                    label={common.status}
                                    aria-label={common.status}
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    options={[
                                        { value: '', label: common.all },
                                        ...(filterOptions.statuses ?? []).map(
                                            (s) => ({
                                                value: s,
                                                label: statusLabel(s),
                                            }),
                                        ),
                                    ]}
                                />
                            </div>
                            <div className="w-full sm:w-[12.5rem]">
                                <Select
                                    label={
                                        assignedCopy.evidence_status ??
                                        queueCopy.evidence_status
                                    }
                                    aria-label={
                                        assignedCopy.evidence_status ??
                                        queueCopy.evidence_status
                                    }
                                    value={evidence}
                                    onChange={(e) =>
                                        setEvidence(e.target.value)
                                    }
                                    options={[
                                        { value: '', label: common.all },
                                        {
                                            value: 'available',
                                            label:
                                                assignedCopy.evidence_available ??
                                                queueCopy.evidence_available,
                                        },
                                        {
                                            value: 'missing',
                                            label:
                                                assignedCopy.evidence_missing ??
                                                queueCopy.evidence_missing,
                                        },
                                    ]}
                                />
                            </div>
                        </>
                    )}
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

                <MobileFilterDrawer
                    open={filtersOpen}
                    onClose={() => setFiltersOpen(false)}
                    onApply={applyFilters}
                    onReset={resetFilters}
                >
                    {isCompleted ? (
                        <Select
                            label={completedCopy.recommendation}
                            aria-label={completedCopy.recommendation}
                            value={recommendation}
                            onChange={(e) => setRecommendation(e.target.value)}
                            options={[
                                { value: '', label: common.all },
                                ...(filterOptions.recommendations ?? []).map(
                                    (s) => ({
                                        value: s,
                                        label: statusLabel(s),
                                    }),
                                ),
                            ]}
                        />
                    ) : (
                        <>
                            <Select
                                label={common.status}
                                aria-label={common.status}
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                options={[
                                    { value: '', label: common.all },
                                    ...(filterOptions.statuses ?? []).map(
                                        (s) => ({
                                            value: s,
                                            label: statusLabel(s),
                                        }),
                                    ),
                                ]}
                            />
                            <Select
                                label={
                                    assignedCopy.evidence_status ??
                                    queueCopy.evidence_status
                                }
                                aria-label={
                                    assignedCopy.evidence_status ??
                                    queueCopy.evidence_status
                                }
                                value={evidence}
                                onChange={(e) => setEvidence(e.target.value)}
                                options={[
                                    { value: '', label: common.all },
                                    {
                                        value: 'available',
                                        label:
                                            assignedCopy.evidence_available ??
                                            queueCopy.evidence_available,
                                    },
                                    {
                                        value: 'missing',
                                        label:
                                            assignedCopy.evidence_missing ??
                                            queueCopy.evidence_missing,
                                    },
                                ]}
                            />
                        </>
                    )}
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
                </MobileFilterDrawer>

                {audits.data.length === 0 ? (
                    <EmptyState
                        title={common.empty}
                        description={emptyDescription}
                    />
                ) : isMobile ? (
                    <MobileCardList
                        items={audits.data.map((row) => ({
                            id: String(row.id),
                            title: leadLink(row),
                            subtitle: [row.scheme, row.zone, row.seller_name]
                                .filter(Boolean)
                                .join(' · '),
                            meta: (
                                <StatusBadge
                                    label={statusLabel(
                                        isCompleted
                                            ? row.recommendation
                                            : row.audit_status,
                                    )}
                                    tone={leadStatusTone(
                                        isCompleted
                                            ? row.recommendation
                                            : row.audit_status,
                                    )}
                                />
                            ),
                            actions: openAction(row.id),
                        }))}
                    />
                ) : isCompleted ? (
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
                                cell: (r) => leadLink(r),
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
                                    r.size_m2 != null
                                        ? `${r.size_m2} m²`
                                        : '—',
                            },
                            {
                                id: 'rec',
                                header: sortableHeader(
                                    completedCopy.recommendation ?? '',
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
                                header: completedCopy.final_decision,
                                cell: (r) =>
                                    r.final_decision
                                        ? statusLabel(r.final_decision)
                                        : (completedCopy.pending_final ?? '—'),
                            },
                            {
                                id: 'completed',
                                header: sortableHeader(
                                    completedCopy.completed_at ?? '',
                                    'date',
                                ),
                                cell: (r) =>
                                    formatCompletedAt(r.completed_at),
                            },
                            {
                                id: 'action',
                                header: common.actions,
                                cell: (r) => openAction(r.id),
                            },
                        ]}
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
                                cell: (r) => leadLink(r),
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
                                    r.size_m2 != null
                                        ? `${r.size_m2} m²`
                                        : '—',
                            },
                            {
                                id: 'seller',
                                header: common.seller,
                                cell: (r) => r.seller_name ?? '—',
                            },
                            {
                                id: 'submitted',
                                header: sortableHeader(
                                    common.submitted,
                                    'date',
                                ),
                                cell: (r) => r.submitted_at ?? '—',
                            },
                            {
                                id: 'audit',
                                header: sortableHeader(
                                    assignedCopy.audit_status ??
                                        queueCopy.audit_status ??
                                        '',
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
                                header:
                                    assignedCopy.evidence_status ??
                                    queueCopy.evidence_status,
                                cell: (r) =>
                                    r.evidence_status === 'missing'
                                        ? (assignedCopy.evidence_missing ??
                                          queueCopy.evidence_missing)
                                        : (assignedCopy.evidence_available ??
                                          queueCopy.evidence_available),
                            },
                            {
                                id: 'action',
                                header: common.actions,
                                cell: (r) => openAction(r.id),
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
            </Tabs>
        </AppLayout>
    );
}
