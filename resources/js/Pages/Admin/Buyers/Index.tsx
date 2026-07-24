import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { AccountRowActions } from '@/Components/admin/AccountRowActions';
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

interface BuyerRow {
    id: number;
    name: string;
    email: string;
    company_name: string | null;
    approval_status: ApprovalStatus;
    purchases_count: number;
    leads_bought_count: number;
    created_at: string | null;
}

export default function BuyersIndex({
    buyers,
    filters,
    filterOptions,
}: {
    buyers: Paginator<BuyerRow>;
    filters: {
        approval_status?: string | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: { approval_statuses: string[] };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.buyers;
    const common = translations.admin.common;
    const statuses = translations.statuses;
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [approvalStatus, setApprovalStatus] = useState(
        filters.approval_status ?? '',
    );

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection =
        filters.direction === 'asc' ? 'asc' : 'desc';
    const { page, pageCount, perPage } = paginationMeta(buyers);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const actionLabels = accountActionLabels(common);

    const queryParams = (overrides: Record<string, string | number | undefined> = {}) => ({
        search: search || undefined,
        approval_status: approvalStatus || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(route('admin.buyers.index'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setApprovalStatus('');
        router.get(
            route('admin.buyers.index'),
            { sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('admin.buyers.index'),
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

    const rowActions = (row: BuyerRow) => (
        <AccountRowActions
            status={row.approval_status}
            targetName={row.name}
            companyName={row.company_name}
            viewHref={route('admin.buyers.show', row.id)}
            approveRoute={route('admin.buyers.approve', row.id)}
            rejectRoute={route('admin.buyers.reject', row.id)}
            suspendRoute={route('admin.buyers.suspend', row.id)}
            reinstateRoute={route('admin.buyers.reinstate', row.id)}
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
            </FilterBar>

            {buyers.data.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={buyers.data.map((row) => ({
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
                                <p>
                                    {t.purchases}: {row.purchases_count}
                                </p>
                            </div>
                        ),
                        actions: rowActions(row),
                    }))}
                />
            ) : (
                <DataTable
                    data={buyers.data}
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
                            cell: (r) => r.email,
                        },
                        {
                            id: 'status',
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
                            id: 'purchases',
                            header: sortableHeader(t.purchases, 'purchases'),
                            cell: (row) => row.purchases_count,
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
                        route('admin.buyers.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('admin.buyers.index'),
                        queryParams({ page: next }),
                        { preserveState: true },
                    )
                }
                labels={paginationLabels(common)}
            />
        </AppLayout>
    );
}
