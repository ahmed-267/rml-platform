import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { BackLink } from '@/Components/admin/BackLink';
import { ProfileCard, ProfileField } from '@/Components/admin/ProfileCard';
import { StatusBadge } from '@/Components/ui';
import { formatDateTime, formatMoney } from '@/lib/admin-helpers';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface SoldLead {
    id: number;
    lead_reference: string;
    status: string | null;
    customer_first_name: string;
    customer_last_name: string;
    customer_phone: string;
    customer_email: string | null;
    address_line_1: string | null;
    city: string | null;
    postcode: string | null;
    buying_price: number | null;
    selling_price: number | null;
    expected_margin: number | null;
    commission_due?: number | null;
    payout_due?: number | null;
    scheme: { id: number; name: string } | null;
    zone: { id: number; code: string; name: string } | null;
    seller: { id: number; name: string; email: string } | null;
    seller_company: { id: number; name: string } | null;
    evidence?: Array<{
        id: number;
        original_name: string | null;
        mime_type: string | null;
    }>;
    audits?: Array<{
        id: number;
        status: string | null;
        completed_at: string | null;
    }>;
    buyer: {
        company_name: string | null;
        user_name: string | null;
        user_email: string | null;
        purchase_reference: string | null;
        purchase_status?: string | null;
        purchased_at: string | null;
        payment_reference: string | null;
        payment_status: string | null;
        payment_method?: string | null;
        paid_at?: string | null;
        amount_paid: number | null;
        currency?: string | null;
        release_status?: string | null;
    } | null;
    activity?: Array<{
        id: number;
        action: string;
        user_name: string | null;
        created_at: string | null;
    }>;
    sold_at: string | null;
}

export default function LeadsSoldShow({ lead }: { lead: SoldLead }) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.leads_sold;
    const lb = translations.admin.leads_bought;
    const common = translations.admin.common;
    const leadStatuses = translations.lead_statuses;
    const paymentStatuses = translations.payment_statuses;
    const paymentMethods = translations.payment_methods ?? {};
    const latestAudit = lead.audits?.[0] ?? null;

    const releaseLabel = (status: string | null | undefined) => {
        if (status === 'released') {
            return t.release_released ?? common.released_leads;
        }
        if (status === 'pending_release') {
            return t.release_pending ?? common.pending_release;
        }
        return status ?? '—';
    };

    return (
        <AppLayout title={t.show_title} subtitle={lead.lead_reference}>
            <Head title={t.show_title} />

            <div className="flex items-start gap-3">
                <BackLink
                    href={route('admin.leads-sold.index')}
                    label={common.back}
                    className="mt-0.5"
                />
                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="font-mono text-xl font-semibold text-rml-text">
                            {lead.lead_reference}
                        </h1>
                        {lead.status && (
                            <StatusBadge
                                label={leadStatusLabel(
                                    lead.status,
                                    leadStatuses,
                                )}
                                tone={leadStatusTone(lead.status)}
                            />
                        )}
                    </div>
                    <p className="text-sm text-rml-muted">
                        {lead.seller_company?.name ??
                            lead.seller?.name ??
                            t.show_title}
                    </p>
                </div>
            </div>

            <div className="grid items-start gap-3 lg:grid-cols-2">
                <ProfileCard title={t.lead_summary}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField
                            label={common.lead_id}
                            value={lead.lead_reference}
                        />
                        <ProfileField
                            label={common.scheme}
                            value={lead.scheme?.name ?? '—'}
                        />
                        <ProfileField
                            label={common.zone}
                            value={lead.zone?.code ?? '—'}
                        />
                        <ProfileField
                            label={common.status}
                            value={
                                lead.status
                                    ? leadStatusLabel(lead.status, leadStatuses)
                                    : '—'
                            }
                        />
                        <ProfileField
                            label={t.sold_date ?? common.date}
                            value={formatDateTime(lead.sold_at, app.locale)}
                        />
                        <ProfileField
                            label={lb.seller}
                            value={
                                lead.seller_company?.name ??
                                lead.seller?.name ??
                                '—'
                            }
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={t.customer_details}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField
                            label={common.name}
                            value={`${lead.customer_first_name} ${lead.customer_last_name}`.trim()}
                        />
                        <ProfileField
                            label={common.phone}
                            value={lead.customer_phone}
                        />
                        <ProfileField
                            label={common.email}
                            value={lead.customer_email ?? '—'}
                        />
                        <ProfileField
                            label={common.details}
                            value={
                                [
                                    lead.address_line_1,
                                    lead.city,
                                    lead.postcode,
                                ]
                                    .filter(Boolean)
                                    .join(', ') || '—'
                            }
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={t.seller_details}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField
                            label={common.company}
                            value={lead.seller_company?.name ?? '—'}
                        />
                        <ProfileField
                            label={common.name}
                            value={lead.seller?.name ?? '—'}
                        />
                        <ProfileField
                            label={common.email}
                            value={lead.seller?.email ?? '—'}
                        />
                        <ProfileField
                            label={t.payout_due}
                            value={formatMoney(
                                lead.payout_due ?? lead.buying_price,
                            )}
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={t.buyer_details}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField
                            label={common.company}
                            value={lead.buyer?.company_name ?? '—'}
                        />
                        <ProfileField
                            label={common.name}
                            value={lead.buyer?.user_name ?? '—'}
                        />
                        <ProfileField
                            label={common.email}
                            value={lead.buyer?.user_email ?? '—'}
                        />
                        <ProfileField
                            label={t.purchase_reference}
                            value={lead.buyer?.purchase_reference ?? '—'}
                        />
                        <ProfileField
                            label={t.release_status}
                            value={releaseLabel(lead.buyer?.release_status)}
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={t.financials}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField
                            label={t.buying_price ?? lb.buying_price}
                            value={formatMoney(lead.buying_price)}
                        />
                        <ProfileField
                            label={t.selling_price ?? lb.selling_price}
                            value={formatMoney(lead.selling_price)}
                        />
                        <ProfileField
                            label={t.margin}
                            value={formatMoney(lead.expected_margin)}
                        />
                        <ProfileField
                            label={t.commission_due}
                            value={formatMoney(lead.commission_due ?? 0)}
                        />
                        <ProfileField
                            label={t.payout_due}
                            value={formatMoney(
                                lead.payout_due ?? lead.buying_price,
                            )}
                        />
                        <ProfileField
                            label={t.currency ?? 'EUR'}
                            value={lead.buyer?.currency ?? 'EUR'}
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={t.payment_purchase}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField
                            label={t.payment_reference}
                            value={lead.buyer?.payment_reference ?? '—'}
                        />
                        <div>
                            <dt className="text-xs text-rml-muted">
                                {t.payment_status ?? common.status}
                            </dt>
                            <dd className="pt-0.5">
                                {lead.buyer?.payment_status ? (
                                    <StatusBadge
                                        label={
                                            paymentStatuses[
                                                lead.buyer.payment_status
                                            ] ?? lead.buyer.payment_status
                                        }
                                        tone={leadStatusTone(
                                            lead.buyer.payment_status,
                                        )}
                                    />
                                ) : (
                                    '—'
                                )}
                            </dd>
                        </div>
                        <ProfileField
                            label={common.payment_method}
                            value={
                                lead.buyer?.payment_method
                                    ? (paymentMethods[
                                          lead.buyer.payment_method
                                      ] ?? lead.buyer.payment_method)
                                    : '—'
                            }
                        />
                        <ProfileField
                            label={t.paid_date ?? common.date}
                            value={formatDateTime(
                                lead.buyer?.paid_at ?? null,
                                app.locale,
                            )}
                        />
                        <ProfileField
                            label={common.amount}
                            value={formatMoney(lead.buyer?.amount_paid)}
                        />
                        <ProfileField
                            label={t.purchase_reference}
                            value={lead.buyer?.purchase_reference ?? '—'}
                        />
                    </dl>
                </ProfileCard>

                <ProfileCard title={t.evidence_audit_summary}>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <ProfileField
                            label={t.audit_status ?? common.status}
                            value={
                                latestAudit?.status
                                    ? leadStatusLabel(
                                          latestAudit.status,
                                          leadStatuses,
                                      )
                                    : '—'
                            }
                        />
                        <ProfileField
                            label={t.audit_date ?? common.date}
                            value={formatDateTime(
                                latestAudit?.completed_at ?? null,
                                app.locale,
                            )}
                        />
                        <ProfileField
                            label={t.evidence_count ?? t.evidence_audit_summary}
                            value={String(lead.evidence?.length ?? 0)}
                        />
                    </dl>
                    {(lead.evidence?.length ?? 0) > 0 && (
                        <ul className="mt-3 space-y-1 text-sm text-rml-muted">
                            {lead.evidence?.slice(0, 5).map((file) => (
                                <li key={file.id}>
                                    {file.original_name ?? file.mime_type ?? '—'}
                                </li>
                            ))}
                        </ul>
                    )}
                </ProfileCard>

                <ProfileCard title={t.activity ?? common.activity}>
                    {(lead.activity?.length ?? 0) === 0 ? (
                        <p className="text-sm text-rml-muted">
                            {common.no_recent_account_activity}
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {lead.activity?.map((item) => (
                                <li
                                    key={item.id}
                                    className="border-b border-rml-border pb-2 text-sm last:border-0 last:pb-0"
                                >
                                    <p className="font-medium text-rml-text">
                                        {item.action}
                                    </p>
                                    <p className="text-xs text-rml-muted">
                                        {[
                                            item.user_name,
                                            formatDateTime(
                                                item.created_at,
                                                app.locale,
                                            ),
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </ProfileCard>
            </div>
        </AppLayout>
    );
}
