import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { AccountRowActions } from '@/Components/admin/AccountRowActions';
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
    approvalStatusLabel,
    accountActionLabels,
    formatDate,
    paginationLabels,
    paginationMeta,
    type Paginator,
} from '@/lib/admin-helpers';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusTone } from '@/lib/lead-status';
import type { ApprovalStatus, PageProps } from '@/types';

interface SellerRow {
    id: number;
    name: string;
    email: string;
    role: string | null;
    role_label: string | null;
    seller_type: string | null;
    company_name: string | null;
    approval_status: ApprovalStatus;
    leads_submitted_count: number;
    leads_sold_count: number;
    created_at: string | null;
}

export default function SellersIndex({
    sellers,
    filters,
    filterOptions,
}: {
    sellers: Paginator<SellerRow>;
    filters: {
        approval_status?: string | null;
        role?: string | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        approval_statuses: string[];
        roles: string[];
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.sellers;
    const common = translations.admin.common;
    const statuses = translations.statuses;
    const roles = translations.roles;
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [approvalStatus, setApprovalStatus] = useState(
        filters.approval_status ?? '',
    );
    const [role, setRole] = useState(filters.role ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection =
        filters.direction === 'asc' ? 'asc' : 'desc';
    const { page, pageCount, perPage } = paginationMeta(sellers);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const actionLabels = accountActionLabels(common);

    const queryParams = (overrides: Record<string, string | number | undefined> = {}) => ({
        search: search || undefined,
        approval_status: approvalStatus || undefined,
        role: role || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(route('admin.sellers.index'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setApprovalStatus('');
        setRole('');
        router.get(
            route('admin.sellers.index'),
            { sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('admin.sellers.index'),
            queryParams({
                sort: column,
                direction:
                    currentSort === column && currentDirection === 'asc'
                        ? 'desc'
                        : 'asc',
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

    const roleLabel = (row: SellerRow) =>
        row.role && roles[row.role] ? roles[row.role] : row.role_label ?? '—';

    const rowActions = (row: SellerRow) => (
        <AccountRowActions
            status={row.approval_status}
            targetName={row.name}
            companyName={row.company_name}
            viewHref={route('admin.sellers.show', row.id)}
            approveRoute={route('admin.sellers.approve', row.id)}
            rejectRoute={route('admin.sellers.reject', row.id)}
            suspendRoute={route('admin.sellers.suspend', row.id)}
            reinstateRoute={route('admin.sellers.reinstate', row.id)}
            labels={actionLabels}
        />
    );

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
                        label={common.status}
                        aria-label={common.status}
                        value={approvalStatus}
                        onChange={(e) => setApprovalStatus(e.target.value)}
                        options={[
                            { label: common.all_statuses, value: '' },
                            ...filterOptions.approval_statuses.map((status) => ({
                                label: approvalStatusLabel(status, statuses),
                                value: status,
                            })),
                        ]}
                    />
                </div>
                <div className="w-full sm:w-[14rem]">
                    <Select
                        label={common.role}
                        aria-label={common.role}
                        value={role}
                        onChange={(e) => setRole(e.target.value)}
                        options={[
                            { label: common.all_roles, value: '' },
                            ...filterOptions.roles.map((r) => ({
                                label: roles[r] ?? r,
                                value: r,
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
                    value={approvalStatus}
                    onChange={(e) => setApprovalStatus(e.target.value)}
                    options={[
                        { label: common.all_statuses, value: '' },
                        ...filterOptions.approval_statuses.map((status) => ({
                            label: approvalStatusLabel(status, statuses),
                            value: status,
                        })),
                    ]}
                />
                <Select
                    label={common.role}
                    aria-label={common.role}
                    value={role}
                    onChange={(e) => setRole(e.target.value)}
                    options={[
                        { label: common.all_roles, value: '' },
                        ...filterOptions.roles.map((r) => ({
                            label: roles[r] ?? r,
                            value: r,
                        })),
                    ]}
                />
            </MobileFilterDrawer>

            {sellers.data.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={sellers.data.map((row) => ({
                        id: String(row.id),
                        title: row.name,
                        subtitle: row.company_name ?? row.email,
                        meta: (
                            <StatusBadge
                                label={approvalStatusLabel(
                                    row.approval_status,
                                    statuses,
                                )}
                                tone={leadStatusTone(row.approval_status)}
                            />
                        ),
                        body: (
                            <div className="space-y-1 text-rml-muted">
                                <p>{row.email}</p>
                                <p>{roleLabel(row)}</p>
                                <p>
                                    {t.leads_submitted}: {row.leads_submitted_count}
                                </p>
                                <p>
                                    {t.leads_sold}: {row.leads_sold_count}
                                </p>
                            </div>
                        ),
                        actions: rowActions(row),
                    }))}
                />
            ) : (
                <DataTable
                    data={sellers.data}
                    getRowId={(row) => String(row.id)}
                    emptyMessage={common.empty}
                    columns={[
                        {
                            id: 'name',
                            header: sortableHeader(common.name, 'name'),
                            cell: (row) => (
                                <span className="font-medium">{row.name}</span>
                            ),
                        },
                        {
                            id: 'company',
                            header: sortableHeader(common.company, 'company'),
                            cell: (row) => row.company_name ?? '—',
                        },
                        {
                            id: 'email',
                            header: sortableHeader(common.email, 'email'),
                            cell: (row) => row.email,
                        },
                        {
                            id: 'role',
                            header: sortableHeader(common.role, 'role'),
                            cell: (row) => roleLabel(row),
                        },
                        {
                            id: 'approval_status',
                            header: sortableHeader(common.status, 'status'),
                            cell: (row) => (
                                <StatusBadge
                                    label={approvalStatusLabel(
                                        row.approval_status,
                                        statuses,
                                    )}
                                    tone={leadStatusTone(row.approval_status)}
                                />
                            ),
                        },
                        {
                            id: 'leads',
                            header: sortableHeader(
                                t.leads_submitted,
                                'leads_submitted',
                            ),
                            cell: (row) => row.leads_submitted_count,
                        },
                        {
                            id: 'sold',
                            header: sortableHeader(t.leads_sold, 'leads_sold'),
                            cell: (row) => row.leads_sold_count,
                        },
                        {
                            id: 'created_at',
                            header: sortableHeader(common.date, 'date'),
                            cell: (row) => formatDate(row.created_at, app.locale),
                        },
                        {
                            id: 'actions',
                            header: common.actions,
                            cell: (row) => rowActions(row),
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
                        route('admin.sellers.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('admin.sellers.index'),
                        queryParams({ page: next }),
                        { preserveState: true },
                    )
                }
                labels={paginationLabels(common)}
            />
        </AppLayout>
    );
}
