import { useState, type ReactNode } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { AccountRowActions } from '@/Components/admin/AccountRowActions';
import { BackLink } from '@/Components/admin/BackLink';
import { LocationCard } from '@/Components/admin/LocationCard';
import { ProfileCard, ProfileField } from '@/Components/admin/ProfileCard';
import {
    Button,
    DataTable,
    FormInput,
    Modal,
    StatusBadge,
} from '@/Components/ui';
import { useIsDesktop } from '@/hooks/use-media-query';
import {
    accountActionLabels,
    approvalStatusLabel,
    formatDate,
    formatDateTime,
    formatMoney,
} from '@/lib/admin-helpers';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { ApprovalStatus, PageProps } from '@/types';

interface SellerDetail {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    locale: string;
    approval_status: ApprovalStatus;
    role: string | null;
    role_label: string | null;
    seller_type: string | null;
    commission_rate: number | null;
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
        latitude?: number | null;
        longitude?: number | null;
        formatted_address?: string | null;
        geocoding_status?: string | null;
        geocoded_at?: string | null;
        geocoding_error?: string | null;
    } | null;
    approved_at: string | null;
    rejection_reason?: string | null;
    rejection_comment?: string | null;
    rejected_at?: string | null;
    rejected_by?: { id: number; name: string } | null;
    created_at: string | null;
}

export default function SellersShow({
    seller,
    staff,
    lead_stats,
    recent_leads,
    commissions,
    payouts,
    activity,
}: {
    seller: SellerDetail;
    staff: Array<{
        id: number;
        name: string;
        email: string;
        role: string | null;
        approval_status: ApprovalStatus;
    }>;
    lead_stats: {
        submitted: number;
        sold: number;
        listed: number;
        rejected: number;
    };
    recent_leads: Array<{
        id: number;
        lead_reference: string;
        status: string | null;
        created_at: string | null;
    }>;
    commissions: Array<{
        id: number;
        commission_reference: string;
        status: string | null;
        commission_amount: number | null;
    }>;
    payouts: Array<{
        id: number;
        payout_reference: string;
        status: string | null;
        amount: number | null;
    }>;
    activity: Array<{
        id: number;
        action: string;
        actor: string | null;
        created_at: string | null;
    }>;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.sellers;
    const common = translations.admin.common;
    const rejection = translations.rejection;
    const locationT = translations.location;
    const statuses = translations.statuses;
    const roles = translations.roles;
    const leadStatuses = translations.lead_statuses;
    const paymentStatuses = translations.payment_statuses;
    const isDesktop = useIsDesktop();
    const [editOpen, setEditOpen] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        name: seller.name,
        email: seller.email,
        phone: seller.phone ?? '',
        company_name: seller.company?.name ?? '',
        company_email: seller.company?.email ?? '',
        company_phone: seller.company?.phone ?? '',
    });

    const roleLabel =
        seller.role && roles[seller.role]
            ? roles[seller.role]
            : seller.role_label ?? '—';

    const sellerTypes =
        (t as { seller_types?: Record<string, string> }).seller_types ?? {};
    const sellerTypeLabel = seller.seller_type
        ? sellerTypes[seller.seller_type] ?? seller.seller_type
        : '—';

    const save = () => {
        put(route('admin.sellers.update', seller.id), {
            preserveScroll: true,
            onSuccess: () => setEditOpen(false),
        });
    };

    const personalCard = (
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
                <ProfileField label={common.name} value={seller.name} />
                <ProfileField label={common.email} value={seller.email} />
                <ProfileField
                    label={common.phone}
                    value={seller.phone ?? '—'}
                />
                <ProfileField label={common.role} value={roleLabel} />
                <ProfileField
                    label={common.status}
                    value={approvalStatusLabel(
                        seller.approval_status,
                        statuses,
                    )}
                />
            </dl>
        </ProfileCard>
    );

    const rejectionCard =
        seller.approval_status === 'rejected' ? (
            <ProfileCard title={rejection?.reason ?? common.reject}>
                <dl className="grid gap-3 sm:grid-cols-2">
                    <ProfileField
                        label={rejection?.rejected_by ?? common.reject}
                        value={seller.rejected_by?.name ?? '—'}
                    />
                    <ProfileField
                        label={rejection?.rejected_at ?? common.joined_date}
                        value={formatDateTime(seller.rejected_at, app.locale)}
                    />
                    <ProfileField
                        label={rejection?.reason ?? common.reject}
                        value={seller.rejection_reason ?? '—'}
                    />
                    <ProfileField
                        label={
                            rejection?.rejection_comment ??
                            rejection?.comment ??
                            common.reject
                        }
                        value={seller.rejection_comment ?? '—'}
                    />
                </dl>
            </ProfileCard>
        ) : null;

    const companyCard = (
        <ProfileCard title={common.company_details}>
            <dl className="grid gap-3 sm:grid-cols-2">
                <ProfileField
                    label={common.company}
                    value={seller.company?.name ?? '—'}
                />
                <ProfileField
                    label={t.filter_seller_type}
                    value={sellerTypeLabel}
                />
                <ProfileField
                    label={common.joined_date}
                    value={formatDate(seller.created_at, app.locale)}
                />
                <ProfileField
                    label={common.status}
                    value={
                        seller.company?.approval_status
                            ? approvalStatusLabel(
                                  seller.company.approval_status,
                                  statuses,
                              )
                            : '—'
                    }
                />
                <ProfileField
                    label={common.email}
                    value={seller.company?.email ?? '—'}
                />
                <ProfileField
                    label={common.phone}
                    value={seller.company?.phone ?? '—'}
                />
            </dl>
        </ProfileCard>
    );

    const locationCard = seller.company ? (
        <LocationCard
            location={seller.company}
            locale={app.locale}
            geocodeRoute={route('admin.sellers.geocode-company', seller.id)}
            updateRoute={route('admin.sellers.company-location', seller.id)}
            labels={{
                title: locationT?.company_title ?? '',
                status: locationT?.status ?? '',
                latitude: locationT?.latitude ?? '',
                longitude: locationT?.longitude ?? '',
                formatted_address: locationT?.formatted_address ?? '',
                geocoded_at: locationT?.geocoded_at ?? '',
                geocoding_error: locationT?.geocoding_error ?? '',
                retry: locationT?.retry ?? '',
                edit_coordinates: locationT?.edit_coordinates ?? '',
                save_coordinates: locationT?.save_coordinates ?? '',
                cancel: locationT?.cancel ?? common.cancel,
                statuses: locationT?.statuses ?? {},
            }}
        />
    ) : null;

    const performanceCard = (
        <ProfileCard title={common.performance_summary}>
            <div className="grid grid-cols-2 gap-x-3 gap-y-2 sm:grid-cols-4">
                <div>
                    <p className="text-lg font-semibold leading-tight">
                        {lead_stats.submitted}
                    </p>
                    <p className="text-xs text-rml-muted">
                        {t.leads_submitted}
                    </p>
                </div>
                <div>
                    <p className="text-lg font-semibold leading-tight">
                        {lead_stats.sold}
                    </p>
                    <p className="text-xs text-rml-muted">{t.leads_sold}</p>
                </div>
                <div>
                    <p className="text-lg font-semibold leading-tight">
                        {lead_stats.listed}
                    </p>
                    <p className="text-xs text-rml-muted">
                        {leadStatuses.listed}
                    </p>
                </div>
                <div>
                    <p className="text-lg font-semibold leading-tight">
                        {lead_stats.rejected}
                    </p>
                    <p className="text-xs text-rml-muted">
                        {leadStatuses.rejected}
                    </p>
                </div>
            </div>
            <div className="mt-3 grid gap-1 border-t border-rml-border pt-2 text-sm sm:grid-cols-2">
                <p>
                    {t.commissions}: {commissions.length}
                </p>
                <p>
                    {t.payouts}: {payouts.length}
                </p>
            </div>
        </ProfileCard>
    );

    const recentLeadsCard = (
        <ProfileCard title={common.recent_leads}>
            {recent_leads.length === 0 ? (
                <p className="text-sm text-rml-muted">{common.empty}</p>
            ) : (
                <DataTable
                    data={recent_leads}
                    getRowId={(row) => String(row.id)}
                    columns={[
                        {
                            id: 'lead_reference',
                            header: common.lead_id,
                            cell: (row) => (
                                <Link
                                    href={route(
                                        'admin.leads-bought.show',
                                        row.id,
                                    )}
                                    className="font-mono text-sm font-medium text-rml-primary"
                                >
                                    {row.lead_reference}
                                </Link>
                            ),
                        },
                        {
                            id: 'status',
                            header: common.status,
                            cell: (row) =>
                                row.status ? (
                                    <StatusBadge
                                        label={leadStatusLabel(
                                            row.status,
                                            leadStatuses,
                                        )}
                                        tone={leadStatusTone(row.status)}
                                    />
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            id: 'created_at',
                            header: common.date,
                            cell: (row) =>
                                formatDate(row.created_at, app.locale),
                        },
                    ]}
                />
            )}
            <div className="mt-2">
                <Link
                    href={route('admin.leads.index', {
                        tab: 'registered',
                        search: seller.email,
                    })}
                    className="text-xs font-semibold text-rml-primary hover:underline"
                >
                    {common.view_all}
                </Link>
            </div>
        </ProfileCard>
    );

    const staffCard =
        staff.length > 0 ? (
            <ProfileCard title={common.staff}>
                <DataTable
                    data={staff}
                    getRowId={(row) => String(row.id)}
                    columns={[
                        {
                            id: 'name',
                            header: common.name,
                            cell: (r) => (
                                <Link
                                    href={route('admin.sellers.show', r.id)}
                                    className="text-rml-primary"
                                >
                                    {r.name}
                                </Link>
                            ),
                        },
                        {
                            id: 'email',
                            header: common.email,
                            cell: (r) => r.email,
                        },
                        {
                            id: 'status',
                            header: common.status,
                            cell: (r) => (
                                <StatusBadge
                                    label={approvalStatusLabel(
                                        r.approval_status,
                                        statuses,
                                    )}
                                    tone={leadStatusTone(r.approval_status)}
                                />
                            ),
                        },
                    ]}
                />
            </ProfileCard>
        ) : null;

    const payoutsCard = (
        <ProfileCard title={t.payouts}>
            {payouts.length === 0 ? (
                <p className="text-sm text-rml-muted">{common.empty}</p>
            ) : (
                <DataTable
                    data={payouts}
                    getRowId={(r) => String(r.id)}
                    columns={[
                        {
                            id: 'ref',
                            header: common.reference,
                            cell: (r) => r.payout_reference,
                        },
                        {
                            id: 'amount',
                            header: common.amount,
                            cell: (r) => formatMoney(r.amount),
                        },
                        {
                            id: 'status',
                            header: common.status,
                            cell: (r) =>
                                r.status
                                    ? (paymentStatuses[r.status] ?? r.status)
                                    : '—',
                        },
                    ]}
                />
            )}
        </ProfileCard>
    );

    const commissionsCard =
        commissions.length > 0 ? (
            <ProfileCard title={t.commissions}>
                <DataTable
                    data={commissions}
                    getRowId={(r) => String(r.id)}
                    columns={[
                        {
                            id: 'ref',
                            header: common.reference,
                            cell: (r) => r.commission_reference,
                        },
                        {
                            id: 'amount',
                            header: common.amount,
                            cell: (r) => formatMoney(r.commission_amount),
                        },
                        {
                            id: 'status',
                            header: common.status,
                            cell: (r) =>
                                r.status
                                    ? (paymentStatuses[r.status] ?? r.status)
                                    : '—',
                        },
                    ]}
                />
            </ProfileCard>
        ) : null;

    const activityCard = (
        <ProfileCard title={common.account_activity ?? common.activity}>
            {activity.length === 0 ? (
                <p className="text-sm text-rml-muted">
                    {common.no_recent_account_activity ?? common.empty}
                </p>
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
                                {formatDateTime(item.created_at, app.locale)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </ProfileCard>
    );

    const stack = (cards: ReactNode) => (
        <div className="flex flex-col gap-3">{cards}</div>
    );

    return (
        <AppLayout title={t.show_title} subtitle={seller.name}>
            <Head title={t.show_title} />

            <div className="flex items-start gap-3">
                <BackLink
                    href={route('admin.users.index', { tab: 'sellers' })}
                    label={common.back}
                    className="mt-0.5"
                />
                <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <h1 className="text-xl font-semibold text-rml-text">
                            {seller.name}
                        </h1>
                        {seller.company?.name && (
                            <p className="text-sm text-rml-muted">
                                {seller.company.name}
                            </p>
                        )}
                        <div className="flex flex-wrap items-center gap-2 pt-1">
                            <StatusBadge label={roleLabel} tone="neutral" />
                            <StatusBadge
                                label={approvalStatusLabel(
                                    seller.approval_status,
                                    statuses,
                                )}
                                tone={leadStatusTone(seller.approval_status)}
                            />
                        </div>
                    </div>
                    <AccountRowActions
                        status={seller.approval_status}
                        targetName={seller.name}
                        companyName={seller.company?.name}
                        showView={false}
                        variant="buttons"
                        approveRoute={route(
                            'admin.sellers.approve',
                            seller.id,
                        )}
                        rejectRoute={route('admin.sellers.reject', seller.id)}
                        suspendRoute={route(
                            'admin.sellers.suspend',
                            seller.id,
                        )}
                        reinstateRoute={route(
                            'admin.sellers.reinstate',
                            seller.id,
                        )}
                        labels={accountActionLabels(common)}
                    />
                </div>
            </div>

            {isDesktop
                ? (
                    <div className="grid grid-cols-2 items-start gap-3">
                        {stack(
                            <>
                                {personalCard}
                                {rejectionCard}
                                {performanceCard}
                                {staffCard}
                                {activityCard}
                            </>,
                        )}
                        {stack(
                            <>
                                {companyCard}
                                {locationCard}
                                {recentLeadsCard}
                                {payoutsCard}
                                {commissionsCard}
                            </>,
                        )}
                    </div>
                )
                : stack(
                      <>
                          {personalCard}
                          {rejectionCard}
                          {companyCard}
                          {locationCard}
                          {performanceCard}
                          {recentLeadsCard}
                          {staffCard}
                          {payoutsCard}
                          {commissionsCard}
                          {activityCard}
                      </>,
                  )}

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
