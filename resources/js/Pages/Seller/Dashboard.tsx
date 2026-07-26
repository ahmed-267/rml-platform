import {
    CheckCircle2,
    ClipboardCheck,
    FileText,
    Wallet,
    XCircle,
    ImagePlus,
} from 'lucide-react';
import { Head, Link, usePage } from '@inertiajs/react';
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

interface RecentLead {
    id: number;
    lead_reference: string;
    status: string | null;
    scheme_name: string | null;
    zone_code: string | null;
    customer_name: string;
    created_at: string | null;
}

interface DashboardKpis {
    total_leads: number;
    drafts: number;
    pending_evidence: number;
    pending_validation: number;
    needs_more_information: number;
    accepted: number;
    rejected: number;
    sold: number;
    commissions_due: number;
    commissions_paid: number;
    payouts_pending: number;
    payouts_paid: number;
}

function formatMoney(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatDate(value: string | null, locale: string): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function SellerDashboard({
    kpis,
    recent_leads,
}: {
    kpis: DashboardKpis;
    recent_leads: RecentLead[];
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.seller.dashboard;
    const common = translations.seller.common;
    const leadStatuses = translations.lead_statuses;
    const isMobile = useIsMobile();

    const reviewed = kpis.accepted + kpis.rejected;
    const validationRate =
        reviewed > 0 ? Math.round((kpis.accepted / reviewed) * 100) : null;

    const validationHint =
        reviewed > 0
            ? t.accepted_of_reviewed
                  .replace(':accepted', String(kpis.accepted))
                  .replace(':reviewed', String(reviewed))
            : undefined;

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    {validationRate !== null && (
                        <p className="text-sm text-rml-muted">
                            {t.validation_rate}:{' '}
                            <span className="font-semibold text-rml-text">
                                {validationRate}%
                            </span>
                            {validationHint ? ` · ${validationHint}` : null}
                        </p>
                    )}
                </div>
                <Link href={route('seller.leads.create')}>
                    <Button>{t.submit_cta}</Button>
                </Link>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <KpiCard
                    label={t.kpi_submitted}
                    value={kpis.total_leads}
                    icon={FileText}
                    tone="info"
                />
                <KpiCard
                    label={t.kpi_accepted}
                    value={kpis.accepted}
                    icon={CheckCircle2}
                    tone="success"
                />
                <KpiCard
                    label={t.kpi_rejected}
                    value={kpis.rejected}
                    icon={XCircle}
                    tone="danger"
                />
                <KpiCard
                    label={t.kpi_pending_audit}
                    value={kpis.pending_validation}
                    icon={ClipboardCheck}
                    tone="warning"
                />
                <KpiCard
                    label={t.kpi_pending_evidence}
                    value={kpis.pending_evidence}
                    icon={ImagePlus}
                    tone="warning"
                />
                <KpiCard
                    label={t.kpi_earnings}
                    value={formatMoney(kpis.payouts_pending)}
                    icon={Wallet}
                    tone="default"
                />
            </div>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.recent_leads}
                </h2>

                {recent_leads.length === 0 ? (
                    <EmptyState title={t.no_leads}>
                        <Link href={route('seller.leads.create')}>
                            <Button>{t.submit_cta}</Button>
                        </Link>
                    </EmptyState>
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.no_leads}
                        items={recent_leads.map((lead) => ({
                            id: String(lead.id),
                            title: (
                                <span className="font-mono text-sm">
                                    {lead.lead_reference}
                                </span>
                            ),
                            subtitle: lead.customer_name,
                            meta: lead.status ? (
                                <StatusBadge
                                    label={leadStatusLabel(
                                        lead.status,
                                        leadStatuses,
                                    )}
                                    tone={leadStatusTone(lead.status)}
                                />
                            ) : null,
                            body: (
                                <div className="space-y-1 text-rml-muted">
                                    <p>
                                        {common.scheme}: {lead.scheme_name ?? '—'}
                                    </p>
                                    <p>
                                        {common.zone}: {lead.zone_code ?? '—'}
                                    </p>
                                    <p>
                                        {common.submitted}:{' '}
                                        {formatDate(lead.created_at, app.locale)}
                                    </p>
                                </div>
                            ),
                            actions: (
                                <TableActionLink href={route('seller.leads.show', lead.id)} label={common.view} icon={tableActionIcons.view} />
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={recent_leads}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.no_leads}
                        columns={[
                            {
                                id: 'lead_reference',
                                header: common.lead_id,
                                cell: (row) => (
                                    <span className="font-mono text-sm font-medium">
                                        {row.lead_reference}
                                    </span>
                                ),
                            },
                            {
                                id: 'customer_name',
                                header: common.customer,
                                cell: (row) => row.customer_name,
                            },
                            {
                                id: 'scheme_name',
                                header: common.scheme,
                                cell: (row) => row.scheme_name ?? '—',
                            },
                            {
                                id: 'zone_code',
                                header: common.zone,
                                cell: (row) => row.zone_code ?? '—',
                            },
                            {
                                id: 'created_at',
                                header: common.submitted,
                                cell: (row) =>
                                    formatDate(row.created_at, app.locale),
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
                                id: 'actions',
                                header: common.actions,
                                cell: (row) => (
                                    <TableActionLink href={route(
                                            'seller.leads.show',
                                            row.id,
                                        )} label={common.view} icon={tableActionIcons.view} />
                                ),
                            },
                        ]}
                    />
                )}
            </section>
        </AppLayout>
    );
}
