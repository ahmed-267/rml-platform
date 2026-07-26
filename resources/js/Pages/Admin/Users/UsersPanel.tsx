import { FormEvent, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { AccountRowActions } from '@/Components/admin/AccountRowActions';
import {
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    MobileFilterDrawer,
    FormInput,
    MobileCardList,
    Modal,
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
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';

interface UserRow {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    locale?: string | null;
    company_name: string | null;
    role: string | null;
    role_label: string | null;
    approval_status: ApprovalStatus;
    created_at: string | null;
}

export default function UsersPanel({
    users,
    filters,
    filterOptions,
}: {
    users: Paginator<UserRow>;
    filters: {
        role?: string | null;
        approval_status?: string | null;
        search?: string | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
    filterOptions: {
        roles: string[];
        assignable_roles: string[];
        creatable_roles?: string[];
        approval_statuses: string[];
    };
}) {
    const { translations, app, auth } = usePage<PageProps>().props;
    const t = translations.admin.users;
    const common = translations.admin.common;
    const statuses = translations.statuses;
    const roles = translations.roles;
    const isMobile = useIsMobile();
    const isSuperAdmin = auth.user?.primary_role === 'super_admin';

    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole] = useState(filters.role ?? '');
    const [approvalStatus, setApprovalStatus] = useState(
        filters.approval_status ?? '',
    );
    const [createOpen, setCreateOpen] = useState(false);
    const [editUser, setEditUser] = useState<UserRow | null>(null);
    const [filtersOpen, setFiltersOpen] = useState(false);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection =
        filters.direction === 'asc' ? 'asc' : 'desc';
    const { page, pageCount, perPage } = paginationMeta(users);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const creatableRoles = (
        filterOptions.creatable_roles ??
        filterOptions.assignable_roles ??
        filterOptions.roles
    ).filter((r) => r !== 'super_admin');

    const createForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        phone: '',
        role: creatableRoles[0] ?? 'admin_staff',
        locale: app.locale,
    });

    const editForm = useForm({
        name: '',
        email: '',
        phone: '',
        locale: app.locale,
        role: '',
        password: '',
        password_confirmation: '',
    });

    const actionLabels = accountActionLabels(common);
    const assignableRoles = (
        filterOptions.assignable_roles ?? filterOptions.roles
    ).filter((r) => r !== 'super_admin' || isSuperAdmin);

    const openEdit = (row: UserRow) => {
        setEditUser(row);
        editForm.setData({
            name: row.name,
            email: row.email,
            phone: row.phone ?? '',
            locale: row.locale ?? app.locale,
            role: row.role ?? assignableRoles[0] ?? '',
            password: '',
            password_confirmation: '',
        });
        editForm.clearErrors();
    };

    const canDeleteUser = (row: UserRow) =>
        isSuperAdmin &&
        row.id !== auth.user?.id &&
        row.role !== 'super_admin';

    const queryParams = (overrides: Record<string, string | number | undefined> = {}) => ({
        tab: 'users',
        search: search || undefined,
        role: role || undefined,
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
    }

    useInstantListFilters(applyFilters, search, [role, approvalStatus]);


    const resetFilters = () => {
        setSearch('');
        setRole('');
        setApprovalStatus('');
        router.get(
            route('admin.users.index'),
            { tab: 'users', sort: 'date', direction: 'desc', per_page: 10 },
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

    const roleLabel = (row: UserRow) =>
        row.role && roles[row.role] ? roles[row.role] : row.role_label ?? '—';

    const rowActions = (row: UserRow) => (
        <AccountRowActions
            status={row.approval_status}
            targetName={row.name}
            companyName={row.company_name}
            viewHref={route('admin.users.show', row.id)}
            onEdit={() => openEdit(row)}
            deleteRoute={route('admin.users.destroy', row.id)}
            canDelete={canDeleteUser(row)}
            approveRoute={route('admin.users.approve', row.id)}
            rejectRoute={route('admin.users.reject', row.id)}
            suspendRoute={route('admin.users.suspend', row.id)}
            reinstateRoute={route('admin.users.reinstate', row.id)}
            labels={actionLabels}
        />
    );

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
                    <>
<Button size="sm" variant="ghost" onClick={resetFilters}>
                            {common.reset}
                        </Button>
                    </>
                }
                endActions={
                    <Button
                        size="sm"
                        className="w-full sm:w-auto"
                        onClick={() => setCreateOpen(true)}
                    >
                        {t.create_title}
                    </Button>
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
                <div className="w-full sm:w-[14rem]">
                    <Select
                        label={common.role}
                        aria-label={common.role}
                        value={role}
                        onChange={(e) => setRole(e.target.value)}
                        options={[
                            { label: common.all_roles, value: '' },
                            ...filterOptions.roles
                                .filter((r) => r !== 'super_admin' || isSuperAdmin)
                                .map((r) => ({
                                    label: roles[r] ?? r,
                                    value: r,
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
                <Select
                    label={common.role}
                    aria-label={common.role}
                    value={role}
                    onChange={(e) => setRole(e.target.value)}
                    options={[
                        { label: common.all_roles, value: '' },
                        ...filterOptions.roles
                            .filter((r) => r !== 'super_admin' || isSuperAdmin)
                            .map((r) => ({
                                label: roles[r] ?? r,
                                value: r,
                            })),
                    ]}
                />
            </MobileFilterDrawer>

            {users.data.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={users.data.map((row) => ({
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
                                <p>{roleLabel(row)}</p>
                            </div>
                        ),
                        actions: rowActions(row),
                    }))}
                />
            ) : (
                <DataTable
                    data={users.data}
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
                            id: 'company',
                            header: sortableHeader(
                                common.company_organisation,
                                'company',
                            ),
                            cell: (r) => r.company_name ?? '—',
                        },
                        {
                            id: 'email',
                            header: sortableHeader(common.email, 'email'),
                            cell: (r) => r.email,
                        },
                        {
                            id: 'role',
                            header: sortableHeader(t.role, 'role'),
                            cell: (row) => roleLabel(row),
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

            <Modal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                title={t.create_title}
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
                        label={t.role}
                        value={createForm.data.role}
                        error={createForm.errors.role}
                        onChange={(e) =>
                            createForm.setData('role', e.target.value)
                        }
                        options={creatableRoles.map((r) => ({
                            label: roles[r] ?? r,
                            value: r,
                        }))}
                    />
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
                <form onSubmit={submitEdit} className="grid gap-3 sm:grid-cols-2">
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
                    <Select
                        label={t.role}
                        value={editForm.data.role}
                        error={editForm.errors.role}
                        onChange={(e) =>
                            editForm.setData('role', e.target.value)
                        }
                        options={assignableRoles.map((r) => ({
                            label: roles[r] ?? r,
                            value: r,
                        }))}
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
