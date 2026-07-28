import { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    AuditLeadModal,
    type AuditPayload,
} from '@/Components/admin/AuditLeadModal';
import { BackLink } from '@/Components/admin/BackLink';
import { SellerQuickViewModal } from '@/Components/admin/SellerQuickViewModal';
import {
    Button,
    StatusBadge,
    TableActionButton,
    tableActionIcons,
} from '@/Components/ui';
import {
    approvalStatusLabel,
    formatDateTime,
    formatMoney,
} from '@/lib/admin-helpers';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface AdminLead {
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
    notes: string | null;
    rejection_reason: string | null;
    rejection_comment?: string | null;
    rejected_at?: string | null;
    rejected_by?: { id: number; name: string; email?: string | null } | null;
    requested_info: string | null;
    seller: {
        id: number;
        name: string;
        email: string;
        phone?: string | null;
        role_label?: string | null;
        approval_status?: string | null;
    } | null;
    seller_company: { id: number; name: string } | null;
    scheme: { id: number; name: string } | null;
    zone: { id: number; code: string; name: string } | null;
    size_m2: number | null;
    evidence: Array<{
        id: number;
        file_type: string | null;
        original_name: string | null;
        mime_type: string | null;
        size: number | null;
    }>;
    created_at: string | null;
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-rml-muted">{label}</dt>
            <dd className="text-sm text-rml-text">{value}</dd>
        </div>
    );
}

export default function LeadsBoughtShow({
    lead,
    auditOpen,
    audit,
}: {
    lead: AdminLead;
    auditOpen?: boolean;
    audit?: AuditPayload | null;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.leads_bought;
    const auditT = translations.admin.audit;
    const common = translations.admin.common;
    const rejection = translations.rejection;
    const leadStatuses = translations.lead_statuses;
    const statuses = translations.statuses;

    const [modalOpen, setModalOpen] = useState(Boolean(auditOpen));
    const [sellerOpen, setSellerOpen] = useState(false);

    useEffect(() => {
        setModalOpen(Boolean(auditOpen));
    }, [auditOpen]);

    const customerName =
        `${lead.customer_first_name} ${lead.customer_last_name}`.trim();

    return (
        <AppLayout
            title={t.show_title}
            subtitle={lead.lead_reference}
            headerActions={
                <Button size="sm" onClick={() => setModalOpen(true)}>
                    {t.audit_lead}
                </Button>
            }
        >
            <Head title={t.show_title} />

            <div className="flex items-start gap-3">
                <BackLink
                    href={route('admin.leads.index', { tab: 'registered' })}
                    label={common.back}
                    className="mt-0.5"
                />
                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                    <span className="font-mono text-2xl font-bold tracking-tight text-rml-text sm:text-3xl">
                        {lead.lead_reference}
                    </span>
                    {lead.status && (
                        <StatusBadge
                            label={leadStatusLabel(lead.status, leadStatuses)}
                            tone={leadStatusTone(lead.status)}
                            className="px-3 py-1 text-sm font-semibold"
                        />
                    )}
                </div>
            </div>

            <section className="rml-card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                <DetailRow
                    label={auditT.summary}
                    value={`${lead.scheme?.name ?? '—'} · ${lead.zone?.code ?? '—'}`}
                />
                <DetailRow label={common.customer} value={customerName} />
                <DetailRow label={common.phone} value={lead.customer_phone} />
                <DetailRow
                    label={common.email}
                    value={lead.customer_email ?? '—'}
                />
                <DetailRow
                    label={
                        lead.seller_company
                            ? (t.seller_company ?? t.seller)
                            : t.seller
                    }
                    value={
                        lead.seller_company?.name ??
                        lead.seller?.name ??
                        common.unknown
                    }
                />
                <DetailRow
                    label={t.seller_payout ?? t.buying_price}
                    value={formatMoney(lead.buying_price)}
                />
                <DetailRow
                    label={t.selling_price}
                    value={formatMoney(lead.selling_price)}
                />
                <DetailRow
                    label={t.margin}
                    value={formatMoney(lead.expected_margin)}
                />
                <DetailRow
                    label={common.date}
                    value={formatDateTime(lead.created_at, app.locale)}
                />
            </section>

            {(lead.rejection_reason ||
                lead.rejection_comment ||
                lead.rejected_at ||
                lead.rejected_by) && (
                <section className="rml-card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    {lead.rejected_by && (
                        <DetailRow
                            label={rejection?.rejected_by ?? t.seller}
                            value={lead.rejected_by.name}
                        />
                    )}
                    {lead.rejected_at && (
                        <DetailRow
                            label={rejection?.rejected_at ?? common.date}
                            value={formatDateTime(lead.rejected_at, app.locale)}
                        />
                    )}
                    {lead.rejection_reason && (
                        <DetailRow
                            label={
                                rejection?.reason ?? auditT.rejection_reason
                            }
                            value={lead.rejection_reason}
                        />
                    )}
                    {lead.rejection_comment && (
                        <DetailRow
                            label={
                                rejection?.rejection_comment ??
                                rejection?.comment ??
                                auditT.rejection_reason
                            }
                            value={lead.rejection_comment}
                        />
                    )}
                </section>
            )}

            {lead.notes && (
                <section className="rml-card p-5">
                    <h2 className="mb-2 text-sm font-semibold text-rml-text">
                        {auditT.audit_notes}
                    </h2>
                    <p className="whitespace-pre-wrap text-sm">{lead.notes}</p>
                </section>
            )}

            <section className="rml-card p-5">
                <h2 className="mb-3 text-sm font-semibold text-rml-text">
                    {auditT.evidence}
                </h2>
                {lead.evidence.length === 0 ? (
                    <p className="text-sm text-rml-muted">—</p>
                ) : (
                    <ul className="divide-y divide-rml-border">
                        {lead.evidence.map((file) => (
                            <li
                                key={file.id}
                                className="flex justify-between py-2 text-sm"
                            >
                                <span>
                                    {file.original_name ?? file.file_type}
                                </span>
                                <span className="text-rml-muted">
                                    {file.mime_type}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {lead.seller && (
                <section className="rml-card p-5">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <h2 className="text-sm font-semibold text-rml-text">
                            {lead.seller_company
                                ? (t.seller_company ?? t.seller)
                                : t.seller}
                        </h2>
                        <TableActionButton
                            label={t.view_seller ?? common.view}
                            icon={tableActionIcons.view}
                            onClick={() => setSellerOpen(true)}
                        />
                    </div>
                    <p className="text-sm font-medium text-rml-text">
                        {lead.seller_company?.name ?? lead.seller.name}
                    </p>
                    <p className="mt-1 text-sm text-rml-muted">
                        {lead.seller.name} · {lead.seller.email}
                    </p>
                </section>
            )}

            <SellerQuickViewModal
                open={sellerOpen}
                onClose={() => setSellerOpen(false)}
                seller={lead.seller}
                companyName={lead.seller_company?.name}
                statusLabel={
                    lead.seller?.approval_status
                        ? approvalStatusLabel(
                              lead.seller.approval_status,
                              statuses,
                          )
                        : undefined
                }
                statusTone={
                    lead.seller?.approval_status
                        ? leadStatusTone(lead.seller.approval_status)
                        : undefined
                }
                labels={{
                    title: t.view_seller ?? t.seller,
                    name: common.name,
                    email: common.email,
                    phone: common.phone,
                    company: common.company,
                    role: common.role ?? t.seller,
                    status: common.status,
                    openFullProfile: common.view,
                    close: common.close ?? common.cancel,
                }}
            />

            <AuditLeadModal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                leadId={lead.id}
                audit={audit}
            />
        </AppLayout>
    );
}
