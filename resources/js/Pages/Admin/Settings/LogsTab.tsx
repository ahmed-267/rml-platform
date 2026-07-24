import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Button,
    DataTable,
    Drawer,
    EmptyState,
    FormInput,
    MobileCardList,
    Modal,
    Pagination,
    Select,
    SortableHeader,
} from '@/Components/ui';
import type { SortDirection } from '@/Components/ui/SortableHeader';
import {
    formatDateTime,
    paginationLabels,
    paginationMeta,
    type Paginator,
} from '@/lib/admin-helpers';
import { useIsMobile } from '@/hooks/use-media-query';
import type { AuditLogRow } from './types';

type LogFilters = {
    action?: string | null;
    search?: string | null;
    user_id?: string | number | null;
    sort?: string | null;
    direction?: string | null;
    per_page?: string | number | null;
    page?: number | null;
};

type LogUserOption = {
    id: number;
    name: string;
};

export default function LogsTab({
    logs,
    filters,
    logUsers,
    logActions,
    t,
    auditT,
    common,
    locale,
}: {
    logs: Paginator<AuditLogRow> | null;
    filters: LogFilters;
    logUsers: LogUserOption[];
    logActions: string[];
    t: Record<string, string>;
    auditT: Record<string, string>;
    common: Record<string, string>;
    locale: string;
}) {
    const isMobile = useIsMobile();
    const [search, setSearch] = useState(filters.search ?? '');
    const [action, setAction] = useState(filters.action ?? '');
    const [userId, setUserId] = useState(
        filters.user_id != null ? String(filters.user_id) : '',
    );
    const [detail, setDetail] = useState<AuditLogRow | null>(null);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection =
        filters.direction === 'asc' ? 'asc' : 'desc';
    const currentPerPage = Number(filters.per_page ?? 10);

    const applyFilters = (overrides: Partial<LogFilters> = {}) => {
        const nextSort = overrides.sort ?? currentSort;
        const nextDirection = overrides.direction ?? currentDirection;
        const nextUser =
            overrides.user_id !== undefined
                ? overrides.user_id
                : userId || undefined;
        const nextAction =
            overrides.action !== undefined
                ? overrides.action
                : action || undefined;
        const nextSearch =
            overrides.search !== undefined
                ? overrides.search
                : search || undefined;
        const nextPerPage =
            overrides.per_page !== undefined
                ? overrides.per_page
                : currentPerPage;

        router.get(
            route('admin.settings.index'),
            {
                tab: 'logs',
                search: nextSearch || undefined,
                action: nextAction || undefined,
                user_id: nextUser || undefined,
                sort: nextSort,
                direction: nextDirection,
                per_page: nextPerPage,
                ...(overrides.page != null ? { page: overrides.page } : {}),
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        setAction('');
        setUserId('');
        router.get(
            route('admin.settings.index'),
            { tab: 'logs', sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    const handleSort = (column: string) => {
        applyFilters({
            sort: column,
            direction:
                currentSort === column && currentDirection === 'asc'
                    ? 'desc'
                    : 'asc',
            page: 1,
        });
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

    const openDetail = (log: AuditLogRow) => {
        if (log.old_values !== undefined || log.new_values !== undefined) {
            setDetail(log);
            return;
        }

        router.visit(route('admin.audit-logs.show', log.id), {
            preserveState: true,
            preserveScroll: true,
            only: ['log'],
            onSuccess: (page) => {
                const loaded = (page.props as { log?: AuditLogRow }).log;
                if (loaded) {
                    setDetail(loaded);
                }
            },
        });
    };

    if (logs == null) {
        return (
            <div className="space-y-3">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.logs_title}
                    </h2>
                    <p className="text-xs text-rml-muted">{t.logs_subtitle}</p>
                </div>
                <EmptyState title={common.empty} />
            </div>
        );
    }

    const { page, pageCount, perPage } = paginationMeta(logs);

    const subjectLabel = (log: AuditLogRow) => {
        const label = log.entity_label ?? log.entity_type;
        if (!label) {
            return common.not_available ?? '—';
        }

        return `${label}${log.entity_id != null ? ` #${log.entity_id}` : ''}`;
    };

    const userOptions = [
        { label: t.all_users, value: '' },
        ...logUsers.map((user) => ({
            label: user.name,
            value: String(user.id),
        })),
    ];

    const actionOptions = [
        { label: t.all_actions ?? auditT.action, value: '' },
        ...logActions.map((value) => ({ label: value, value })),
    ];

    const detailBody = detail ? (
        <div className="space-y-3">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-rml-muted">{auditT.action}</dt>
                    <dd>{detail.action}</dd>
                </div>
                <div>
                    <dt className="text-rml-muted">{t.subject}</dt>
                    <dd>{subjectLabel(detail)}</dd>
                </div>
                <div>
                    <dt className="text-rml-muted">{t.performed_by}</dt>
                    <dd>{detail.user?.name ?? common.unknown}</dd>
                </div>
                <div>
                    <dt className="text-rml-muted">{common.date}</dt>
                    <dd>{formatDateTime(detail.created_at, locale)}</dd>
                </div>
            </dl>
            <div>
                <h3 className="mb-1.5 text-sm font-semibold">
                    {auditT.old_values}
                </h3>
                <pre className="max-h-40 overflow-auto rounded-lg bg-rml-background p-2 text-xs">
                    {JSON.stringify(detail.old_values ?? {}, null, 2)}
                </pre>
            </div>
            <div>
                <h3 className="mb-1.5 text-sm font-semibold">
                    {auditT.new_values}
                </h3>
                <pre className="max-h-40 overflow-auto rounded-lg bg-rml-background p-2 text-xs">
                    {JSON.stringify(detail.new_values ?? {}, null, 2)}
                </pre>
            </div>
        </div>
    ) : null;

    return (
        <div className="space-y-3">
            <div>
                <h2 className="text-base font-semibold text-rml-text">
                    {t.logs_title}
                </h2>
                <p className="text-xs text-rml-muted">{t.logs_subtitle}</p>
            </div>

            <form
                className="flex flex-col gap-2 rounded-xl border border-rml-border bg-white p-3 lg:flex-row lg:flex-wrap lg:items-end"
                onSubmit={(e) => {
                    e.preventDefault();
                    applyFilters({ page: 1 });
                }}
            >
                <div className="min-w-0 w-full flex-1 lg:min-w-[14rem]">
                    <FormInput
                        label={common.search}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={auditT.search_placeholder}
                        aria-label={common.search}
                    />
                </div>
                <div className="w-full lg:w-44">
                    <Select
                        label={t.filter_user}
                        aria-label={t.filter_user}
                        value={userId}
                        onChange={(e) => setUserId(e.target.value)}
                        options={userOptions}
                    />
                </div>
                <div className="w-full lg:w-52">
                    <Select
                        label={auditT.action}
                        aria-label={auditT.action}
                        value={action}
                        onChange={(e) => setAction(e.target.value)}
                        options={actionOptions}
                    />
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <Button type="submit" size="sm">
                        {common.apply}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={resetFilters}
                    >
                        {common.reset}
                    </Button>
                </div>
            </form>

            {logs.data.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={logs.data.map((log) => ({
                        id: String(log.id),
                        title: log.user?.name ?? common.unknown,
                        subtitle: log.action,
                        body: (
                            <p className="text-rml-muted">{subjectLabel(log)}</p>
                        ),
                        meta: (
                            <span className="text-xs text-rml-muted">
                                {formatDateTime(log.created_at, locale)}
                            </span>
                        ),
                        actions: (
                            <button
                                type="button"
                                className="text-xs font-semibold text-rml-primary"
                                onClick={() => openDetail(log)}
                            >
                                {common.details}
                            </button>
                        ),
                    }))}
                />
            ) : (
                <DataTable
                    data={logs.data}
                    getRowId={(row) => String(row.id)}
                    emptyMessage={common.empty}
                    columns={[
                        {
                            id: 'performed_by',
                            header: sortableHeader(t.performed_by, 'user'),
                            cell: (row) => row.user?.name ?? common.unknown,
                        },
                        {
                            id: 'action',
                            header: sortableHeader(auditT.action, 'action'),
                            cell: (row) => row.action,
                        },
                        {
                            id: 'subject',
                            header: sortableHeader(t.subject, 'entity'),
                            cell: (row) => subjectLabel(row),
                        },
                        {
                            id: 'created_at',
                            header: sortableHeader(common.date, 'date'),
                            cell: (row) =>
                                formatDateTime(row.created_at, locale),
                        },
                        {
                            id: 'actions',
                            header: common.actions,
                            cell: (row) => (
                                <button
                                    type="button"
                                    className="text-xs font-semibold text-rml-primary hover:underline"
                                    onClick={() => openDetail(row)}
                                >
                                    {common.details}
                                </button>
                            ),
                        },
                    ]}
                />
            )}

            <Pagination
                page={page}
                pageCount={pageCount}
                perPage={Number(filters.per_page ?? perPage ?? 10)}
                onPerPageChange={(next) =>
                    applyFilters({ per_page: next, page: 1 })
                }
                onPageChange={(next) =>
                    applyFilters({
                        page: next,
                        per_page: filters.per_page ?? perPage ?? 10,
                    })
                }
                labels={paginationLabels(common)}
            />

            {isMobile ? (
                <Drawer
                    open={detail != null}
                    onClose={() => setDetail(null)}
                    title={auditT.show_title}
                    side="bottom"
                >
                    {detailBody}
                </Drawer>
            ) : (
                <Modal
                    open={detail != null}
                    onClose={() => setDetail(null)}
                    title={auditT.show_title}
                    size="lg"
                    footer={
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDetail(null)}
                        >
                            {common.close}
                        </Button>
                    }
                >
                    {detailBody}
                </Modal>
            )}
        </div>
    );
}
