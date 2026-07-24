import { FormEvent, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    FormInput,
    MobileCardList,
    Pagination,
    Select,
    SortableHeader,
    StatusBadge,
    Textarea,
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

interface ThreadRow {
    id: number;
    thread_reference: string;
    subject: string;
    category: string | null;
    status: string | null;
    lead_reference: string | null;
    preview: string | null;
    updated_at: string | null;
}

export default function AuditorMessagesIndex({
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
    filterOptions: { categories: string[]; statuses: string[] };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.auditor;
    const common = t.common;
    const categories = t.message_categories ?? {};
    const statuses = translations.statuses;
    const isMobile = useIsMobile();
    const [showCreate, setShowCreate] = useState(false);

    const [search, setSearch] = useState(filters.search ?? '');
    const [category, setCategory] = useState(filters.category ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(threads);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const form = useForm({
        subject: '',
        body: '',
        category: 'internal',
        related_lead_id: '',
    });

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
        router.get(route('auditor.messages.index'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setCategory('');
        setStatus('');
        router.get(
            route('auditor.messages.index'),
            { sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('auditor.messages.index'),
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

    const createThread = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('auditor.messages.store'), {
            onSuccess: () => {
                form.reset();
                setShowCreate(false);
            },
        });
    };

    const formatUpdatedAt = (value: string | null) => {
        if (!value) {
            return '—';
        }

        return new Date(value).toLocaleString(app.locale);
    };

    return (
        <AppLayout
            title={t.messages.index_title}
            subtitle={t.messages.index_subtitle}
        >
            <Head title={t.messages.index_title} />

            <FilterBar
                compact
                search={search}
                onSearchChange={setSearch}
                searchLabel={common.search}
                searchPlaceholder={t.messages.search_placeholder}
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
                endActions={
                    <Button
                        size="sm"
                        type="button"
                        onClick={() => setShowCreate((v) => !v)}
                    >
                        {t.messages.new_thread}
                    </Button>
                }
            >
                <div className="w-full sm:w-[14rem]">
                    <Select
                        label={t.messages.category}
                        aria-label={t.messages.category}
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        options={[
                            { value: '', label: common.all },
                            ...filterOptions.categories.map((c) => ({
                                value: c,
                                label: categories[c] ?? c,
                            })),
                        ]}
                    />
                </div>
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
                                label: statuses[s] ?? s,
                            })),
                        ]}
                    />
                </div>
            </FilterBar>

            {showCreate && (
                <form
                    onSubmit={createThread}
                    className="rml-card space-y-3 p-4"
                >
                    <FormInput
                        label={t.messages.subject}
                        value={form.data.subject}
                        onChange={(e) => form.setData('subject', e.target.value)}
                        error={form.errors.subject}
                    />
                    <Select
                        label={t.messages.category}
                        value={form.data.category}
                        onChange={(e) =>
                            form.setData('category', e.target.value)
                        }
                        options={filterOptions.categories.map((c) => ({
                            value: c,
                            label: categories[c] ?? c,
                        }))}
                    />
                    <Textarea
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        rows={4}
                        placeholder={t.messages.body_placeholder}
                    />
                    {form.errors.body && (
                        <p className="text-sm text-rml-red">{form.errors.body}</p>
                    )}
                    <Button type="submit" disabled={form.processing}>
                        {t.messages.send}
                    </Button>
                </form>
            )}

            {threads.data.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={threads.data.map((row) => ({
                        id: String(row.id),
                        title: row.subject,
                        subtitle: row.preview ?? row.thread_reference,
                        meta: (
                            <StatusBadge
                                label={
                                    statuses[row.status ?? ''] ??
                                    row.status ??
                                    '—'
                                }
                                tone={leadStatusTone(row.status)}
                            />
                        ),
                        actions: (
                            <Link
                                href={route('auditor.messages.show', row.id)}
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
                            id: 'ref',
                            header: common.reference,
                            cell: (r) => (
                                <span className="font-mono text-sm">
                                    {r.thread_reference}
                                </span>
                            ),
                        },
                        {
                            id: 'subject',
                            header: sortableHeader(
                                t.messages.subject,
                                'subject',
                            ),
                            cell: (r) => r.subject,
                        },
                        {
                            id: 'category',
                            header: sortableHeader(
                                t.messages.category,
                                'category',
                            ),
                            cell: (r) =>
                                categories[r.category ?? ''] ??
                                r.category ??
                                '—',
                        },
                        {
                            id: 'lead',
                            header: sortableHeader(
                                common.lead_id,
                                'lead_reference',
                            ),
                            cell: (r) => r.lead_reference ?? '—',
                        },
                        {
                            id: 'status',
                            header: sortableHeader(common.status, 'status'),
                            cell: (r) => (
                                <StatusBadge
                                    label={
                                        statuses[r.status ?? ''] ??
                                        r.status ??
                                        '—'
                                    }
                                    tone={leadStatusTone(r.status)}
                                />
                            ),
                        },
                        {
                            id: 'updated',
                            header: sortableHeader(common.date, 'date'),
                            cell: (r) => formatUpdatedAt(r.updated_at),
                        },
                        {
                            id: 'action',
                            header: common.actions,
                            cell: (r) => (
                                <Link
                                    href={route('auditor.messages.show', r.id)}
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
                        route('auditor.messages.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('auditor.messages.index'),
                        queryParams({ page: next }),
                        { preserveState: true },
                    )
                }
                labels={paginationLabels(common)}
            />
        </AppLayout>
    );
}
