import { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    FormInput,
    MobileFilterDrawer,
    MobileCardList,
    Modal,
    Pagination,
    Select,
    SortableHeader,
    StatusBadge,
    TableActionButton,
    TableActionLink,
    TableActions,
    Textarea,
    tableActionIcons,
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
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';

interface ThreadRow {
    id: number;
    thread_reference: string;
    subject: string;
    category: string | null;
    status: string | null;
    created_by: { id: number; name: string; email: string } | null;
    assigned_to: { id: number; name: string; email: string } | null;
    related_lead_reference: string | null;
    latest_message_preview: string | null;
    updated_at: string | null;
}

interface MessageRecipient {
    id: number;
    name: string;
    email: string;
    label: string;
}

export default function MessagesIndex({
    threads,
    filters,
    filterOptions,
    recipients,
    can_delete_messages = false,
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
    recipients: MessageRecipient[];
    can_delete_messages?: boolean;
}) {
    const { translations, app, auth } = usePage<PageProps>().props;
    const t = translations.admin.messages;
    const common = translations.admin.common;
    const statuses = translations.statuses;
    const categories =
        (translations.admin.messages as { categories?: Record<string, string> })
            .categories ?? {};
    const isMobile = useIsMobile();
    const isSuperAdmin = auth.user?.primary_role === 'super_admin';
    const canDelete = can_delete_messages || isSuperAdmin;

    const [search, setSearch] = useState(filters.search ?? '');
    const [category, setCategory] = useState(filters.category ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [composeOpen, setComposeOpen] = useState(false);
    const [deleteRow, setDeleteRow] = useState<ThreadRow | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const {
        data: composeData,
        setData: setComposeData,
        post: postCompose,
        processing: composeProcessing,
        errors: composeErrors,
        reset: resetCompose,
    } = useForm({
        recipient_user_id: '',
        category: filterOptions.categories[0] ?? 'internal',
        subject: '',
        body: '',
    });

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

    useInstantListFilters(applyFilters, search, [category, status]);

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

    const categoryOptions = filterOptions.categories.map((value) => ({
        label: categoryLabel(value),
        value,
    }));

    const recipientOptions = recipients.map((recipient) => ({
        label: recipient.label,
        value: String(recipient.id),
    }));

    const closeCompose = () => {
        if (!composeProcessing) {
            setComposeOpen(false);
            resetCompose();
        }
    };

    const submitCompose = () => {
        postCompose(route('admin.messages.store'), {
            onSuccess: () => {
                setComposeOpen(false);
                resetCompose();
            },
        });
    };

    const confirmDelete = () => {
        if (!deleteRow) {
            return;
        }

        setDeleteProcessing(true);
        router.delete(route('admin.messages.destroy', deleteRow.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleteProcessing(false);
                setDeleteRow(null);
            },
        });
    };

    const deleteConfirmBody = useMemo(() => {
        if (!deleteRow) {
            return '';
        }

        return (t.delete_confirm_body ?? '').replaceAll(
            ':ref',
            deleteRow.thread_reference,
        );
    }, [deleteRow, t.delete_confirm_body]);

    const rowActions = (row: ThreadRow) => (
        <TableActions>
            <TableActionLink
                href={route('admin.messages.show', row.id)}
                label={common.view}
                icon={tableActionIcons.view}
            />
            {canDelete && (
                <TableActionButton
                    label={common.delete}
                    icon={tableActionIcons.delete}
                    tone="danger"
                    onClick={() => setDeleteRow(row)}
                />
            )}
        </TableActions>
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
                        <Button size="sm" onClick={() => setComposeOpen(true)}>
                            {t.start_conversation}
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
                            ...categoryOptions,
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
                        ...categoryOptions,
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
                        actions: rowActions(row),
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

            <Modal
                open={composeOpen}
                onClose={closeCompose}
                title={t.compose_title}
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={composeProcessing}
                            onClick={closeCompose}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            size="sm"
                            disabled={composeProcessing}
                            onClick={submitCompose}
                        >
                            {t.send_message}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Select
                        label={t.recipient}
                        aria-label={t.recipient}
                        value={composeData.recipient_user_id}
                        onChange={(e) =>
                            setComposeData('recipient_user_id', e.target.value)
                        }
                        options={[
                            {
                                label: t.select_recipient,
                                value: '',
                            },
                            ...recipientOptions,
                        ]}
                        error={composeErrors.recipient_user_id}
                        required
                    />
                    <Select
                        label={t.filter_category}
                        aria-label={t.filter_category}
                        value={composeData.category}
                        onChange={(e) =>
                            setComposeData('category', e.target.value)
                        }
                        options={[
                            {
                                label: t.select_category,
                                value: '',
                            },
                            ...categoryOptions,
                        ]}
                        error={composeErrors.category}
                        required
                    />
                    <FormInput
                        label={t.subject}
                        name="subject"
                        value={composeData.subject}
                        error={composeErrors.subject}
                        onChange={(e) =>
                            setComposeData('subject', e.target.value)
                        }
                        hint={
                            categories[composeData.category]
                                ? `${t.select_category}: ${categories[composeData.category]}`
                                : undefined
                        }
                    />
                    <Textarea
                        label={t.body}
                        name="body"
                        required
                        value={composeData.body}
                        error={composeErrors.body}
                        onChange={(e) => setComposeData('body', e.target.value)}
                    />
                </div>
            </Modal>

            <Modal
                open={deleteRow != null}
                onClose={() => {
                    if (!deleteProcessing) {
                        setDeleteRow(null);
                    }
                }}
                title={t.delete_confirm_title}
                size="sm"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={deleteProcessing}
                            onClick={() => setDeleteRow(null)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            size="sm"
                            variant="danger"
                            disabled={deleteProcessing}
                            onClick={confirmDelete}
                        >
                            {common.delete}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-rml-muted">{deleteConfirmBody}</p>
            </Modal>
        </AppLayout>
    );
}
