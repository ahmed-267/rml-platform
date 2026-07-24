import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { AccountRowActions } from '@/Components/admin/AccountRowActions';
import { BackLink } from '@/Components/admin/BackLink';
import { ProfileCard, ProfileField } from '@/Components/admin/ProfileCard';
import {
    Button,
    FormInput,
    Modal,
    Select,
    StatusBadge,
} from '@/Components/ui';
import {
    accountActionLabels,
    approvalStatusLabel,
    formatDateTime,
} from '@/lib/admin-helpers';
import { leadStatusTone } from '@/lib/lead-status';
import type { ApprovalStatus, PageProps } from '@/types';

interface UserDetail {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    locale: string;
    company_name: string | null;
    role: string | null;
    role_label: string | null;
    roles: string[];
    portal?: string | null;
    approval_status: ApprovalStatus;
    approved_at: string | null;
    created_at: string | null;
}

export default function UsersShow({
    user,
    assignable_roles,
    activity,
    related_summary,
}: {
    user: UserDetail;
    assignable_roles: string[];
    activity: Array<{
        id: number;
        action: string;
        actor: string | null;
        created_at: string | null;
    }>;
    related_summary: Record<string, number>;
}) {
    const { translations, app, auth } = usePage<PageProps>().props;
    const t = translations.admin.users;
    const common = translations.admin.common;
    const statuses = translations.statuses;
    const roles = translations.roles;
    const isSuperAdmin = auth.user?.primary_role === 'super_admin';
    const canEditSuperAdmin =
        !user.roles.includes('super_admin') || isSuperAdmin;
    const [editOpen, setEditOpen] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        phone: user.phone ?? '',
        locale: user.locale,
        role: user.role ?? assignable_roles[0] ?? '',
        password: '',
        password_confirmation: '',
    });

    const roleLabel =
        user.role && roles[user.role] ? roles[user.role] : user.role_label ?? '—';

    const roleOptions = assignable_roles.map((r) => ({
        label: roles[r] ?? r,
        value: r,
    }));

    const portalLabel =
        user.portal === 'admin'
            ? common.portal_admin
            : user.portal === 'auditor'
              ? common.portal_auditor
              : user.portal === 'seller'
                ? common.portal_seller
                : user.portal === 'buyer'
                  ? common.portal_buyer
                  : user.portal ?? '—';

    const save = () => {
        put(route('admin.users.update', user.id), {
            preserveScroll: true,
            onSuccess: () => setEditOpen(false),
        });
    };

    return (
        <AppLayout title={t.show_title} subtitle={user.name}>
            <Head title={t.show_title} />

            <div className="flex items-start gap-3">
                <BackLink
                    href={route('admin.users.index')}
                    label={common.back}
                    className="mt-0.5"
                />
                <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <h1 className="text-xl font-semibold text-rml-text">
                            {user.name}
                        </h1>
                        <p className="text-sm text-rml-muted">
                            {user.company_name ?? '—'}
                        </p>
                        <div className="flex flex-wrap items-center gap-2 pt-1">
                            <StatusBadge label={roleLabel} tone="neutral" />
                            <StatusBadge
                                label={approvalStatusLabel(
                                    user.approval_status,
                                    statuses,
                                )}
                                tone={leadStatusTone(user.approval_status)}
                            />
                        </div>
                    </div>
                    {canEditSuperAdmin && (
                        <AccountRowActions
                            status={user.approval_status}
                            targetName={user.name}
                            companyName={user.company_name}
                            showView={false}
                            variant="buttons"
                            approveRoute={route('admin.users.approve', user.id)}
                            rejectRoute={route('admin.users.reject', user.id)}
                            suspendRoute={route('admin.users.suspend', user.id)}
                            reinstateRoute={route(
                                'admin.users.reinstate',
                                user.id,
                            )}
                            labels={accountActionLabels(common)}
                        />
                    )}
                </div>
            </div>

            <div className="grid gap-3 lg:grid-cols-2">
                <ProfileCard
                    title={common.personal_details}
                    actions={
                        canEditSuperAdmin ? (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setEditOpen(true)}
                            >
                                {common.edit_details}
                            </Button>
                        ) : undefined
                    }
                >
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField label={common.name} value={user.name} />
                        <ProfileField label={common.email} value={user.email} />
                        <ProfileField
                            label={common.phone}
                            value={user.phone ?? '—'}
                        />
                        <ProfileField label={common.role} value={roleLabel} />
                        <ProfileField
                            label={common.status}
                            value={approvalStatusLabel(
                                user.approval_status,
                                statuses,
                            )}
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={common.organisation_access}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField
                            label={common.company_organisation}
                            value={user.company_name ?? '—'}
                        />
                        <ProfileField
                            label={common.portal_access}
                            value={portalLabel}
                        />
                        <ProfileField label={common.role} value={roleLabel} />
                        <ProfileField
                            label={common.status}
                            value={approvalStatusLabel(
                                user.approval_status,
                                statuses,
                            )}
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={common.account_status}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField
                            label={common.joined_date}
                            value={formatDateTime(user.created_at, app.locale)}
                        />
                        <ProfileField
                            label={statuses.approved}
                            value={formatDateTime(user.approved_at, app.locale)}
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={common.related_records}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        {'leads_submitted' in related_summary && (
                            <>
                                <ProfileField
                                    label={
                                        translations.admin.sellers.leads_submitted
                                    }
                                    value={String(
                                        related_summary.leads_submitted ?? 0,
                                    )}
                                />
                                <ProfileField
                                    label={
                                        translations.admin.sellers.leads_sold
                                    }
                                    value={String(
                                        related_summary.leads_sold ?? 0,
                                    )}
                                />
                            </>
                        )}
                        {'purchases' in related_summary && (
                            <ProfileField
                                label={translations.admin.buyers.purchases}
                                value={String(related_summary.purchases ?? 0)}
                            />
                        )}
                        {'activity_count' in related_summary && (
                            <ProfileField
                                label={common.activity}
                                value={String(
                                    related_summary.activity_count ?? 0,
                                )}
                            />
                        )}
                    </dl>
                </ProfileCard>

                <ProfileCard title={common.activity} className="lg:col-span-2">
                    {activity.length === 0 ? (
                        <p className="text-sm text-rml-muted">{common.empty}</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {activity.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex items-start justify-between gap-3 border-b border-rml-border/70 pb-2 last:border-0"
                                >
                                    <div>
                                        <p className="font-medium text-rml-text">
                                            {item.action}
                                        </p>
                                        <p className="text-xs text-rml-muted">
                                            {item.actor ?? common.unknown}
                                        </p>
                                    </div>
                                    <span className="shrink-0 text-xs text-rml-muted">
                                        {formatDateTime(
                                            item.created_at,
                                            app.locale,
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </ProfileCard>
            </div>

            <Modal
                open={editOpen}
                onClose={() => setEditOpen(false)}
                title={common.edit_details}
                size="md"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditOpen(false)}
                        >
                            {common.cancel}
                        </Button>
                        <Button size="sm" onClick={save} disabled={processing}>
                            {common.save}
                        </Button>
                    </>
                }
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <FormInput
                        label={common.name}
                        value={data.name}
                        error={errors.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    <FormInput
                        label={common.email}
                        type="email"
                        value={data.email}
                        error={errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <FormInput
                        label={common.phone}
                        value={data.phone}
                        error={errors.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                    <Select
                        label={t.role}
                        value={data.role}
                        error={errors.role}
                        onChange={(e) => setData('role', e.target.value)}
                        options={roleOptions}
                    />
                    <FormInput
                        label={t.password}
                        type="password"
                        value={data.password}
                        error={errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <FormInput
                        label={t.password_confirm}
                        type="password"
                        value={data.password_confirmation}
                        error={errors.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                </div>
            </Modal>
        </AppLayout>
    );
}
