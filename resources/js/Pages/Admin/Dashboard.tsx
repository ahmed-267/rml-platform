import {
    BadgeCheck,
    Banknote,
    CircleDollarSign,
    HandCoins,
    Hourglass,
    Inbox,
    Layers,
    MessageCircleWarning,
    Percent,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    EmptyState,
    KpiCard,
    StatusBadge,
    TableActionLink,
    tableActionIcons,
} from '@/Components/ui';
import { formatMoney } from '@/lib/admin-helpers';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import { cn } from '@/lib/cn';
import type { BadgeTone } from '@/Components/ui/StatusBadge';
import type { PageProps } from '@/types';

type QueueCategory =
    | 'all'
    | 'audits'
    | 'payments'
    | 'approvals'
    | 'messages'
    | 'issues';

interface QueueItem {
    id: string;
    title: string;
    detail: string;
    badge: string;
    tone?: BadgeTone;
    href?: string;
    action_label?: string;
    category?: Exclude<QueueCategory, 'all'>;
}

interface AwaitingAuditLead {
    id: number;
    lead_reference: string;
    status: string | null;
    seller_name: string | null;
}

const PIPELINE_ORDER = [
    'pending_review',
    'needs_information',
    'listed',
    'sold',
    'rejected',
    'cancelled',
    'disputed',
];

export default function AdminDashboard({
    kpis,
    pipeline_counts,
    awaiting_audit_leads,
    action_queue,
    financial_snapshot,
    open_issues_summary,
}: {
    kpis: {
        total_submitted: number;
        awaiting_validation: number;
        leads_sold: number;
        buyer_payments_paid_sum: number;
        seller_payouts_paid_sum: number;
        profit_margin: number;
        open_issues: number;
        acceptance_rate: number;
        sold_rate?: number;
        awaiting_rate?: number;
        margin_rate?: number;
        pending_payments_count?: number;
        pending_payouts_count?: number;
        pending_approvals?: number;
    };
    pipeline_counts: Record<string, number>;
    awaiting_audit_leads: AwaitingAuditLead[];
    action_queue: QueueItem[];
    financial_snapshot: {
        buyer_payments_paid: number;
        seller_payouts_paid: number;
        profit_margin: number;
        pending_payments_sum: number;
        pending_payouts_sum: number;
        pending_payments_count: number;
        pending_payouts_count: number;
    };
    open_issues_summary: {
        open_count: number;
        items: QueueItem[];
    };
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.admin.dashboard;
    const common = translations.admin.common;
    const leadStatuses = translations.lead_statuses;
    const auditLabel = t.action_audit;

    const pipelineEntries = PIPELINE_ORDER.filter(
        (status) => (pipeline_counts[status] ?? 0) > 0,
    ).map((status) => [status, pipeline_counts[status] as number] as const);

    const pipelineMax = Math.max(
        1,
        ...pipelineEntries.map(([, count]) => count),
    );

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    className="p-4"
                    label={t.kpi_total_submitted}
                    value={kpis.total_submitted}
                    hint={t.kpi_hint_active_pipeline}
                    icon={Layers}
                    tone="info"
                />
                <KpiCard
                    className="p-4"
                    label={t.kpi_awaiting_validation}
                    value={kpis.awaiting_validation}
                    hint={
                        kpis.awaiting_rate != null
                            ? `${kpis.awaiting_rate}% ${t.kpi_hint_of_pipeline}`
                            : undefined
                    }
                    icon={Hourglass}
                    tone="warning"
                />
                <KpiCard
                    className="p-4"
                    label={t.kpi_leads_sold}
                    value={kpis.leads_sold}
                    hint={
                        kpis.sold_rate != null
                            ? `${kpis.sold_rate}% ${t.kpi_hint_conversion}`
                            : undefined
                    }
                    icon={BadgeCheck}
                    tone="success"
                />
                <KpiCard
                    className="p-4"
                    label={t.kpi_acceptance_rate}
                    value={`${kpis.acceptance_rate}%`}
                    hint={t.kpi_hint_acceptance}
                    icon={Percent}
                    tone="default"
                />
                <KpiCard
                    className="p-4"
                    label={t.kpi_buyer_payments_paid}
                    value={formatMoney(kpis.buyer_payments_paid_sum)}
                    hint={
                        kpis.pending_payments_count != null
                            ? `${kpis.pending_payments_count} ${t.kpi_hint_pending_payments}`
                            : undefined
                    }
                    icon={Banknote}
                    tone="info"
                />
                <KpiCard
                    className="p-4"
                    label={t.kpi_seller_payouts_paid}
                    value={formatMoney(kpis.seller_payouts_paid_sum)}
                    hint={
                        kpis.pending_payouts_count != null
                            ? `${kpis.pending_payouts_count} ${t.kpi_hint_pending_payouts}`
                            : undefined
                    }
                    icon={HandCoins}
                    tone="default"
                />
                <KpiCard
                    className="p-4"
                    label={t.kpi_profit_margin}
                    value={formatMoney(kpis.profit_margin)}
                    hint={
                        kpis.margin_rate != null
                            ? `${kpis.margin_rate}% ${t.kpi_hint_margin_rate}`
                            : undefined
                    }
                    icon={CircleDollarSign}
                    tone="success"
                />
                <KpiCard
                    className="p-4"
                    label={t.kpi_open_issues}
                    value={kpis.open_issues}
                    hint={
                        kpis.pending_approvals != null && kpis.pending_approvals > 0
                            ? `${kpis.pending_approvals} ${t.kpi_hint_pending_approvals}`
                            : t.kpi_hint_open_threads
                    }
                    icon={MessageCircleWarning}
                    tone="warning"
                />
            </div>

            {/* Row 1: Awaiting validation + Lead pipeline */}
            <div className="grid items-start gap-3 lg:grid-cols-3">
                <section className="rml-card p-4 lg:col-span-2">
                    <div className="mb-3 flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <Hourglass className="h-4 w-4 shrink-0 text-rml-amber" />
                                <h2 className="text-sm font-semibold text-rml-text">
                                    {t.awaiting_validation_title}
                                </h2>
                            </div>
                            <p className="mt-0.5 text-xs text-rml-muted">
                                {t.awaiting_validation_subtitle}
                            </p>
                        </div>
                        <Link
                            href={route('admin.leads.index', { tab: 'registered' })}
                            className="shrink-0 text-xs font-semibold text-rml-primary hover:underline"
                        >
                            {t.view_all}
                        </Link>
                    </div>

                    {awaiting_audit_leads.length === 0 ? (
                        <EmptyState title={common.empty} />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[28rem] text-left text-sm">
                                <thead>
                                    <tr className="border-b border-rml-border text-xs text-rml-muted">
                                        <th className="pb-2 pr-3 font-medium">
                                            {common.lead_id}
                                        </th>
                                        <th className="pb-2 pr-3 font-medium">
                                            {
                                                translations.admin.leads_bought
                                                    .seller
                                            }
                                        </th>
                                        <th className="pb-2 pr-3 font-medium">
                                            {common.status}
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            {common.actions}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {awaiting_audit_leads.map((lead) => (
                                        <tr
                                            key={lead.id}
                                            className="border-b border-rml-border/70 last:border-0"
                                        >
                                            <td className="py-2 pr-3">
                                                <Link
                                                    href={route(
                                                        'admin.leads-bought.show',
                                                        lead.id,
                                                    )}
                                                    className="font-mono text-sm font-semibold text-rml-primary hover:underline"
                                                >
                                                    {lead.lead_reference}
                                                </Link>
                                            </td>
                                            <td className="max-w-[10rem] truncate py-2 pr-3 text-rml-muted sm:max-w-[14rem]">
                                                {lead.seller_name ??
                                                    common.unknown}
                                            </td>
                                            <td className="py-2 pr-3">
                                                {lead.status ? (
                                                    <StatusBadge
                                                        label={leadStatusLabel(
                                                            lead.status,
                                                            leadStatuses,
                                                        )}
                                                        tone={leadStatusTone(
                                                            lead.status,
                                                        )}
                                                    />
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="py-2 text-right">
                                                <TableActionLink
                                                    href={`${route('admin.leads-bought.show', lead.id)}?audit=1`}
                                                    label={auditLabel}
                                                    icon={tableActionIcons.audit}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="rml-card p-4">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold text-rml-text">
                            {t.pipeline_title}
                        </h2>
                        <Link
                            href={route('admin.leads.index', { tab: 'registered' })}
                            className="text-xs font-semibold text-rml-primary hover:underline"
                        >
                            {t.view_leads}
                        </Link>
                    </div>
                    {pipelineEntries.length === 0 ? (
                        <EmptyState title={common.empty} />
                    ) : (
                        <ul className="space-y-2">
                            {pipelineEntries.map(([status, count]) => (
                                <li key={status} className="space-y-1">
                                    <div className="flex items-center justify-between gap-2 text-sm">
                                        <StatusBadge
                                            label={leadStatusLabel(
                                                status,
                                                leadStatuses,
                                            )}
                                            tone={leadStatusTone(status)}
                                        />
                                        <span className="font-semibold tabular-nums text-rml-text">
                                            {count}
                                        </span>
                                    </div>
                                    <div className="h-1 overflow-hidden rounded-full bg-rml-border/70">
                                        <div
                                            className="h-full rounded-full bg-rml-primary/70"
                                            style={{
                                                width: `${Math.max(8, (count / pipelineMax) * 100)}%`,
                                            }}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            {/* Row 2: Action queue · Financial · Open issues — equal height on xl */}
            <div className="grid items-start gap-3 md:grid-cols-2 xl:grid-cols-3 xl:items-stretch">
                <ActionQueueCard items={action_queue} />

                <section className="rml-card flex flex-col p-4 xl:h-[280px]">
                    <div className="mb-3 flex shrink-0 items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold text-rml-text">
                            {t.financial_snapshot_title}
                        </h2>
                        <Link
                            href={route('admin.payments.index')}
                            className="text-xs font-semibold text-rml-primary hover:underline"
                        >
                            {t.view_payments}
                        </Link>
                    </div>
                    <ul className="flex flex-1 flex-col justify-between text-sm">
                        <SnapshotRow
                            label={t.kpi_buyer_payments_paid}
                            value={formatMoney(
                                financial_snapshot.buyer_payments_paid,
                            )}
                        />
                        <SnapshotRow
                            label={t.kpi_seller_payouts_paid}
                            value={formatMoney(
                                financial_snapshot.seller_payouts_paid,
                            )}
                        />
                        <SnapshotRow
                            label={t.kpi_profit_margin}
                            value={formatMoney(
                                financial_snapshot.profit_margin,
                            )}
                        />
                        <SnapshotRow
                            label={t.financial_pending_payments}
                            value={formatMoney(
                                financial_snapshot.pending_payments_sum,
                            )}
                        />
                        <SnapshotRow
                            label={t.financial_payouts_due}
                            value={formatMoney(
                                financial_snapshot.pending_payouts_sum,
                            )}
                        />
                    </ul>
                </section>

                <section className="rml-card flex flex-col p-4 md:col-span-2 xl:col-span-1 xl:h-[280px]">
                    <div className="mb-3 flex shrink-0 items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold text-rml-text">
                            {t.open_issues_title}
                        </h2>
                        <Link
                            href={route('admin.messages.index')}
                            className="text-xs font-semibold text-rml-primary hover:underline"
                        >
                            {t.view_messages}
                        </Link>
                    </div>
                    <p className="mb-2 shrink-0 text-xs text-rml-muted">
                        {t.open_issues_count.replace(
                            ':count',
                            String(open_issues_summary.open_count),
                        )}
                    </p>
                    {open_issues_summary.items.length === 0 ? (
                        <div className="flex flex-1 items-center justify-center">
                            <EmptyState title={common.empty} />
                        </div>
                    ) : (
                        <ul className="flex min-h-0 flex-1 flex-col justify-between gap-1.5 overflow-y-auto">
                            {open_issues_summary.items.slice(0, 3).map((item) => (
                                <li key={item.id}>
                                    <div className="flex items-start justify-between gap-2 rounded-lg border border-rml-border/80 px-2.5 py-1.5">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-rml-text">
                                                {item.title}
                                            </p>
                                            <p className="mt-0.5 truncate text-xs text-rml-muted">
                                                {item.detail}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            <StatusBadge
                                                label={item.badge}
                                                tone={item.tone ?? 'neutral'}
                                            />
                                            {item.href ? (
                                                <Link
                                                    href={item.href}
                                                    className="text-xs font-semibold text-rml-primary hover:underline"
                                                >
                                                    {item.action_label ??
                                                        t.action_open}
                                                </Link>
                                            ) : null}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function ActionQueueCard({ items }: { items: QueueItem[] }) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.admin.dashboard;
    const [tab, setTab] = useState<QueueCategory>('all');

    const tabs: { id: QueueCategory; label: string }[] = [
        { id: 'all', label: t.queue_tab_all },
        { id: 'audits', label: t.queue_tab_audits },
        { id: 'payments', label: t.queue_tab_payments },
        { id: 'approvals', label: t.queue_tab_approvals },
        { id: 'messages', label: t.queue_tab_messages },
        { id: 'issues', label: t.queue_tab_issues },
    ];

    const filtered = useMemo(
        () =>
            tab === 'all'
                ? items
                : items.filter((item) => item.category === tab),
        [items, tab],
    );

    const viewAllHref = useMemo(() => {
        switch (tab) {
            case 'audits':
                return route('admin.leads.index', { tab: 'registered' });
            case 'payments':
                return route('admin.payments.index');
            case 'approvals':
                return route('admin.users.index', {
                    tab: 'sellers',
                    approval_status: 'pending',
                });
            case 'messages':
            case 'issues':
                return route('admin.messages.index');
            default:
                return route('admin.users.index', {
                    tab: 'sellers',
                    approval_status: 'pending',
                });
        }
    }, [tab]);

    return (
        <section className="rml-card flex max-h-[280px] flex-col p-4 xl:h-[280px]">
            <div className="mb-2 flex shrink-0 items-start justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <Inbox className="h-4 w-4 shrink-0 text-rml-primary" />
                    <h2 className="text-sm font-semibold text-rml-text">
                        {t.queue_title}
                    </h2>
                </div>
                <Link
                    href={viewAllHref}
                    className="shrink-0 text-xs font-semibold text-rml-primary hover:underline"
                >
                    {t.view_all}
                </Link>
            </div>

            <div
                className="-mx-1 mb-2 flex shrink-0 gap-1 overflow-x-auto px-1 pb-0.5 [scrollbar-width:thin]"
                role="tablist"
                aria-label={t.queue_title}
            >
                {tabs.map((item) => {
                    const selected = tab === item.id;

                    return (
                        <button
                            key={item.id}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            onClick={() => setTab(item.id)}
                            className={cn(
                                'shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rml-primary/40',
                                selected
                                    ? 'bg-rml-primary text-white'
                                    : 'bg-rml-background text-rml-muted hover:bg-rml-primary-light hover:text-rml-primary',
                            )}
                        >
                            {item.label}
                        </button>
                    );
                })}
            </div>

            {filtered.length === 0 ? (
                <div className="flex flex-1 items-center justify-center">
                    <EmptyState title={t.queue_tab_empty} />
                </div>
            ) : (
                <ul className="min-h-0 flex-1 space-y-1.5 overflow-y-auto pr-0.5 [scrollbar-width:thin]">
                    {filtered.map((item) => (
                        <li key={item.id}>
                            <div className="flex items-center gap-2 rounded-lg border border-rml-border bg-rml-background/50 px-2.5 py-1.5">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <p className="truncate text-sm font-medium text-rml-text">
                                            {item.title}
                                        </p>
                                        <StatusBadge
                                            label={item.badge}
                                            tone={item.tone ?? 'warning'}
                                        />
                                    </div>
                                    <p className="mt-0.5 truncate text-xs text-rml-muted">
                                        {item.detail}
                                    </p>
                                </div>
                                {item.href ? (
                                    <Link
                                        href={item.href}
                                        className="shrink-0 text-xs font-semibold text-rml-primary hover:underline"
                                    >
                                        {item.action_label ?? t.action_review}
                                    </Link>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function SnapshotRow({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <li className="flex items-center justify-between gap-3 border-b border-rml-border/60 py-1 last:border-0">
            <span className="text-xs text-rml-muted sm:text-sm">{label}</span>
            <span className="text-sm font-semibold tabular-nums text-rml-text">
                {value}
            </span>
        </li>
    );
}
