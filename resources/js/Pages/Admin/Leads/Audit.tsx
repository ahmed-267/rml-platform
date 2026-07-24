import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    AuditLeadModal,
    type AuditPayload,
} from '@/Components/admin/AuditLeadModal';
import { BackLink } from '@/Components/admin/BackLink';
import { Button, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatMoney } from '@/lib/admin-helpers';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface AuditPageProps {
    lead: {
        id: number;
        lead_reference: string;
        status: string | null;
        scheme?: { name: string } | null;
        zone?: { code: string } | null;
        size_m2?: number | null;
        buying_price?: number | null;
        selling_price?: number | null;
    };
    audit: AuditPayload;
    auditOpen?: boolean;
}

export default function AdminLeadAudit({
    lead,
    audit,
    auditOpen = true,
}: AuditPageProps) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.admin.audit;
    const common = translations.admin.common;
    const [open, setOpen] = useState(auditOpen);

    return (
        <AppLayout title={t.title} subtitle={lead.lead_reference}>
            <Head title={`${t.title} · ${lead.lead_reference}`} />

            <div className="space-y-4">
                <div className="rml-card flex flex-wrap items-start gap-3 p-5">
                    <BackLink
                        href={route('admin.leads-bought.show', lead.id)}
                        label={common.back}
                    />
                    <div className="min-w-0 flex-1 space-y-1">
                        <p className="font-mono text-sm font-semibold text-rml-text">
                            {lead.lead_reference}
                        </p>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-rml-muted">
                            <StatusBadge
                                tone={leadStatusTone(lead.status)}
                                label={leadStatusLabel(
                                    lead.status,
                                    translations.lead_statuses,
                                )}
                            />
                            <span>{lead.scheme?.name}</span>
                            <span>{lead.zone?.code}</span>
                            {lead.size_m2 != null && (
                                <span>{lead.size_m2} m²</span>
                            )}
                            {lead.selling_price != null && (
                                <span>{formatMoney(lead.selling_price)}</span>
                            )}
                        </div>
                    </div>
                    <Button variant="outline" onClick={() => setOpen(true)}>
                        {t.open_audit ?? t.title}
                    </Button>
                </div>
            </div>

            <AuditLeadModal
                open={open}
                onClose={() => setOpen(false)}
                leadId={lead.id}
                audit={audit}
            />
        </AppLayout>
    );
}
