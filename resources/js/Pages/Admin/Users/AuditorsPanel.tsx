import { FormEvent, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { AccountRowActions } from '@/Components/admin/AccountRowActions';
import { AssignAuditorAuditsModal } from '@/Components/admin/AssignAuditorAuditsModal';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    FormInput,
    MobileCardList,
    MobileFilterDrawer,
    Modal,
    Pagination,
    Select,
    SortableHeader,
    StatusBadge,
} from '@/Components/ui';
import type { SortDirection } from '@/Components/ui/SortableHeader';
import {
    accountActionLabels,
    approvalStatusLabel,
    formatDate,
    paginationLabels,
    paginationMeta,
    type Paginator,
} from '@/lib/admin-helpers';
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusTone } from '@/lib/lead-status';
import type { ApprovalStatus, PageProps } from '@/types';

interface AuditorRow {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    locale?: string | null;
    role: string | null;
    role_label: string | null;
    approval_status: ApprovalStatus;
    assigned_audits_count: number;
    in_review_audits_count: number;
    completed_audits_count: number;
    last_active_at: string | null;
    created_at: string | null;
}

type AuditorPermissions = {
    can_create?: boolean;
    can_edit?: boolean;
    can_suspend?: boolean;
    can_delete?: boolean;
    can_assign?: boolean;
};

export default function AuditorsPanel({
    auditors,
    filters,
    filterOptions,
    permissions = {},
}: {
    auditors: Paginator<AuditorRow>;
    filters: {
        approval_status?: string | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        approval_statuses: string[];
    };
    permissions?: AuditorPermissions;
}) {
    const { translations, app, auth } = usePage<PageProps>().props;
    const t = translations.admin.users;
    const common = translations.admin.common;
    const statuses = translations.statuses;
    const isMobile = useIsMobile();

    const canCreate = Boolean(permissions.can_create);
    const canEdit = Boolean(permissions.can_edit);
    const canSuspend = Boolean(permissions.can_suspend);
    const canDelete = Boolean(permissions.can_delete);
    const canAssign = Boolean(permissions.can_assign);

    const [search, setSearch] = useState(filters.search ?? '');
    const [approvalStatus, setApprovalStatus] = useState(
        filters.approval_status ?? '',
    );
    const [createOpen, setCreateOpen] = useState(false);
    const [editUser, setEditUser] = useState<AuditorRow | null>(null);
    const [assignAuditor, setAssignAuditor] = useState<AuditorRow | null>(null);
    const [filtersOpen, setFiltersOpen] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection =
        filters.direction === 'asc' ? 'asc' : 'desc';
    const { page, pageCount, perPage } = paginationMeta(auditors);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const createForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        phone: '',
        role: 'internal_auditor',
        locale: app.locale,
        approval_status: 'approved',
    });

    const editForm = useForm({
        name: '',
        email: '',
        phone: '',
        locale: app.locale,
        role: 'internal_auditor',
        password: '',
        password_confirmation: '',
    });

    const actionLabels = {
        ...accountActionLabels(common),
        assign: t.assign_audit ?? 'Assign audit',
    };

    const openEdit = (row: AuditorRow) => {
        setEditUser(row);
        editForm.setData({
            name: row.name,
            email: row.email,
            phone: row.phone ?? '',
            locale: row.locale ?? app.locale,
            role: 'internal_auditor',
            password: '',
            password_confirmation: '',
        });
        editForm.clearErrors();
    };

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        tab: 'auditors',
        search: search || undefined,
        approval_status: approvalStatus || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = () => {
        router.get(route('admin.users.index'), queryParams({ page: 1 }), {
            preserveState: true,
            replace: true,
        });
    };

    useInstantListFilters(applyFilters, search, [approvalStatus]);

    const resetFilters = () => {
        setSearch('');
        setApprovalStatus('');
        router.get(
            route('admin.users.index'),
            { tab: 'auditors', sort: 'date', direction: 'desc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('admin.users.index'),
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

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route('admin.users.store'), {
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
                createForm.setData('role', 'internal_auditor');
                createForm.setData('approval_status', 'approved');
                createForm.setData('locale', app.locale);
            },
        });
    };

    const submitEdit = (event: FormEvent) => {
        event.preventDefault();
        if (!editUser) {
            return;
        }

        editForm.put(route('admin.users.update', editUser.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditUser(null);
                editForm.reset();
            },
        });
    };

    const rowActions = (row: AuditorRow) => (
        <AccountRowActions
            status={row.approval_status}
            targetName={row.name}
            viewHref={route('admin.users.show', row.id)}
            onEdit={canEdit ? () => openEdit(row) : undefined}
            onAssign={canAssign ? () => setAssignAuditor(row) : undefined}
            canAssign={canAssign && row.approval_status === 'approved'}
            deleteRoute={route('admin.users.destroy', row.id)}
            canDelete={
                canDelete &&
                row.id !== auth.user?.id &&
                row.role !== 'super_admin'
            }
            canManage={canSuspend}
            approveRoute={route('admin.users.approve', row.id)}
            rejectRoute={route('admin.users.reject', row.id)}
            suspendRoute={route('admin.users.suspend', row.id)}
            reinstateRoute={route('admin.users.reinstate', row.id)}
            labels={actionLabels}
        />
    );

    const statusOptions = [
        { label: common.all_statuses, value: '' },
        ...filterOptions.approval_statuses.map((status) => ({
            label: approvalStatusLabel(status, statuses),
            value: status,
        })),
    ];

    return (
        <>
            <FilterBar
                compact
                search={search}
                onSearchChange={setSearch}
                searchLabel={common.search}
                searchPlaceholder={t.search_placeholder}
                onOpenMobileFilters={() => setFiltersOpen(true)}
                actions={
                    <Button size="sm" variant="ghost" onClick={resetFilters}>
                        {common.reset}
                    </Button>
                }
                endActions={
                    canCreate ? (
                        <Button
                            size="sm"
                            className="w-full sm:w-auto"
                            onClick={() => setCreateOpen(true)}
                        >
                            {t.create_auditor ?? t.create_title}
                        </Button>
                    ) : null
                }
            >
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={common.status}
                        aria-label={common.status}
                        value={approvalStatus}
                        onChange={(e) => setApprovalStatus(e.target.value)}
                        options={statusOptions}
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
                    options={statusOptions}
                />
            </MobileFilterDrawer>

            {auditors.data.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={auditors.data.map((row) => ({
                        id: String(row.id),
                        title: row.name,
                        subtitle: row.email,
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
                                <p>
                                    {t.col_assigned_audits ?? 'Assigned'}:{' '}
                                    {row.assigned_audits_count}
                                </p>
                                <p>
                                    {t.col_in_review_audits ?? 'In review'}:{' '}
                                    {row.in_review_audits_count}
                                </p>
                                <p>
                                    {t.col_completed_audits ?? 'Completed'}:{' '}
                                    {row.completed_audits_count}
                                </p>
                            </div>
                        ),
                        actions: rowActions(row),
                    }))}
                />
            ) : (
                <DataTable
                    data={auditors.data}
                    getRowId={(row) => String(row.id)}
                    emptyMessage={common.empty}
                    columns={[
                        {
                            id: 'name',
                            header: sortableHeader(common.name, 'name'),
                            cell: (r) => (
                                <span className="font-medium">{r.name}</span>
                            ),
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
                            id: 'assigned',
                            header: t.col_assigned_audits ?? 'Assigned audits',
                            cell: (r) => r.assigned_audits_count,
                        },
                        {
                            id: 'in_review',
                            header: t.col_in_review_audits ?? 'In-review audits',
                            cell: (r) => r.in_review_audits_count,
                        },
                        {
                            id: 'completed',
                            header: t.col_completed_audits ?? 'Completed audits',
                            cell: (r) => r.completed_audits_count,
                        },
                        {
                            id: 'last_active',
                            header: t.col_last_active ?? 'Last active',
                            cell: (row) =>
                                formatDate(row.last_active_at, app.locale),
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
                        route('admin.users.index'),
                        queryParams({ per_page: next, page: 1 }),
                        { preserveState: true, replace: true },
                    )
                }
                onPageChange={(next) =>
                    router.get(
                        route('admin.users.index'),
                        queryParams({ page: next }),
                        { preserveState: true },
                    )
                }
                labels={paginationLabels(common)}
            />

            <AssignAuditorAuditsModal
                open={assignAuditor != null}
                onClose={() => setAssignAuditor(null)}
                auditor={assignAuditor}
            />

            <Modal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                title={t.create_auditor ?? t.create_title}
                size="md"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            onClick={() => setCreateOpen(false)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            onClick={submitCreate}
                            disabled={createForm.processing}
                        >
                            {common.create}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitCreate} className="space-y-4">
                    <FormInput
                        label={common.name}
                        value={createForm.data.name}
                        error={createForm.errors.name}
                        required
                        onChange={(e) =>
                            createForm.setData('name', e.target.value)
                        }
                    />
                    <FormInput
                        label={common.email}
                        type="email"
                        value={createForm.data.email}
                        error={createForm.errors.email}
                        required
                        onChange={(e) =>
                            createForm.setData('email', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.password}
                        type="password"
                        value={createForm.data.password}
                        error={createForm.errors.password}
                        required
                        onChange={(e) =>
                            createForm.setData('password', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.password_confirm}
                        type="password"
                        value={createForm.data.password_confirmation}
                        error={createForm.errors.password_confirmation}
                        required
                        onChange={(e) =>
                            createForm.setData(
                                'password_confirmation',
                                e.target.value,
                            )
                        }
                    />
                    <Select
                        label={common.status}
                        value={createForm.data.approval_status}
                        error={createForm.errors.approval_status}
                        onChange={(e) =>
                            createForm.setData(
                                'approval_status',
                                e.target.value,
                            )
                        }
                        options={[
                            {
                                label: approvalStatusLabel('approved', statuses),
                                value: 'approved',
                            },
                            {
                                label: approvalStatusLabel('pending', statuses),
                                value: 'pending',
                            },
                        ]}
                    />
                    <p className="text-xs text-rml-muted">
                        {t.auditor_role_locked ??
                            'Role is fixed to Internal Auditor. Super Admin cannot be created here.'}
                    </p>
                </form>
            </Modal>

            <Modal
                open={editUser != null}
                onClose={() => setEditUser(null)}
                title={common.edit_details}
                size="md"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            onClick={() => setEditUser(null)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            onClick={submitEdit}
                            disabled={editForm.processing}
                        >
                            {common.save}
                        </Button>
                    </>
                }
            >
                <form
                    onSubmit={submitEdit}
                    className="grid gap-3 sm:grid-cols-2"
                >
                    <FormInput
                        label={common.name}
                        value={editForm.data.name}
                        error={editForm.errors.name}
                        required
                        onChange={(e) =>
                            editForm.setData('name', e.target.value)
                        }
                    />
                    <FormInput
                        label={common.email}
                        type="email"
                        value={editForm.data.email}
                        error={editForm.errors.email}
                        required
                        onChange={(e) =>
                            editForm.setData('email', e.target.value)
                        }
                    />
                    <FormInput
                        label={common.phone}
                        value={editForm.data.phone}
                        error={editForm.errors.phone}
                        onChange={(e) =>
                            editForm.setData('phone', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.password}
                        type="password"
                        value={editForm.data.password}
                        error={editForm.errors.password}
                        onChange={(e) =>
                            editForm.setData('password', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.password_confirm}
                        type="password"
                        value={editForm.data.password_confirmation}
                        error={editForm.errors.password_confirmation}
                        onChange={(e) =>
                            editForm.setData(
                                'password_confirmation',
                                e.target.value,
                            )
                        }
                    />
                </form>
            </Modal>
        </>
    );
}
