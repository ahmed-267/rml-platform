import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    ConfirmDialog,
    DataTable,
    EmptyState,
    FilterBar,
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
    nextSortDirection,
    paginationLabels,
    paginationMeta,
    resolveSortDirection,
    type Paginator,
} from '@/lib/list-helpers';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusLabel } from '@/lib/lead-status';
import {
    purchaseFlowStatus,
    purchaseFlowStatusTone,
} from '@/lib/purchase-status';
import type { PageProps } from '@/types';
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';

interface PurchaseRow {
    id: number;
    purchase_reference: string;
    display_reference: string;
    status: string | null;
    total_amount: number | null;
    total_size_m2: number | null;
    purchased_at: string | null;
    created_at: string | null;
    scheme: string | null;
    zone: string | null;
    item_count: number;
    details_released: boolean;
    can_edit?: boolean;
    can_delete?: boolean;
    cannot_delete_reason?: string | null;
    payment: {
        id?: number;
        status: string | null;
        method: string | null;
    } | null;
}

function formatMoney(value: number | null | undefined): string {
    if (value == null) {
        return '—';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 2,
    }).format(value);
}

function formatDate(value: string | null, locale: string): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function BuyerPurchasesIndex({
    purchases,
    filters,
    filterOptions,
}: {
    purchases: Paginator<PurchaseRow>;
    filters: {
        status?: string | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        statuses: string[];
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.buyer?.purchases ?? {};
    const common = translations.buyer?.common ?? {};
    const purchaseStatuses = translations.purchase_statuses ?? {};
    const paymentStatuses = translations.payment_statuses ?? {};
    const paymentMethods = translations.payment_methods ?? {};
    const statusLabels = {
        ...purchaseStatuses,
        ...paymentStatuses,
        ...paymentMethods,
    };
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [deleteRow, setDeleteRow] = useState<PurchaseRow | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(purchases);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        search: search || undefined,
        status: status || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(
            route('buyer.purchases.index'),
            queryParams({ page: 1 }),
            { preserveState: true, replace: true },
        );
    }

    useInstantListFilters(applyFilters, search, [status]);


    const resetFilters = () => {
        setSearch('');
        setStatus('');
        router.get(
            route('buyer.purchases.index'),
            { sort: 'date', direction: 'desc', per_page: currentPerPage },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('buyer.purchases.index'),
            queryParams({
                sort: column,
                direction: nextSortDirection(
                    currentSort,
                    column,
                    currentDirection,
                ),
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

    const confirmDelete = () => {
        if (!deleteRow) {
            return;
        }
        setDeleteProcessing(true);
        router.delete(route('buyer.purchases.destroy', deleteRow.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleteProcessing(false);
                setDeleteRow(null);
            },
        });
    };

    const rowActions = (row: PurchaseRow) => (
        <TableActions>
            <TableActionLink
                href={route('buyer.purchases.show', row.id)}
                label={common.view ?? 'View'}
                icon={tableActionIcons.view}
            />
            {row.can_edit ? (
                <TableActionLink
                    href={route('buyer.purchases.show', row.id)}
                    label={common.edit ?? 'Edit'}
                    icon={tableActionIcons.edit}
                />
            ) : (
                <TableActionButton
                    label={t.cannot_edit_paid ?? 'Paid purchases cannot be edited'}
                    icon={tableActionIcons.edit}
                    disabled
                />
            )}
            {row.can_delete ? (
                <TableActionButton
                    label={common.delete ?? 'Delete'}
                    icon={tableActionIcons.delete}
                    tone="danger"
                    onClick={() => setDeleteRow(row)}
                />
            ) : (
                <TableActionButton
                    label={
                        row.cannot_delete_reason ??
                        t.cannot_delete_paid ??
                        'Paid purchases cannot be deleted'
                    }
                    icon={tableActionIcons.delete}
                    tone="danger"
                    disabled
                />
            )}
        </TableActions>
    );

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <FilterBar
                compact
                search={search}
                onSearchChange={setSearch}
                searchLabel={common.search}
                searchPlaceholder={t.search_placeholder ?? common.search}
                onOpenMobileFilters={() => setFiltersOpen(true)}
                actions={
                    <>
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
                            {
                                label: common.all_statuses ?? common.all,
                                value: '',
                            },
                            ...filterOptions.statuses.map((s) => ({
                                label: leadStatusLabel(s, statusLabels),
                                value: s,
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
                    value={status}
                    onChange={(e) => setStatus(e.target.value)}
                    options={[
                        {
                            label: common.all_statuses ?? common.all,
                            value: '',
                        },
                        ...filterOptions.statuses.map((s) => ({
                            label: leadStatusLabel(s, statusLabels),
                            value: s,
                        })),
                    ]}
                />
            </MobileFilterDrawer>

            {purchases.data.length === 0 ? (
                <EmptyState title={t.empty} />
            ) : isMobile ? (
                <MobileCardList
                    emptyMessage={t.empty}
                    items={purchases.data.map((purchase) => ({
                        id: String(purchase.id),
                        title: (
                            <span className="font-mono text-sm">
                                {purchase.display_reference}
                            </span>
                        ),
                        subtitle: purchase.scheme ?? undefined,
                        meta: (() => {
                            const status = purchaseFlowStatus(purchase);
                            return (
                                <StatusBadge
                                    label={leadStatusLabel(
                                        status,
                                        statusLabels,
                                    )}
                                    tone={purchaseFlowStatusTone(status)}
                                />
                            );
                        })(),
                        body: (
                            <div className="space-y-1 text-rml-muted">
                                <p>
                                    {common.zone}: {purchase.zone ?? '—'}
                                </p>
                                <p>
                                    {common.price}:{' '}
                                    {formatMoney(purchase.total_amount)}
                                </p>
                                <p>
                                    {t.release_status}:{' '}
                                    {purchase.details_released
                                        ? t.released
                                        : t.not_released}
                                </p>
                            </div>
                        ),
                        actions: rowActions(purchase),
                    }))}
                />
            ) : (
                <DataTable
                    data={purchases.data}
                    getRowId={(row) => String(row.id)}
                    emptyMessage={t.empty}
                    columns={[
                        {
                            id: 'reference',
                            header: sortableHeader(t.reference ?? '', 'reference'),
                            cell: (row) => (
                                <span className="font-mono text-sm font-medium">
                                    {row.display_reference}
                                </span>
                            ),
                        },
                        {
                            id: 'scheme',
                            header: sortableHeader(common.scheme ?? '', 'scheme'),
                            cell: (row) => row.scheme ?? '—',
                        },
                        {
                            id: 'zone',
                            header: sortableHeader(common.zone ?? '', 'zone'),
                            cell: (row) => row.zone ?? '—',
                        },
                        {
                            id: 'amount',
                            header: sortableHeader(common.price ?? '', 'amount'),
                            cell: (row) => formatMoney(row.total_amount),
                        },
                        {
                            id: 'status',
                            header: sortableHeader(common.status ?? '', 'status'),
                            cell: (row) => {
                                const status = purchaseFlowStatus(row);
                                return (
                                    <StatusBadge
                                        label={leadStatusLabel(
                                            status,
                                            statusLabels,
                                        )}
                                        tone={purchaseFlowStatusTone(status)}
                                    />
                                );
                            },
                        },
                        {
                            id: 'release',
                            header: t.release_status,
                            cell: (row) =>
                                row.details_released
                                    ? t.released
                                    : t.not_released,
                        },
                        {
                            id: 'purchased_at',
                            header: sortableHeader(
                                translations.buyer?.dashboard?.purchased_date ??
                                    'Date',
                                'date',
                            ),
                            cell: (row) =>
                                formatDate(
                                    row.purchased_at ?? row.created_at,
                                    app.locale,
                                ),
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
                        route('buyer.purchases.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(nextPage) =>
                    router.get(
                        route('buyer.purchases.index'),
                        queryParams({ page: nextPage }),
                        { preserveState: true },
                    )
                }
                labels={paginationLabels(common)}
            />

            <ConfirmDialog
                open={deleteRow != null}
                onClose={() => {
                    if (!deleteProcessing) {
                        setDeleteRow(null);
                    }
                }}
                onConfirm={confirmDelete}
                title={t.delete_confirm_title ?? 'Cancel this purchase?'}
                body={
                    t.delete_confirm_body ??
                    'This cancels the pending purchase and payment.'
                }
                confirmLabel={common.delete ?? 'Delete'}
                cancelLabel={common.cancel ?? 'Cancel'}
                processing={deleteProcessing}
            />
        </AppLayout>
    );
}
