import { FormEvent, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    MobileFilterDrawer,
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
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
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
    related_lead_id: number | null;
    related_lead_reference: string | null;
    latest_message_preview: string | null;
    updated_at: string | null;
    created_at: string | null;
}

export default function SellerMessagesIndex({
    threads,
    filters,
    filterOptions,
}: {
    threads: Paginator<ThreadRow>;
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
    const t = translations.seller.messages;
    const common = translations.seller.common;
    const statusLabels = {
        ...translations.statuses,
        ...translations.lead_statuses,
    };
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(threads);
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
        router.get(route('seller.messages.index'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        router.get(
            route('seller.messages.index'),
            { sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('seller.messages.index'),
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

    const { data, setData, post, processing, errors, reset, transform } =
        useForm({
            subject: '',
            body: '',
            category: 'seller_issue',
            related_lead_id: '',
        });

    const categoryOptions = [
        { value: 'seller_issue', label: t.cat_seller_issue },
        { value: 'payment_query', label: t.cat_payment_query },
        { value: 'information_request', label: t.cat_information_request },
        { value: 'internal', label: t.cat_internal },
    ];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        transform((form) => ({
            ...form,
            related_lead_id: form.related_lead_id
                ? Number(form.related_lead_id)
                : null,
        }));
        post(route('seller.messages.store'), {
            onSuccess: () => reset(),
            onFinish: () => {
                transform((form) => form);
            },
        });
    };

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.new}
                </h2>
                <form onSubmit={submit} className="space-y-4">
                    <Select
                        label={t.category}
                        name="category"
                        value={data.category}
                        options={categoryOptions}
                        error={errors.category}
                        onChange={(e) => setData('category', e.target.value)}
                    />
                    <FormInput
                        label={t.subject}
                        name="subject"
                        required
                        value={data.subject}
                        error={errors.subject}
                        onChange={(e) => setData('subject', e.target.value)}
                    />
                    <FormInput
                        label={t.related_lead}
                        name="related_lead_id"
                        type="number"
                        value={data.related_lead_id}
                        error={errors.related_lead_id}
                        onChange={(e) =>
                            setData('related_lead_id', e.target.value)
                        }
                    />
                    <Textarea
                        label={t.body}
                        name="body"
                        required
                        value={data.body}
                        error={errors.body}
                        onChange={(e) => setData('body', e.target.value)}
                    />
                    <Button type="submit" disabled={processing}>
                        {t.send}
                    </Button>
                </form>
            </section>

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
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        options={[
                            { value: '', label: common.all_statuses },
                            ...filterOptions.statuses.map((value) => ({
                                value,
                                label: leadStatusLabel(value, statusLabels),
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
                        { value: '', label: common.all_statuses },
                        ...filterOptions.statuses.map((value) => ({
                            value,
                            label: leadStatusLabel(value, statusLabels),
                        })),
                    ]}
                />
            </MobileFilterDrawer>

            <section className="space-y-3">
                {threads.data.length === 0 ? (
                    <EmptyState title={t.empty} />
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.empty}
                        items={threads.data.map((thread) => ({
                            id: String(thread.id),
                            title: thread.subject,
                            subtitle: thread.latest_message_preview ?? undefined,
                            meta: thread.status ? (
                                <StatusBadge
                                    label={leadStatusLabel(
                                        thread.status,
                                        statusLabels,
                                    )}
                                    tone={leadStatusTone(thread.status)}
                                />
                            ) : null,
                            body: (
                                <div className="space-y-1 text-rml-muted">
                                    <p className="font-mono text-xs">
                                        {thread.thread_reference}
                                    </p>
                                    {thread.related_lead_reference && (
                                        <p>
                                            {t.related_lead}:{' '}
                                            {thread.related_lead_reference}
                                        </p>
                                    )}
                                </div>
                            ),
                            actions: (
                                <Link
                                    href={route(
                                        'seller.messages.show',
                                        thread.id,
                                    )}
                                    className="text-sm font-semibold text-rml-primary"
                                >
                                    {t.open_thread}
                                </Link>
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={threads.data}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.empty}
                        columns={[
                            {
                                id: 'reference',
                                header: sortableHeader(
                                    translations.seller.payments.reference,
                                    'reference',
                                ),
                                cell: (row) => (
                                    <span className="font-mono text-sm">
                                        {row.thread_reference}
                                    </span>
                                ),
                            },
                            {
                                id: 'subject',
                                header: sortableHeader(t.subject, 'subject'),
                                cell: (row) => row.subject,
                            },
                            {
                                id: 'preview',
                                header: t.body,
                                cell: (row) =>
                                    row.latest_message_preview ?? '—',
                            },
                            {
                                id: 'status',
                                header: sortableHeader(common.status, 'status'),
                                cell: (row) =>
                                    row.status ? (
                                        <StatusBadge
                                            label={leadStatusLabel(
                                                row.status,
                                                statusLabels,
                                            )}
                                            tone={leadStatusTone(row.status)}
                                        />
                                    ) : (
                                        '—'
                                    ),
                            },
                            {
                                id: 'updated',
                                header: sortableHeader(common.date, 'date'),
                                cell: (row) =>
                                    row.updated_at
                                        ? new Date(
                                              row.updated_at,
                                          ).toLocaleString(app.locale)
                                        : '—',
                            },
                            {
                                id: 'actions',
                                header: common.actions,
                                cell: (row) => (
                                    <Link
                                        href={route(
                                            'seller.messages.show',
                                            row.id,
                                        )}
                                        className="font-semibold text-rml-primary hover:underline"
                                    >
                                        {t.open_thread}
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
                            route('seller.messages.index'),
                            queryParams({ per_page: next, page: 1 }),
                            { preserveState: true, replace: true },
                        )
                    }
                    onPageChange={(next) =>
                        router.get(
                            route('seller.messages.index'),
                            queryParams({ page: next }),
                            { preserveState: true },
                        )
                    }
                    labels={paginationLabels(common)}
                />
            </section>
        </AppLayout>
    );
}
