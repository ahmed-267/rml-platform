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
} from '@/Components/ui';
import type { SortDirection } from '@/Components/ui/SortableHeader';
import {
    formatDateTime,
    paginationLabels,
    paginationMeta,
    type Paginator,
} from '@/lib/admin-helpers';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface ThreadRow {
    id: number;
    thread_reference: string;
    subject: string;
    category: string | null;
    status: string | null;
    created_by: { id: number; name: string; email: string } | null;
    related_lead_reference: string | null;
    latest_message_preview: string | null;
    updated_at: string | null;
}

export default function MessagesIndex({
    threads,
    filters,
    filterOptions,
}: {
    threads: Paginator<ThreadRow>;
    filters: {
        category?: string | null;
        status?: string | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        categories: string[];
        statuses: string[];
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.messages;
    const common = translations.admin.common;
    const statuses = translations.statuses;
    const categories =
        (translations.admin.messages as { categories?: Record<string, string> })
            .categories ?? {};
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [category, setCategory] = useState(filters.category ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection =
        filters.direction === 'asc' ? 'asc' : 'desc';
    const { page, pageCount, perPage } = paginationMeta(threads);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        search: search || undefined,
        category: category || undefined,
        status: status || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(route('admin.messages.index'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setCategory('');
        setStatus('');
        router.get(
            route('admin.messages.index'),
            { sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('admin.messages.index'),
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

    const categoryLabel = (value: string | null) =>
        value && categories[value] ? categories[value] : (value ?? '—');

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
                <div className="w-full sm:w-[14rem]">
                    <Select
                        label={t.filter_category}
                        aria-label={t.filter_category}
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        options={[
                            {
                                label: common.all_categories ?? common.all,
                                value: '',
                            },
                            ...filterOptions.categories.map((c) => ({
                                label: categoryLabel(c),
                                value: c,
                            })),
                        ]}
                    />
                </div>
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={t.filter_status}
                        aria-label={t.filter_status}
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        options={[
                            { label: common.all_statuses, value: '' },
                            ...filterOptions.statuses.map((s) => ({
                                label: leadStatusLabel(s, statuses),
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
                    label={t.filter_category}
                    aria-label={t.filter_category}
                    value={category}
                    onChange={(e) => setCategory(e.target.value)}
                    options={[
                        {
                            label: common.all_categories ?? common.all,
                            value: '',
                        },
                        ...filterOptions.categories.map((c) => ({
                            label: categoryLabel(c),
                            value: c,
                        })),
                    ]}
                />
                <Select
                    label={t.filter_status}
                    aria-label={t.filter_status}
                    value={status}
                    onChange={(e) => setStatus(e.target.value)}
                    options={[
                        { label: common.all_statuses, value: '' },
                        ...filterOptions.statuses.map((s) => ({
                            label: leadStatusLabel(s, statuses),
                            value: s,
                        })),
                    ]}
                />
            </MobileFilterDrawer>

            {threads.data.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={threads.data.map((row) => ({
                        id: String(row.id),
                        title: row.subject,
                        subtitle: row.thread_reference,
                        meta: row.status ? (
                            <StatusBadge
                                label={leadStatusLabel(row.status, statuses)}
                                tone={leadStatusTone(row.status)}
                            />
                        ) : null,
                        body: (
                            <p className="line-clamp-2 text-rml-muted">
                                {row.latest_message_preview ?? '—'}
                            </p>
                        ),
                        actions: (
                            <Link
                                href={route('admin.messages.show', row.id)}
                                className="text-sm font-semibold text-rml-primary"
                            >
                                {common.view}
                            </Link>
                        ),
                    }))}
                />
            ) : (
                <DataTable
                    data={threads.data}
                    getRowId={(r) => String(r.id)}
                    emptyMessage={common.empty}
                    columns={[
                        {
                            id: 'reference',
                            header: sortableHeader(common.reference, 'reference'),
                            cell: (row) => (
                                <span className="font-mono text-sm">
                                    {row.thread_reference}
                                </span>
                            ),
                        },
                        {
                            id: 'subject',
                            header: sortableHeader(
                                t.subject ?? t.show_title,
                                'subject',
                            ),
                            cell: (row) => (
                                <Link
                                    href={route('admin.messages.show', row.id)}
                                    className="font-medium text-rml-primary"
                                >
                                    {row.subject}
                                </Link>
                            ),
                        },
                        {
                            id: 'category',
                            header: sortableHeader(
                                t.filter_category,
                                'category',
                            ),
                            cell: (row) => categoryLabel(row.category),
                        },
                        {
                            id: 'status',
                            header: sortableHeader(common.status, 'status'),
                            cell: (row) =>
                                row.status ? (
                                    <StatusBadge
                                        label={leadStatusLabel(
                                            row.status,
                                            statuses,
                                        )}
                                        tone={leadStatusTone(row.status)}
                                    />
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            id: 'created_by',
                            header: t.created_by,
                            cell: (row) => row.created_by?.name ?? '—',
                        },
                        {
                            id: 'updated_at',
                            header: sortableHeader(common.date, 'date'),
                            cell: (row) =>
                                formatDateTime(row.updated_at, app.locale),
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
                        route('admin.messages.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('admin.messages.index'),
                        queryParams({ page: next }),
                        { preserveState: true },
                    )
                }
                labels={paginationLabels(common)}
            />
        </AppLayout>
    );
}
