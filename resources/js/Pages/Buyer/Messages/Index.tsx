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
import {
    nextSortDirection,
    paginationLabels,
    paginationMeta,
    resolveSortDirection,
    type Paginator,
} from '@/lib/list-helpers';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface ThreadRow {
    id: number;
    thread_reference: string;
    subject: string;
    category: string | null;
    status: string | null;
    related_lead_id: number | null;
    related_lead_reference: string | null;
    related_purchase_id: number | null;
    related_purchase_reference: string | null;
    latest_message_preview: string | null;
    updated_at: string | null;
    created_at: string | null;
}

export default function BuyerMessagesIndex({
    threads,
    filters,
    filterOptions,
}: {
    threads: Paginator<ThreadRow>;
    filters: {
        status?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        statuses: string[];
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.buyer?.messages ?? {};
    const common = translations.buyer?.common ?? {};
    const statusLabels = {
        ...translations.statuses,
        ...translations.lead_statuses,
    };
    const isMobile = useIsMobile();

    const [status, setStatus] = useState(filters.status ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(threads);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const { data, setData, post, processing, errors, reset, transform } =
        useForm({
            subject: '',
            body: '',
            category: 'buyer_issue',
            related_lead_id: '',
            related_purchase_id: '',
        });

    const categoryOptions = [
        { value: 'buyer_issue', label: t.cat_buyer_issue ?? '' },
        { value: 'payment_query', label: t.cat_payment_query ?? '' },
        { value: 'complaint', label: t.cat_complaint ?? '' },
        { value: 'dispute', label: t.cat_dispute ?? '' },
        { value: 'internal', label: t.cat_internal ?? '' },
    ];

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        status: status || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(
            route('buyer.messages.index'),
            queryParams({ page: 1 }),
            { preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setStatus('');
        router.get(
            route('buyer.messages.index'),
            { sort: 'date', direction: 'desc', per_page: currentPerPage },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('buyer.messages.index'),
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

    const submit = (event: FormEvent) => {
        event.preventDefault();
        transform((form) => ({
            ...form,
            related_lead_id: form.related_lead_id
                ? Number(form.related_lead_id)
                : null,
            related_purchase_id: form.related_purchase_id
                ? Number(form.related_purchase_id)
                : null,
        }));
        post(route('buyer.messages.store'), {
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
                        label={common.lead_id}
                        name="related_lead_id"
                        type="number"
                        value={data.related_lead_id}
                        error={errors.related_lead_id}
                        onChange={(e) =>
                            setData('related_lead_id', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.related_purchase}
                        name="related_purchase_id"
                        type="number"
                        value={data.related_purchase_id}
                        error={errors.related_purchase_id}
                        onChange={(e) =>
                            setData('related_purchase_id', e.target.value)
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
                                    {thread.related_purchase_reference && (
                                        <p>
                                            {t.related_purchase}:{' '}
                                            {thread.related_purchase_reference}
                                        </p>
                                    )}
                                </div>
                            ),
                            actions: (
                                <Link
                                    href={route(
                                        'buyer.messages.show',
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
                                    translations.buyer?.payments?.reference ??
                                        '',
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
                                header: sortableHeader(
                                    t.subject ?? '',
                                    'subject',
                                ),
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
                                header: sortableHeader(
                                    common.status ?? '',
                                    'status',
                                ),
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
                                header: sortableHeader(
                                    translations.buyer?.payments?.date ?? '',
                                    'date',
                                ),
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
                                            'buyer.messages.show',
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
                            route('buyer.messages.index'),
                            queryParams({ per_page: next, page: 1 }),
                            { preserveState: true, replace: true },
                        )
                    }
                    onPageChange={(nextPage) =>
                        router.get(
                            route('buyer.messages.index'),
                            queryParams({ page: nextPage }),
                            { preserveState: true },
                        )
                    }
                    labels={paginationLabels(common)}
                />
            </section>
        </AppLayout>
    );
}
