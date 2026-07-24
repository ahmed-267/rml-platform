import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { AccountRowActions } from '@/Components/admin/AccountRowActions';
import { BackLink } from '@/Components/admin/BackLink';
import { ProfileCard, ProfileField } from '@/Components/admin/ProfileCard';
import {
    Button,
    DataTable,
    FormInput,
    Modal,
    StatusBadge,
} from '@/Components/ui';
import {
    accountActionLabels,
    approvalStatusLabel,
    formatDate,
    formatMoney,
} from '@/lib/admin-helpers';
import { leadStatusTone } from '@/lib/lead-status';
import type { ApprovalStatus, PageProps } from '@/types';

interface BuyerDetail {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    locale: string;
    approval_status: ApprovalStatus;
    company: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        city: string | null;
        postcode: string | null;
        country: string | null;
        notes: string | null;
        approval_status?: string | null;
    } | null;
    approved_at: string | null;
    created_at: string | null;
}

export default function BuyersShow({
    buyer,
    purchase_summary,
    purchases,
    payments,
    lead_access_summary,
}: {
    buyer: BuyerDetail;
    purchase_summary: {
        purchases: number;
        paid_purchases: number;
        pending_payments: number;
        total_spend: number;
    };
    purchases: Array<{
        id: number;
        purchase_reference: string;
        status: string | null;
        total_amount: number | null;
        purchased_at: string | null;
    }>;
    payments: Array<{
        id: number;
        payment_reference: string;
        status: string | null;
        amount: number | null;
        paid_at: string | null;
    }>;
    lead_access_summary: {
        paid_purchases: number;
        pending_release: number;
        released_leads: number;
        pending_payments: number;
    };
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.buyers;
    const common = translations.admin.common;
    const statuses = translations.statuses;
    const roles = translations.roles;
    const purchaseStatuses = translations.purchase_statuses;
    const paymentStatuses = translations.payment_statuses;
    const [editOpen, setEditOpen] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        name: buyer.name,
        email: buyer.email,
        phone: buyer.phone ?? '',
        company_name: buyer.company?.name ?? '',
        company_email: buyer.company?.email ?? '',
        company_phone: buyer.company?.phone ?? '',
    });

    const roleLabel = roles.buyer_admin ?? 'Buyer';

    const save = () => {
        put(route('admin.buyers.update', buyer.id), {
            preserveScroll: true,
            onSuccess: () => setEditOpen(false),
        });
    };

    return (
        <AppLayout title={t.show_title} subtitle={buyer.name}>
            <Head title={t.show_title} />

            <div className="flex items-start gap-3">
                <BackLink
                    href={route('admin.buyers.index')}
                    label={common.back}
                    className="mt-0.5"
                />
                <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <h1 className="text-xl font-semibold text-rml-text">
                            {buyer.name}
                        </h1>
                        {buyer.company?.name && (
                            <p className="text-sm text-rml-muted">
                                {buyer.company.name}
                            </p>
                        )}
                        <div className="flex flex-wrap items-center gap-2 pt-1">
                            <StatusBadge label={roleLabel} tone="neutral" />
                            <StatusBadge
                                label={approvalStatusLabel(
                                    buyer.approval_status,
                                    statuses,
                                )}
                                tone={leadStatusTone(buyer.approval_status)}
                            />
                        </div>
                    </div>
                    <AccountRowActions
                        status={buyer.approval_status}
                        targetName={buyer.name}
                        companyName={buyer.company?.name}
                        showView={false}
                        variant="buttons"
                        approveRoute={route('admin.buyers.approve', buyer.id)}
                        rejectRoute={route('admin.buyers.reject', buyer.id)}
                        suspendRoute={route('admin.buyers.suspend', buyer.id)}
                        reinstateRoute={route(
                            'admin.buyers.reinstate',
                            buyer.id,
                        )}
                        labels={accountActionLabels(common)}
                    />
                </div>
            </div>

            <div className="grid items-start gap-3 lg:grid-cols-2">
                <ProfileCard
                    title={common.personal_details}
                    actions={
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setEditOpen(true)}
                        >
                            {common.edit_details}
                        </Button>
                    }
                >
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField label={common.name} value={buyer.name} />
                        <ProfileField label={common.email} value={buyer.email} />
                        <ProfileField
                            label={common.phone}
                            value={buyer.phone ?? '—'}
                        />
                        <ProfileField label={common.role} value={roleLabel} />
                        <ProfileField
                            label={common.status}
                            value={approvalStatusLabel(
                                buyer.approval_status,
                                statuses,
                            )}
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={common.company_details}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField
                            label={common.company}
                            value={buyer.company?.name ?? '—'}
                        />
                        <ProfileField
                            label={common.joined_date}
                            value={formatDate(buyer.created_at, app.locale)}
                        />
                        <ProfileField
                            label={common.status}
                            value={
                                buyer.company?.approval_status
                                    ? approvalStatusLabel(
                                          buyer.company.approval_status,
                                          statuses,
                                      )
                                    : '—'
                            }
                        />
                        <ProfileField
                            label={common.email}
                            value={buyer.company?.email ?? '—'}
                        />
                        <ProfileField
                            label={common.phone}
                            value={buyer.company?.phone ?? '—'}
                        />
                        <ProfileField
                            label={common.city}
                            value={buyer.company?.city ?? '—'}
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={common.purchase_summary}>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div>
                            <p className="text-lg font-semibold leading-tight">
                                {purchase_summary.purchases}
                            </p>
                            <p className="text-xs text-rml-muted">
                                {t.purchases}
                            </p>
                        </div>
                        <div>
                            <p className="text-lg font-semibold leading-tight">
                                {purchase_summary.paid_purchases}
                            </p>
                            <p className="text-xs text-rml-muted">
                                {common.paid_purchases}
                            </p>
                        </div>
                        <div>
                            <p className="text-lg font-semibold leading-tight">
                                {purchase_summary.pending_payments}
                            </p>
                            <p className="text-xs text-rml-muted">
                                {common.pending_payments}
                            </p>
                        </div>
                        <div>
                            <p className="text-lg font-semibold leading-tight">
                                {formatMoney(purchase_summary.total_spend)}
                            </p>
                            <p className="text-xs text-rml-muted">
                                {common.total_spend}
                            </p>
                        </div>
                    </div>
                </ProfileCard>

                <ProfileCard title={common.recent_purchases}>
                    {purchases.length === 0 ? (
                        <p className="text-sm text-rml-muted">{common.empty}</p>
                    ) : (
                        <DataTable
                            data={purchases}
                            getRowId={(r) => String(r.id)}
                            columns={[
                                {
                                    id: 'ref',
                                    header: common.reference,
                                    cell: (r) => r.purchase_reference,
                                },
                                {
                                    id: 'status',
                                    header: common.status,
                                    cell: (r) =>
                                        r.status
                                            ? (purchaseStatuses[r.status] ??
                                              r.status)
                                            : '—',
                                },
                                {
                                    id: 'amount',
                                    header: common.amount,
                                    cell: (r) => formatMoney(r.total_amount),
                                },
                                {
                                    id: 'date',
                                    header: common.date,
                                    cell: (r) =>
                                        formatDate(r.purchased_at, app.locale),
                                },
                            ]}
                        />
                    )}
                </ProfileCard>

                <ProfileCard title={t.payments}>
                    {payments.length === 0 ? (
                        <p className="text-sm text-rml-muted">{common.empty}</p>
                    ) : (
                        <DataTable
                            data={payments}
                            getRowId={(r) => String(r.id)}
                            columns={[
                                {
                                    id: 'ref',
                                    header: common.reference,
                                    cell: (r) => r.payment_reference,
                                },
                                {
                                    id: 'status',
                                    header: common.status,
                                    cell: (r) =>
                                        r.status
                                            ? (paymentStatuses[r.status] ??
                                              r.status)
                                            : '—',
                                },
                                {
                                    id: 'amount',
                                    header: common.amount,
                                    cell: (r) => formatMoney(r.amount),
                                },
                            ]}
                        />
                    )}
                </ProfileCard>

                <ProfileCard title={common.lead_access_summary}>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <p className="text-lg font-semibold leading-tight">
                                {lead_access_summary.paid_purchases}
                            </p>
                            <p className="text-xs text-rml-muted">
                                {common.paid_purchases}
                            </p>
                        </div>
                        <div>
                            <p className="text-lg font-semibold leading-tight">
                                {lead_access_summary.pending_release}
                            </p>
                            <p className="text-xs text-rml-muted">
                                {common.pending_release}
                            </p>
                        </div>
                        <div>
                            <p className="text-lg font-semibold leading-tight">
                                {lead_access_summary.released_leads}
                            </p>
                            <p className="text-xs text-rml-muted">
                                {common.released_leads}
                            </p>
                        </div>
                        <div>
                            <p className="text-lg font-semibold leading-tight">
                                {lead_access_summary.pending_payments}
                            </p>
                            <p className="text-xs text-rml-muted">
                                {common.pending_payments}
                            </p>
                        </div>
                    </div>
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
                    <FormInput
                        label={common.company}
                        value={data.company_name}
                        error={errors.company_name}
                        onChange={(e) =>
                            setData('company_name', e.target.value)
                        }
                    />
                </div>
            </Modal>
        </AppLayout>
    );
}
