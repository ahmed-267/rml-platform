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
    StatusBadge,
    TableActionButton,
    Tabs,
    tableActionIcons,
} from '@/Components/ui';
import { useIsMobile } from '@/hooks/use-media-query';
import { useClientTableSort } from '@/hooks/use-list-sort';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
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

interface InvitationRow {
    id: number;
    email: string;
    status: string | null;
    expires_at: string | null;
    accepted_at: string | null;
    created_at: string | null;
}

export default function StaffIndex({
    tab,
    staff,
    invitations,
    can_manage_staff,
    can_manage_commissions,
}: {
    tab: string;
    staff: StaffMember[];
    invitations: InvitationRow[];
    can_manage_staff: boolean;
    can_manage_commissions: boolean;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.seller.staff;
    const common = translations.seller.common;
    const statusLabels = {
        ...translations.statuses,
        ...translations.lead_statuses,
    };
    const isMobile = useIsMobile();

    const activeTab =
        tab === 'invite' && can_manage_staff ? 'invite' : 'staff';

    const [editingMember, setEditingMember] = useState<StaffMember | null>(
        null,
    );

    const commissionForm = useForm({
        commission_rate: '' as string,
    });

    const inviteForm = useForm({
        email: '',
    });

    const changeTab = (nextTab: string) => {
        router.get(
            route('seller.staff.index'),
            { tab: nextTab },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            },
        );
    };

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

    const roleLabel = (role: string | null) => {
        if (!role) {
            return '—';
        }
        return translations.roles?.[role] ?? role;
    };

    const { sortedRows: sortedStaff, sortableHeader: staffSortableHeader } =
        useClientTableSort(staff, {
            defaultSort: 'name',
            defaultDirection: 'asc',
            sortAscLabel: common.sort_asc,
            sortDescLabel: common.sort_desc,
            accessors: {
                name: (row) => row.name ?? row.email ?? '',
                email: (row) => row.email ?? '',
                role: (row) => roleLabel(row.role),
                status: (row) => row.approval_status ?? '',
                rate: (row) => row.commission_rate ?? -1,
                leads: (row) => row.leads_submitted,
            },
        });

    const {
        sortedRows: sortedInvitations,
        sortableHeader: invitationSortableHeader,
    } = useClientTableSort(pending, {
        defaultSort: 'email',
        defaultDirection: 'asc',
        sortAscLabel: common.sort_asc,
        sortDescLabel: common.sort_desc,
        accessors: {
            email: (row) => row.email,
            status: (row) => row.status ?? '',
            expires: (row) => row.expires_at ?? '',
        },
    });

    const tabItems = [
        { id: 'staff', label: t.staff_roster ?? t.tab_staff_info },
        ...(can_manage_staff
            ? [{ id: 'invite', label: t.tab_invite_staff }]
            : []),
    ];

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
                    data={sortedStaff}
                    getRowId={(row) => String(row.user_id)}
                    emptyMessage={t.empty_staff}
                    columns={[
                        {
                            id: 'name',
                            header: staffSortableHeader(
                                t.name ?? t.staff_member,
                                'name',
                            ),
                            cell: (row) => row.name ?? '—',
                        },
                        {
                            id: 'email',
                            header: staffSortableHeader(
                                translations.seller.profile.email,
                                'email',
                            ),
                            cell: (row) => row.email ?? '—',
                        },
                        {
                            id: 'role',
                            header: staffSortableHeader(
                                common.role ?? t.role,
                                'role',
                            ),
                            cell: (row) => roleLabel(row.role),
                        },
                        {
                            id: 'status',
                            header: staffSortableHeader(common.status, 'status'),
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
                            header: staffSortableHeader(
                                t.commission_percent ?? t.commission_rate,
                                'rate',
                            ),
                            cell: (row) =>
                                row.commission_rate != null
                                    ? `${row.commission_rate}%`
                                    : '—',
                        },
                        {
                            id: 'leads',
                            header: staffSortableHeader(
                                t.leads_submitted,
                                'leads',
                            ),
                            cell: (row) => row.leads_submitted,
                        },
                        ...(can_manage_commissions
                            ? [
                                  {
                                      id: 'actions',
                                      header: common.actions,
                                      cell: (row: StaffMember) => (
                                          <TableActionButton
                                              label={t.edit_commission}
                                              icon={tableActionIcons.edit}
                                              onClick={() =>
                                                  openEditCommission(row)
                                              }
                                          />
                                      ),
                                  },
                              ]
                            : []),
                    ]}
                />
            )}
        </section>
    );

    const inviteSection = (
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
                                inviteForm.setData('email', e.target.value)
                            }
                        />
                    </div>
                    <Button type="submit" disabled={inviteForm.processing}>
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
                                    tone={leadStatusTone(row.status)}
                                />
                            ) : null,
                            body: (
                                <p className="text-rml-muted">
                                    {t.expires}:{' '}
                                    {row.expires_at
                                        ? new Date(
                                              row.expires_at,
                                          ).toLocaleString(app.locale)
                                        : '—'}
                                </p>
                            ),
                            actions: (
                                <TableActionButton
                                    label={t.invite_cancel}
                                    icon={tableActionIcons.cancel}
                                    tone="danger"
                                    onClick={() => cancelInvitation(row.id)}
                                />
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={sortedInvitations}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.empty_invites}
                        columns={[
                            {
                                id: 'email',
                                header: invitationSortableHeader(
                                    t.invite_email,
                                    'email',
                                ),
                                cell: (row) => row.email,
                            },
                            {
                                id: 'status',
                                header: invitationSortableHeader(
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
                                            tone={leadStatusTone(row.status)}
                                        />
                                    ) : (
                                        '—'
                                    ),
                            },
                            {
                                id: 'expires',
                                header: invitationSortableHeader(
                                    t.expires,
                                    'expires',
                                ),
                                cell: (row) =>
                                    row.expires_at
                                        ? new Date(
                                              row.expires_at,
                                          ).toLocaleString(app.locale)
                                        : '—',
                            },
                            {
                                id: 'actions',
                                header: common.actions,
                                cell: (row) => (
                                    <TableActionButton
                                        label={t.invite_cancel}
                                        icon={tableActionIcons.cancel}
                                        tone="danger"
                                        onClick={() =>
                                            cancelInvitation(row.id)
                                        }
                                    />
                                ),
                            },
                        ]}
                    />
                )}
            </section>
        </div>
    );

    const pageTitle = t.title;
    const pageSubtitle = t.subtitle;

    return (
        <AppLayout title={pageTitle} subtitle={pageSubtitle}>
            <Head title={pageTitle} />

            {tabItems.length > 1 ? (
                <Tabs
                    items={tabItems}
                    value={activeTab}
                    onChange={changeTab}
                    className="space-y-3"
                >
                    {activeTab === 'staff' && staffTable}
                    {activeTab === 'invite' && inviteSection}
                </Tabs>
            ) : (
                staffTable
            )}

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
