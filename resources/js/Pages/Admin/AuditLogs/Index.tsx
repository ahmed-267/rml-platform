import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    Drawer,
    EmptyState,
    FilterBar,
    FormInput,
    MobileCardList,
    MobileFilterDrawer,
    Modal,
    Pagination,
} from '@/Components/ui';
import {
    formatDateTime,
    paginationMeta,
    type Paginator,
} from '@/lib/admin-helpers';
import { useIsMobile } from '@/hooks/use-media-query';
import type { PageProps } from '@/types';

interface AuditLogRow {
    id: number;
    action: string;
    entity_type: string;
    entity_id: number | null;
    user: { id: number; name: string; email: string } | null;
    ip_address: string | null;
    created_at: string | null;
    old_values?: Record<string, unknown> | null;
    new_values?: Record<string, unknown> | null;
    user_agent?: string | null;
}

export default function AuditLogsIndex({
    logs,
    filters,
}: {
    logs: Paginator<AuditLogRow>;
    filters: {
        action?: string | null;
        search?: string | null;
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.audit_logs;
    const common = translations.admin.common;
    const isMobile = useIsMobile();

    const [search, setSearch] = useState(filters.search ?? '');
    const [action, setAction] = useState(filters.action ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [detail, setDetail] = useState<AuditLogRow | null>(null);

    const applyFilters = () => {
        router.get(
            route('admin.audit-logs.index'),
            {
                search: search || undefined,
                action: action || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        setAction('');
        router.get(route('admin.audit-logs.index'), {}, { preserveState: true, replace: true });
    };

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

    const { page, pageCount } = paginationMeta(logs);

    const detailBody = detail ? (
        <div className="space-y-4">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-rml-muted">{t.action}</dt>
                    <dd>{detail.action}</dd>
                </div>
                <div>
                    <dt className="text-rml-muted">{t.entity}</dt>
                    <dd>
                        {detail.entity_type}
                        {detail.entity_id != null ? ` #${detail.entity_id}` : ''}
                    </dd>
                </div>
                <div>
                    <dt className="text-rml-muted">{t.user}</dt>
                    <dd>{detail.user?.name ?? common.unknown}</dd>
                </div>
                <div>
                    <dt className="text-rml-muted">{common.date}</dt>
                    <dd>{formatDateTime(detail.created_at, app.locale)}</dd>
                </div>
            </dl>
            <div>
                <h3 className="mb-2 text-sm font-semibold">{t.old_values}</h3>
                <pre className="max-h-48 overflow-auto rounded-lg bg-rml-background p-3 text-xs">
                    {JSON.stringify(detail.old_values ?? {}, null, 2)}
                </pre>
            </div>
            <div>
                <h3 className="mb-2 text-sm font-semibold">{t.new_values}</h3>
                <pre className="max-h-48 overflow-auto rounded-lg bg-rml-background p-3 text-xs">
                    {JSON.stringify(detail.new_values ?? {}, null, 2)}
                </pre>
            </div>
        </div>
    ) : null;

    return (
        <AppLayout title={t.index_title} subtitle={t.index_subtitle}>
            <Head title={t.index_title} />

            <FilterBar
                search={search}
                onSearchChange={setSearch}
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
                    <FormInput
                        label={t.action}
                        name="action"
                        value={action}
                        onChange={(e) => setAction(e.target.value)}
                    />
                </div>
            </FilterBar>

            <MobileFilterDrawer
                open={filtersOpen}
                onClose={() => setFiltersOpen(false)}
                onApply={applyFilters}
                onReset={resetFilters}
            >
                <FormInput
                    label={t.action}
                    name="action"
                    value={action}
                    onChange={(e) => setAction(e.target.value)}
                />
            </MobileFilterDrawer>

            {logs.data.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={logs.data.map((log) => ({
                        id: String(log.id),
                        title: log.action,
                        subtitle: log.user?.name ?? common.unknown,
                        body: (
                            <p className="text-rml-muted">
                                {log.entity_type}
                                {log.entity_id != null ? ` #${log.entity_id}` : ''}
                            </p>
                        ),
                        actions: (
                            <button
                                type="button"
                                className="text-sm font-semibold text-rml-primary"
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
                    getRowId={(r) => String(r.id)}
                    emptyMessage={common.empty}
                    columns={[
                        {
                            id: 'action',
                            header: t.action,
                            cell: (r) => r.action,
                        },
                        {
                            id: 'entity',
                            header: t.entity,
                            cell: (r) =>
                                `${r.entity_type}${r.entity_id != null ? ` #${r.entity_id}` : ''}`,
                        },
                        {
                            id: 'user',
                            header: t.user,
                            cell: (r) => r.user?.name ?? common.unknown,
                        },
                        {
                            id: 'created_at',
                            header: common.date,
                            cell: (r) =>
                                formatDateTime(r.created_at, app.locale),
                        },
                        {
                            id: 'actions',
                            header: common.actions,
                            cell: (r) => (
                                <button
                                    type="button"
                                    className="font-semibold text-rml-primary hover:underline"
                                    onClick={() => openDetail(r)}
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
                onPageChange={(next) =>
                    router.get(
                        route('admin.audit-logs.index'),
                        { ...filters, page: next },
                        { preserveState: true },
                    )
                }
                totalLabel={common.page_of
                    .replace(':page', String(page))
                    .replace(':total', String(pageCount))}
            />

            {isMobile ? (
                <Drawer
                    open={detail != null}
                    onClose={() => setDetail(null)}
                    title={t.show_title}
                    side="bottom"
                >
                    {detailBody}
                </Drawer>
            ) : (
                <Modal
                    open={detail != null}
                    onClose={() => setDetail(null)}
                    title={t.show_title}
                    size="lg"
                    footer={
                        <Button variant="ghost" onClick={() => setDetail(null)}>
                            {common.close}
                        </Button>
                    }
                >
                    {detailBody}
                </Modal>
            )}
        </AppLayout>
    );
}
