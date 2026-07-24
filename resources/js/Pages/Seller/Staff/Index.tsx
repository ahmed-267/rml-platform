import { FormEvent, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FormInput,
    MobileCardList,
    Modal,
    Pagination,
    SortableHeader,
    StatusBadge,
    Tabs,
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

interface StaffMember {
    user_id: number;
    name: string | null;
    email: string | null;
    seller_type: string | null;
    role: string | null;
    approval_status: string | null;
    commission_rate: number | null;
    leads_submitted: number;
}

interface CommissionRow {
    id: number;
    commission_reference: string | null;
    lead_reference: string | null;
    seller_name: string | null;
    seller_user_id: number | null;
    percentage: number | null;
    commission_amount: number | null;
    status: string | null;
    due_at: string | null;
    paid_at: string | null;
}

interface InvitationRow {
    id: number;
    email: string;
    status: string | null;
    expires_at: string | null;
    accepted_at: string | null;
    created_at: string | null;
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

export default function StaffIndex({
    tab,
    staff,
    commissions,
    invitations,
    can_manage_staff,
    can_manage_commissions,
    filters,
}: {
    tab: string;
    staff: StaffMember[];
    commissions: Paginator<CommissionRow>;
    invitations: InvitationRow[];
    can_manage_staff: boolean;
    can_manage_commissions: boolean;
    filters: {
        sort?: string | null;
        direction?: string | null;
        per_page?: number | string | null;
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.seller.staff;
    const payments = translations.seller.payments;
    const common = translations.seller.common;
    const statusLabels = {
        ...translations.statuses,
        ...translations.lead_statuses,
    };
    const isMobile = useIsMobile();

    const activeTab = ['commissions', 'invite'].includes(tab)
        ? tab
        : can_manage_commissions
          ? 'commissions'
          : 'invite';

    const [editingMember, setEditingMember] = useState<StaffMember | null>(
        null,
    );

    const commissionForm = useForm({
        commission_rate: '' as string,
    });

    const inviteForm = useForm({
        email: '',
    });

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(commissions);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        tab: activeTab,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const changeTab = (nextTab: string) => {
        router.get(
            route('seller.staff.index'),
            queryParams({ tab: nextTab, page: 1 }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('seller.staff.index'),
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

    const openEditCommission = (member: StaffMember) => {
        setEditingMember(member);
        commissionForm.setData(
            'commission_rate',
            member.commission_rate != null
                ? String(member.commission_rate)
                : '',
        );
        commissionForm.clearErrors();
    };

    const closeEditCommission = () => {
        setEditingMember(null);
        commissionForm.reset();
        commissionForm.clearErrors();
    };

    const submitCommission = (event: FormEvent) => {
        event.preventDefault();
        if (!editingMember) {
            return;
        }

        commissionForm.patch(
            route('seller.staff.commission.update', editingMember.user_id),
            {
                preserveScroll: true,
                onSuccess: () => closeEditCommission(),
            },
        );
    };

    const submitInvite = (event: FormEvent) => {
        event.preventDefault();
        inviteForm.post(route('seller.staff.invite.store'), {
            onSuccess: () => inviteForm.reset('email'),
        });
    };

    const cancelInvitation = (id: number) => {
        router.delete(route('seller.staff.invitations.destroy', id));
    };

    const pending = invitations.filter((row) => row.status === 'pending');

    const tabItems = [
        ...(can_manage_commissions
            ? [{ id: 'commissions', label: t.tab_commissions }]
            : []),
        ...(can_manage_staff
            ? [{ id: 'invite', label: t.tab_invite_staff }]
            : []),
    ];

    const roleLabel = (role: string | null) => {
        if (!role) {
            return '—';
        }
        return translations.roles?.[role] ?? role;
    };

    const staffTable = (
        <section className="space-y-3">
            <h2 className="text-sm font-semibold text-rml-text">
                {t.staff_roster ?? t.tab_staff_info}
            </h2>
            {staff.length === 0 ? (
                <EmptyState title={t.empty_staff} />
            ) : isMobile ? (
                <MobileCardList
                    emptyMessage={t.empty_staff}
                    items={staff.map((member) => ({
                        id: String(member.user_id),
                        title: member.name ?? member.email ?? '—',
                        subtitle: member.email ?? undefined,
                        meta: member.approval_status ? (
                            <StatusBadge
                                label={leadStatusLabel(
                                    member.approval_status,
                                    statusLabels,
                                )}
                                tone={leadStatusTone(member.approval_status)}
                            />
                        ) : null,
                        body: (
                            <div className="space-y-1 text-rml-muted">
                                <p>
                                    {common.role ?? t.role}:{' '}
                                    {roleLabel(member.role)}
                                </p>
                                <p>
                                    {t.commission_percent ?? t.commission_rate}:{' '}
                                    {member.commission_rate != null
                                        ? `${member.commission_rate}%`
                                        : '—'}
                                </p>
                                <p>
                                    {t.leads_submitted}:{' '}
                                    {member.leads_submitted}
                                </p>
                            </div>
                        ),
                        actions: can_manage_commissions ? (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => openEditCommission(member)}
                            >
                                {t.edit_commission}
                            </Button>
                        ) : null,
                    }))}
                />
            ) : (
                <DataTable
                    data={staff}
                    getRowId={(row) => String(row.user_id)}
                    emptyMessage={t.empty_staff}
                    columns={[
                        {
                            id: 'name',
                            header: t.name ?? t.staff_member,
                            cell: (row) => row.name ?? '—',
                        },
                        {
                            id: 'email',
                            header: translations.seller.profile.email,
                            cell: (row) => row.email ?? '—',
                        },
                        {
                            id: 'role',
                            header: common.role ?? t.role,
                            cell: (row) => roleLabel(row.role),
                        },
                        {
                            id: 'status',
                            header: common.status,
                            cell: (row) =>
                                row.approval_status ? (
                                    <StatusBadge
                                        label={leadStatusLabel(
                                            row.approval_status,
                                            statusLabels,
                                        )}
                                        tone={leadStatusTone(
                                            row.approval_status,
                                        )}
                                    />
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            id: 'rate',
                            header: t.commission_percent ?? t.commission_rate,
                            cell: (row) =>
                                row.commission_rate != null
                                    ? `${row.commission_rate}%`
                                    : '—',
                        },
                        {
                            id: 'leads',
                            header: t.leads_submitted,
                            cell: (row) => row.leads_submitted,
                        },
                        ...(can_manage_commissions
                            ? [
                                  {
                                      id: 'actions',
                                      header: common.actions,
                                      cell: (row: StaffMember) => (
                                          <Button
                                              size="sm"
                                              variant="outline"
                                              onClick={() =>
                                                  openEditCommission(row)
                                              }
                                          >
                                              {t.edit_commission}
                                          </Button>
                                      ),
                                  },
                              ]
                            : []),
                    ]}
                />
            )}
        </section>
    );

    const pageTitle = t.title;
    const pageSubtitle = t.subtitle;

    return (
        <AppLayout title={pageTitle} subtitle={pageSubtitle}>
            <Head title={pageTitle} />

            <Tabs
                items={tabItems}
                value={activeTab}
                onChange={changeTab}
                className="space-y-3"
            >
                {activeTab === 'commissions' && can_manage_commissions && (
                    <div className="space-y-6">
                        {staffTable}

                        <section className="space-y-3">
                            <h2 className="text-sm font-semibold text-rml-text">
                                {t.commission_history ?? t.tab_commissions}
                            </h2>
                        {commissions.data.length === 0 ? (
                            <EmptyState title={t.empty_commissions} />
                        ) : isMobile ? (
                            <MobileCardList
                                emptyMessage={t.empty_commissions}
                                items={commissions.data.map((row) => ({
                                    id: String(row.id),
                                    title: (
                                        <span className="font-mono text-sm">
                                            {row.commission_reference}
                                        </span>
                                    ),
                                    subtitle: row.seller_name ?? undefined,
                                    meta: row.status ? (
                                        <StatusBadge
                                            label={leadStatusLabel(
                                                row.status,
                                                statusLabels,
                                            )}
                                            tone={leadStatusTone(row.status)}
                                        />
                                    ) : null,
                                    body: (
                                        <div className="space-y-1 text-rml-muted">
                                            <p>
                                                {common.lead_id}:{' '}
                                                {row.lead_reference ?? '—'}
                                            </p>
                                            <p>
                                                {payments.amount}:{' '}
                                                {formatMoney(
                                                    row.commission_amount,
                                                )}
                                            </p>
                                        </div>
                                    ),
                                }))}
                            />
                        ) : (
                            <DataTable
                                data={commissions.data}
                                getRowId={(row) => String(row.id)}
                                emptyMessage={t.empty_commissions}
                                columns={[
                                    {
                                        id: 'reference',
                                        header: sortableHeader(
                                            payments.reference,
                                            'reference',
                                        ),
                                        cell: (row) => (
                                            <span className="font-mono text-sm">
                                                {row.commission_reference}
                                            </span>
                                        ),
                                    },
                                    {
                                        id: 'seller',
                                        header: t.staff_member,
                                        cell: (row) =>
                                            row.seller_name ?? '—',
                                    },
                                    {
                                        id: 'lead',
                                        header: common.lead_id,
                                        cell: (row) =>
                                            row.lead_reference ?? '—',
                                    },
                                    {
                                        id: 'amount',
                                        header: sortableHeader(
                                            payments.amount,
                                            'amount',
                                        ),
                                        cell: (row) =>
                                            formatMoney(
                                                row.commission_amount,
                                            ),
                                    },
                                    {
                                        id: 'status',
                                        header: sortableHeader(
                                            common.status,
                                            'status',
                                        ),
                                        cell: (row) =>
                                            row.status ? (
                                                <StatusBadge
                                                    label={leadStatusLabel(
                                                        row.status,
                                                        statusLabels,
                                                    )}
                                                    tone={leadStatusTone(
                                                        row.status,
                                                    )}
                                                />
                                            ) : (
                                                '—'
                                            ),
                                    },
                                    {
                                        id: 'due',
                                        header: sortableHeader(
                                            payments.due_date,
                                            'date',
                                        ),
                                        cell: (row) =>
                                            row.due_at
                                                ? new Date(
                                                      row.due_at,
                                                  ).toLocaleDateString(
                                                      app.locale,
                                                  )
                                                : '—',
                                    },
                                    {
                                        id: 'paid',
                                        header: payments.paid_at,
                                        cell: (row) =>
                                            row.paid_at
                                                ? new Date(
                                                      row.paid_at,
                                                  ).toLocaleDateString(
                                                      app.locale,
                                                  )
                                                : '—',
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
                                    route('seller.staff.index'),
                                    queryParams({
                                        per_page: next,
                                        page: 1,
                                    }),
                                    {
                                        preserveState: true,
                                        replace: true,
                                    },
                                )
                            }
                            onPageChange={(next) =>
                                router.get(
                                    route('seller.staff.index'),
                                    queryParams({ page: next }),
                                    { preserveState: true },
                                )
                            }
                            labels={paginationLabels(common)}
                        />
                        </section>
                    </div>
                )}

                {activeTab === 'invite' && can_manage_staff && (
                    <div className="space-y-4">
                        <section className="rml-card space-y-4 p-4">
                            <form
                                onSubmit={submitInvite}
                                className="flex flex-col gap-3 sm:flex-row sm:items-end"
                            >
                                <div className="min-w-0 flex-1">
                                    <FormInput
                                        label={t.invite_email}
                                        name="email"
                                        type="email"
                                        required
                                        value={inviteForm.data.email}
                                        error={inviteForm.errors.email}
                                        onChange={(e) =>
                                            inviteForm.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={inviteForm.processing}
                                >
                                    {t.invite_send}
                                </Button>
                            </form>
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-base font-semibold text-rml-text">
                                {t.invite_list}
                            </h2>
                            {pending.length === 0 ? (
                                <EmptyState title={t.empty_invites} />
                            ) : isMobile ? (
                                <MobileCardList
                                    emptyMessage={t.empty_invites}
                                    items={pending.map((row) => ({
                                        id: String(row.id),
                                        title: row.email,
                                        meta: row.status ? (
                                            <StatusBadge
                                                label={leadStatusLabel(
                                                    row.status,
                                                    statusLabels,
                                                )}
                                                tone={leadStatusTone(
                                                    row.status,
                                                )}
                                            />
                                        ) : null,
                                        body: (
                                            <p className="text-rml-muted">
                                                {t.expires}:{' '}
                                                {row.expires_at
                                                    ? new Date(
                                                          row.expires_at,
                                                      ).toLocaleString(
                                                          app.locale,
                                                      )
                                                    : '—'}
                                            </p>
                                        ),
                                        actions: (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    cancelInvitation(row.id)
                                                }
                                            >
                                                {t.invite_cancel}
                                            </Button>
                                        ),
                                    }))}
                                />
                            ) : (
                                <DataTable
                                    data={pending}
                                    getRowId={(row) => String(row.id)}
                                    emptyMessage={t.empty_invites}
                                    columns={[
                                        {
                                            id: 'email',
                                            header: t.invite_email,
                                            cell: (row) => row.email,
                                        },
                                        {
                                            id: 'status',
                                            header: common.status,
                                            cell: (row) =>
                                                row.status ? (
                                                    <StatusBadge
                                                        label={leadStatusLabel(
                                                            row.status,
                                                            statusLabels,
                                                        )}
                                                        tone={leadStatusTone(
                                                            row.status,
                                                        )}
                                                    />
                                                ) : (
                                                    '—'
                                                ),
                                        },
                                        {
                                            id: 'expires',
                                            header: t.expires,
                                            cell: (row) =>
                                                row.expires_at
                                                    ? new Date(
                                                          row.expires_at,
                                                      ).toLocaleString(
                                                          app.locale,
                                                      )
                                                    : '—',
                                        },
                                        {
                                            id: 'actions',
                                            header: common.actions,
                                            cell: (row) => (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        cancelInvitation(
                                                            row.id,
                                                        )
                                                    }
                                                >
                                                    {t.invite_cancel}
                                                </Button>
                                            ),
                                        },
                                    ]}
                                />
                            )}
                        </section>
                    </div>
                )}
            </Tabs>

            <Modal
                open={editingMember != null}
                onClose={closeEditCommission}
                title={t.edit_commission}
                description={
                    editingMember
                        ? (editingMember.name ??
                          editingMember.email ??
                          undefined)
                        : undefined
                }
                size="sm"
                footer={
                    <>
                        <Button
                            variant="outline"
                            onClick={closeEditCommission}
                            disabled={commissionForm.processing}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            type="submit"
                            form="edit-staff-commission"
                            disabled={commissionForm.processing}
                        >
                            {common.save}
                        </Button>
                    </>
                }
            >
                <form
                    id="edit-staff-commission"
                    onSubmit={submitCommission}
                    className="space-y-3"
                >
                    <FormInput
                        label={t.commission_rate}
                        name="commission_rate"
                        type="number"
                        min={0}
                        max={100}
                        step="0.01"
                        value={commissionForm.data.commission_rate}
                        error={commissionForm.errors.commission_rate}
                        onChange={(e) =>
                            commissionForm.setData(
                                'commission_rate',
                                e.target.value,
                            )
                        }
                        hint={t.commission_rate_hint}
                    />
                </form>
            </Modal>
        </AppLayout>
    );
}
