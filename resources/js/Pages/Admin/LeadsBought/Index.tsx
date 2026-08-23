import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { AuditLeadModal } from '@/Components/admin/AuditLeadModal';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    FormInput,
    MobileFilterDrawer,
    MobileCardList,
    Pagination,
    Select,
    SortableHeader,
    StatusBadge,
    TableActionButton,
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
    seller_display?: string | null;
    seller_is_company?: boolean;
    submitted_at: string | null;
    distance_km?: number | null;
    packagable?: boolean;
    sellable?: boolean;
}

type MatchInstallerOption = {
    id: number;
    name: string;
    city?: string | null;
    base_location?: string | null;
    matched_count?: number;
};

export default function LeadsBoughtIndex({
    leads,
    filters,
    filterOptions,
    nearby = null,
    can_create = false,
    can_sell = false,
    embedded = false,
}: {
    leads: Paginator<LeadRow>;
    filters: {
        status?: string | null;
        scheme_id?: string | number | null;
        zone_id?: string | number | null;
        zone_code?: string | null;
        seller_company_id?: string | number | null;
        submitted_from?: string | null;
        submitted_to?: string | null;
        search?: string | null;
        match_installer_id?: string | number | null;
        radius_km?: string | number | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        statuses: string[];
        schemes: Array<{ id: number; name: string }>;
        zones: Array<{ id: number; code: string; name: string }>;
        seller_companies?: Array<{ id: number; name: string }>;
        match_installers?: MatchInstallerOption[];
        radius_options_km?: number[];
    };
    nearby?: {
        active: boolean;
        installer: { company_name: string } | null;
        radius_km: number;
        summary: { matched_count: number };
    } | null;
    can_create?: boolean;
    can_sell?: boolean;
    embedded?: boolean;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.leads_bought;
    const leadsT = translations.admin.leads;
    const common = translations.admin.common;
    const leadStatuses = translations.lead_statuses;
    const isMobile = useIsMobile();

    const matchInstallers = filterOptions.match_installers ?? [];
    const radiusOptions = filterOptions.radius_options_km ?? [
        10, 25, 50, 100, 200,
    ];
    const matchActive = Boolean(nearby?.active && nearby.installer);

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );
    const [zoneCode, setZoneCode] = useState(filters.zone_code ?? '');
    const [sellerCompanyId, setSellerCompanyId] = useState(
        filters.seller_company_id != null
            ? String(filters.seller_company_id)
            : '',
    );
    const [submittedFrom, setSubmittedFrom] = useState(
        filters.submitted_from ?? '',
    );
    const [submittedTo, setSubmittedTo] = useState(filters.submitted_to ?? '');
    const [matchInstallerId, setMatchInstallerId] = useState(
        filters.match_installer_id != null
            ? String(filters.match_installer_id)
            : '',
    );
    const [radiusKm, setRadiusKm] = useState(
        filters.radius_km != null
            ? String(filters.radius_km)
            : String(radiusOptions[2] ?? 50),
    );
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [auditLeadId, setAuditLeadId] = useState<number | null>(null);
    const [auditOpen, setAuditOpen] = useState(false);

    const currentSort = filters.sort ?? (matchActive ? 'distance' : 'date');
    const currentDirection: SortDirection =
        filters.direction === 'asc'
            ? 'asc'
            : filters.direction === 'desc'
              ? 'desc'
              : matchActive
                ? 'asc'
                : 'desc';
    const { page, pageCount, perPage } = paginationMeta(leads);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const listHref = route('admin.leads.index');
    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        tab: 'registered',
        search: search || undefined,
        status: status || undefined,
        scheme_id: schemeId || undefined,
        zone_code: zoneCode || undefined,
        seller_company_id: sellerCompanyId || undefined,
        submitted_from: submittedFrom || undefined,
        submitted_to: submittedTo || undefined,
        match_installer_id: matchInstallerId || undefined,
        radius_km: matchInstallerId ? radiusKm || undefined : undefined,
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
        zoneCode,
        sellerCompanyId,
        submittedFrom,
        submittedTo,
        matchInstallerId,
        radiusKm,
    ]);

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setSchemeId('');
        setZoneCode('');
        setSellerCompanyId('');
        setSubmittedFrom('');
        setSubmittedTo('');
        setMatchInstallerId('');
        setRadiusKm(String(radiusOptions[2] ?? 50));
        router.get(
            listHref,
            { tab: 'registered', per_page: currentPerPage },
            { preserveState: true, replace: true },
        );
    };

    const clearInstallerMatch = () => {
        setMatchInstallerId('');
        router.get(
            listHref,
            queryParams({
                match_installer_id: undefined,
                radius_km: undefined,
                sort: 'date',
                direction: 'desc',
                page: 1,
            }),
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

    const openAudit = (leadId: number) => {
        setAuditLeadId(leadId);
        setAuditOpen(true);
    };

    const closeAudit = () => {
        setAuditOpen(false);
        setAuditLeadId(null);
        router.reload({ only: ['leads'] });
    };

    const retryGeocode = (leadId: number) => {
        router.post(route('admin.leads.geocode', leadId), {}, {
            preserveScroll: true,
        });
    };

    const addToPackage = (lead: LeadRow) => {
        router.get(
            listHref,
            {
                tab: 'registered',
                view: 'map',
                marker_set: 'both',
                search: lead.lead_reference,
            },
            { preserveState: false, replace: true },
        );
    };

    const leadActions = (row: LeadRow) => (
        <TableActions>
            <TableActionLink
                href={route('admin.leads-bought.show', row.id)}
                label={common.view}
                icon={tableActionIcons.view}
            />
            <TableActionButton
                label={t.audit_lead}
                icon={tableActionIcons.audit}
                onClick={() => openAudit(row.id)}
            />
            <TableActionLink
                href={route('admin.leads-bought.show', {
                    lead: row.id,
                    audit: 1,
                })}
                label={t.assign_auditor ?? 'Assign auditor'}
                icon={tableActionIcons.assign}
            />
            <TableActionLink
                href={route('admin.leads-bought.show', row.id)}
                label={t.edit_location ?? 'Edit location'}
                icon={tableActionIcons.location}
            />
            <TableActionButton
                label={t.retry_geocoding ?? 'Retry geocoding'}
                icon={tableActionIcons.reinstate}
                onClick={() => retryGeocode(row.id)}
            />
            {row.packagable && (
                <TableActionButton
                    label={t.add_to_package ?? 'Add to package'}
                    icon={tableActionIcons.package}
                    onClick={() => addToPackage(row)}
                />
            )}
            {can_sell && (row.sellable ?? row.packagable) && (
                <TableActionLink
                    href={route('admin.sales.create', {
                        type: 'lead',
                        lead_id: row.id,
                        return_to: 'registered',
                    })}
                    label={leadsT.sell_lead ?? leadsT.buy_lead ?? 'Buy lead'}
                    icon={tableActionIcons.buy}
                />
            )}
        </TableActions>
    );

    const body = (
        <>
            {!embedded && <Head title={t.index_title} />}

            {(can_create || can_sell) && (
                <div className="flex flex-wrap justify-end gap-2">
                    {can_create && (
                        <Button
                            type="button"
                            size="sm"
                            variant="primary"
                            onClick={() =>
                                router.get(route('admin.leads.create'))
                            }
                        >
                            {leadsT.create_lead ?? t.create_lead ?? 'Create lead'}
                        </Button>
                    )}
                    {can_sell && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.get(
                                    route('admin.sales.create', {
                                        type: 'lead',
                                        return_to: 'registered',
                                    }),
                                )
                            }
                        >
                            {leadsT.sell_lead ?? leadsT.buy_lead ?? 'Buy lead'}
                        </Button>
                    )}
                </div>
            )}

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
                        value={zoneCode}
                        onChange={(e) => setZoneCode(e.target.value)}
                        options={[
                            {
                                label: t.all_zones ?? common.all,
                                value: '',
                            },
                            ...filterOptions.zones.map((z) => ({
                                label: z.code,
                                value: z.code,
                            })),
                        ]}
                    />
                </div>
                <div className="w-full sm:w-[14rem]">
                    <Select
                        label={
                            t.filter_seller_company ?? t.seller_company ?? 'Seller company'
                        }
                        value={sellerCompanyId}
                        onChange={(e) => setSellerCompanyId(e.target.value)}
                        options={[
                            {
                                label:
                                    t.all_seller_companies ?? common.all,
                                value: '',
                            },
                            ...(filterOptions.seller_companies ?? []).map(
                                (company) => ({
                                    label: company.name,
                                    value: String(company.id),
                                }),
                            ),
                        ]}
                    />
                </div>
                <div className="w-full sm:w-[11rem]">
                    <FormInput
                        type="date"
                        label={t.filter_submitted_from ?? 'Submitted from'}
                        value={submittedFrom}
                        onChange={(e) => setSubmittedFrom(e.target.value)}
                    />
                </div>
                <div className="w-full sm:w-[11rem]">
                    <FormInput
                        type="date"
                        label={t.filter_submitted_to ?? 'Submitted to'}
                        value={submittedTo}
                        onChange={(e) => setSubmittedTo(e.target.value)}
                    />
                </div>
                {matchInstallers.length > 0 && (
                    <>
                        <div className="w-full sm:w-[18rem]">
                            <Select
                                label={
                                    leadsT.map_match_installer ?? 'Installer'
                                }
                                value={matchInstallerId}
                                onChange={(e) =>
                                    setMatchInstallerId(e.target.value)
                                }
                                options={[
                                    {
                                        label:
                                            leadsT.map_match_installer_placeholder ??
                                            'Select installer',
                                        value: '',
                                    },
                                    ...matchInstallers.map((installer) => ({
                                        label: installer.city
                                            ? `${installer.name} — ${installer.city}${
                                                  installer.matched_count !=
                                                  null
                                                      ? ` · ${installer.matched_count}`
                                                      : ''
                                              }`
                                            : `${installer.name}${
                                                  installer.matched_count !=
                                                  null
                                                      ? ` · ${installer.matched_count}`
                                                      : ''
                                              }`,
                                        value: String(installer.id),
                                    })),
                                ]}
                            />
                        </div>
                        <div className="w-full sm:w-[10rem]">
                            <Select
                                label={leadsT.map_match_radius ?? 'Radius'}
                                value={radiusKm}
                                onChange={(e) => setRadiusKm(e.target.value)}
                                options={radiusOptions.map((km) => ({
                                    label: `${km} km`,
                                    value: String(km),
                                }))}
                            />
                        </div>
                        {matchInstallerId && (
                            <div className="flex items-end">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={clearInstallerMatch}
                                >
                                    {leadsT.map_clear_installer_match ??
                                        'Clear installer match'}
                                </Button>
                            </div>
                        )}
                    </>
                )}
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
                    value={zoneCode}
                    onChange={(e) => setZoneCode(e.target.value)}
                    options={[
                        {
                            label: t.all_zones ?? common.all,
                            value: '',
                        },
                        ...filterOptions.zones.map((z) => ({
                            label: z.code,
                            value: z.code,
                        })),
                    ]}
                />
                <Select
                    label={
                        t.filter_seller_company ??
                        t.seller_company ??
                        'Seller company'
                    }
                    value={sellerCompanyId}
                    onChange={(e) => setSellerCompanyId(e.target.value)}
                    options={[
                        {
                            label: t.all_seller_companies ?? common.all,
                            value: '',
                        },
                        ...(filterOptions.seller_companies ?? []).map(
                            (company) => ({
                                label: company.name,
                                value: String(company.id),
                            }),
                        ),
                    ]}
                />
                <FormInput
                    type="date"
                    label={t.filter_submitted_from ?? 'Submitted from'}
                    value={submittedFrom}
                    onChange={(e) => setSubmittedFrom(e.target.value)}
                />
                <FormInput
                    type="date"
                    label={t.filter_submitted_to ?? 'Submitted to'}
                    value={submittedTo}
                    onChange={(e) => setSubmittedTo(e.target.value)}
                />
            </MobileFilterDrawer>

            {matchActive && (
                <p className="text-sm text-rml-muted">
                    {nearby?.installer?.company_name} · ≤ {nearby?.radius_km}{' '}
                    km · {nearby?.summary.matched_count}{' '}
                    {(leadsT.map_nearby_leads ?? 'Nearby leads').toLowerCase()}
                </p>
            )}

            {leads.data.length === 0 ? (
                <EmptyState
                    title={
                        matchActive
                            ? (leadsT.map_no_nearby_matches ??
                              'No eligible leads found within this radius.')
                            : common.empty
                    }
                />
            ) : isMobile ? (
                <MobileCardList
                    items={leads.data.map((row) => ({
                        id: String(row.id),
                        title: (
                            <span className="font-mono text-sm">
                                {row.lead_reference}
                            </span>
                        ),
                        subtitle:
                            row.seller_display ??
                            row.seller_company ??
                            row.seller_name ??
                            '—',
                        meta: row.status ? (
                            <StatusBadge
                                label={leadStatusLabel(row.status, leadStatuses)}
                                tone={leadStatusTone(row.status)}
                            />
                        ) : null,
                        body: (
                            <div className="space-y-1 text-rml-muted">
                                <p>
                                    {t.seller_payout ?? t.buying_price}:{' '}
                                    {formatMoney(row.buying_price)}
                                </p>
                                <p>
                                    {t.selling_price}:{' '}
                                    {formatMoney(row.selling_price)}
                                </p>
                                {row.distance_km != null && (
                                    <p className="font-medium text-rml-primary">
                                        {(
                                            leadsT.map_distance_from_installer ??
                                            'Distance from installer: :km km'
                                        ).replace(':km', String(row.distance_km))}
                                    </p>
                                )}
                            </div>
                        ),
                        actions: leadActions(row),
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
                            header: sortableHeader(
                                t.seller_company ?? t.seller,
                                'seller',
                            ),
                            cell: (row) =>
                                row.seller_display ??
                                row.seller_company ??
                                row.seller_name ??
                                '—',
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
                        ...(matchActive
                            ? [
                                  {
                                      id: 'distance',
                                      header: sortableHeader(
                                          leadsT.distance ?? 'Distance',
                                          'distance',
                                      ),
                                      cell: (row: LeadRow) =>
                                          row.distance_km != null
                                              ? `${row.distance_km} km`
                                              : '—',
                                  },
                              ]
                            : []),
                        {
                            id: 'buying_price',
                            header: sortableHeader(
                                t.seller_payout ?? t.buying_price,
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
                            cell: (row) => leadActions(row),
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

            {auditLeadId != null && (
                <AuditLeadModal
                    open={auditOpen}
                    onClose={closeAudit}
                    leadId={auditLeadId}
                />
            )}
        </>
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
