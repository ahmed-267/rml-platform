import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    FileSearch,
    MessageSquare,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    KpiCard,
    MobileCardList,
    StatusBadge,
    TableActionLink,
    tableActionIcons,
} from '@/Components/ui';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface AuditRow {
    id: number;
    lead_reference: string;
    scheme: string | null;
    zone: string | null;
    size_m2: number | null;
    seller_name: string | null;
    audit_status: string | null;
    lead_status: string | null;
}

export default function AuditorDashboard({
    kpis,
    assigned_audits,
    needs_attention,
    recent_activity,
}: {
    kpis: {
        assigned: number;
        pending_validation: number;
        in_review: number;
        needs_more_information: number;
        completed_today: number;
        completed_week: number;
        open_messages: number;
    };
    assigned_audits: AuditRow[];
    needs_attention: AuditRow[];
    recent_activity: AuditRow[];
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.auditor;
    const common = t.common;
    const auditStatuses = translations.audit_statuses ?? {};
    const isMobile = useIsMobile();

    const statusLabel = (status: string | null) =>
        status
            ? (auditStatuses[status] ??
              leadStatusLabel(status, translations.lead_statuses))
            : '—';

    return (
        <AppLayout title={t.dashboard.title} subtitle={t.dashboard.subtitle}>
            <Head title={t.dashboard.title} />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <KpiCard
                    label={t.dashboard.kpi_assigned}
                    value={kpis.assigned}
                    tone="info"
                    icon={FileSearch}
                />
                <KpiCard
                    label={t.dashboard.kpi_pending_validation}
                    value={kpis.pending_validation}
                    tone="warning"
                    icon={ClipboardCheck}
                />
                <KpiCard
                    label={t.dashboard.kpi_in_review}
                    value={kpis.in_review}
                    tone="info"
                    icon={Clock3}
                />
                <KpiCard
                    label={t.dashboard.kpi_needs_info}
                    value={kpis.needs_more_information}
                    tone="warning"
                    icon={Clock3}
                />
                <KpiCard
                    label={t.dashboard.kpi_completed_today}
                    value={kpis.completed_today}
                    tone="success"
                    icon={CheckCircle2}
                />
                <KpiCard
                    label={t.dashboard.kpi_completed_week}
                    value={kpis.completed_week}
                    tone="success"
                    icon={CheckCircle2}
                />
            </div>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.dashboard.quick_actions}
                </h2>
                <div className="flex flex-wrap gap-2">
                    <Link
                        href={route('auditor.audits.index', {
                            tab: 'my-audits',
                        })}
                    >
                        <Button type="button">{t.dashboard.action_assigned}</Button>
                    </Link>
                    <Link
                        href={route('auditor.audits.index', {
                            tab: 'audit-queue',
                        })}
                    >
                        <Button type="button" variant="outline">
                            {t.dashboard.action_in_review}
                        </Button>
                    </Link>
                    <Link
                        href={route('auditor.audits.index', {
                            tab: 'completed',
                        })}
                    >
                        <Button type="button" variant="outline">
                            {t.dashboard.action_completed}
                        </Button>
                    </Link>
                    <Link href={route('auditor.messages.index')}>
                        <Button type="button" variant="outline">
                            <MessageSquare className="h-4 w-4" />
                            {t.dashboard.action_messages}
                            {kpis.open_messages > 0
                                ? ` (${kpis.open_messages})`
                                : ''}
                        </Button>
                    </Link>
                </div>
            </section>

            <AuditListSection
                title={t.dashboard.assigned_section}
                rows={assigned_audits}
                empty={common.empty}
                statusLabel={statusLabel}
                openLabel={common.open_audit}
                isMobile={isMobile}
                headers={{
                    leadId: common.lead_id,
                    scheme: common.scheme,
                    zone: common.zone,
                    status: common.status,
                }}
            />

            <AuditListSection
                title={t.dashboard.attention_section}
                rows={needs_attention}
                empty={common.empty}
                statusLabel={statusLabel}
                openLabel={common.open_audit}
                isMobile={isMobile}
                headers={{
                    leadId: common.lead_id,
                    scheme: common.scheme,
                    zone: common.zone,
                    status: common.status,
                }}
            />

            <AuditListSection
                title={t.dashboard.recent_section}
                rows={recent_activity}
                empty={common.empty}
                statusLabel={statusLabel}
                openLabel={common.view}
                isMobile={isMobile}
                headers={{
                    leadId: common.lead_id,
                    scheme: common.scheme,
                    zone: common.zone,
                    status: common.status,
                }}
            />
        </AppLayout>
    );
}

function AuditListSection({
    title,
    rows,
    empty,
    statusLabel,
    openLabel,
    isMobile,
    headers,
}: {
    title: string;
    rows: AuditRow[];
    empty: string;
    statusLabel: (s: string | null) => string;
    openLabel: string;
    isMobile: boolean;
    headers: {
        leadId: string;
        scheme: string;
        zone: string;
        status: string;
    };
}) {
    if (rows.length === 0) {
        return (
            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">{title}</h2>
                <EmptyState title={empty} />
            </section>
        );
    }

    if (isMobile) {
        return (
            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">{title}</h2>
                <MobileCardList
                    items={rows.map((row) => ({
                        id: String(row.id),
                        title: (
                            <span className="font-mono">{row.lead_reference}</span>
                        ),
                        subtitle: [row.scheme, row.zone, row.seller_name]
                            .filter(Boolean)
                            .join(' · '),
                        meta: (
                            <StatusBadge
                                label={statusLabel(row.audit_status)}
                                tone={leadStatusTone(row.audit_status)}
                            />
                        ),
                        actions: (
                            <TableActionLink
                                href={route('auditor.audits.show', row.id)}
                                label={openLabel}
                                icon={tableActionIcons.audit}
                                onClick={(e) => e.stopPropagation()}
                            />
                        ),
                    }))}
                    onItemClick={(id) =>
                        router.visit(route('auditor.audits.show', id))
                    }
                />
            </section>
        );
    }

    return (
        <section className="space-y-3">
            <h2 className="text-base font-semibold text-rml-text">{title}</h2>
            <DataTable
                data={rows}
                getRowId={(r) => String(r.id)}
                columns={[
                    {
                        id: 'ref',
                        header: headers.leadId,
                        cell: (r) => (
                            <span className="font-mono text-sm font-semibold">
                                {r.lead_reference}
                            </span>
                        ),
                    },
                    {
                        id: 'scheme',
                        header: headers.scheme,
                        cell: (r) => r.scheme ?? '—',
                    },
                    {
                        id: 'zone',
                        header: headers.zone,
                        cell: (r) => r.zone ?? '—',
                    },
                    {
                        id: 'status',
                        header: headers.status,
                        cell: (r) => (
                            <StatusBadge
                                label={statusLabel(r.audit_status)}
                                tone={leadStatusTone(r.audit_status)}
                            />
                        ),
                    },
                    {
                        id: 'action',
                        header: '',
                        cell: (r) => (
                            <TableActionLink
                                href={route('auditor.audits.show', r.id)}
                                label={openLabel}
                                icon={tableActionIcons.audit}
                            />
                        ),
                    },
                ]}
            />
        </section>
    );
}
