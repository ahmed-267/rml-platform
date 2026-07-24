import { ReactNode, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    ClipboardList,
    FileWarning,
    Inbox,
    LucideIcon,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Alert,
    DataTable,
    EmptyState,
    FilterBar,
    KpiCard,
    MobileCardList,
    MobileFilterDrawer,
    Select,
    StatusBadge,
} from '@/Components/ui';
import { useIsMobile } from '@/hooks/use-media-query';
import type { BadgeTone } from '@/Components/ui/StatusBadge';

export type PortalDashboardLead = {
    id: string;
    scheme: string;
    zone: string;
    size: number;
    status: string;
    statusTone?: BadgeTone;
};

export type PortalDashboardKpi = {
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'info';
};

export type ActionQueueItem = {
    id: string;
    title: string;
    detail: string;
    tone?: BadgeTone;
    badge: string;
};

export interface PortalDashboardProps {
    title: string;
    subtitle: string;
    kpis: PortalDashboardKpi[];
    actionQueue: ActionQueueItem[];
    recentActivity: ActionQueueItem[];
    pendingEvidence: ActionQueueItem[];
    leads: PortalDashboardLead[];
    alertTitle?: string;
    alertBody?: string;
    headerActions?: ReactNode;
}

export function PortalDashboard({
    title,
    subtitle,
    kpis,
    actionQueue,
    recentActivity,
    pendingEvidence,
    leads,
    alertTitle,
    alertBody,
    headerActions,
}: PortalDashboardProps) {
    const isMobile = useIsMobile();
    const [search, setSearch] = useState('');
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [scheme, setScheme] = useState('');

    const filteredLeads = useMemo(() => {
        return leads.filter((lead) => {
            const matchesSearch =
                !search ||
                lead.id.toLowerCase().includes(search.toLowerCase()) ||
                lead.scheme.toLowerCase().includes(search.toLowerCase()) ||
                lead.status.toLowerCase().includes(search.toLowerCase());
            const matchesScheme = !scheme || lead.scheme === scheme;
            return matchesSearch && matchesScheme;
        });
    }, [leads, search, scheme]);

    return (
        <AppLayout title={title} subtitle={subtitle} headerActions={headerActions}>
            <Head title={title} />

            {(alertTitle || alertBody) && (
                <Alert variant="info" title={alertTitle}>
                    {alertBody}
                </Alert>
            )}

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {kpis.map((kpi) => (
                    <KpiCard
                        key={kpi.label}
                        label={kpi.label}
                        value={kpi.value}
                        hint={kpi.hint}
                        icon={kpi.icon}
                        tone={kpi.tone}
                    />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="rml-card p-5 lg:col-span-1">
                    <div className="mb-4 flex items-center gap-2">
                        <Inbox className="h-4 w-4 text-rml-primary" />
                        <h2 className="text-sm font-semibold text-rml-text">
                            Action queue
                        </h2>
                    </div>
                    <ul className="space-y-3">
                        {actionQueue.map((item) => (
                            <li
                                key={item.id}
                                className="rounded-lg border border-rml-border bg-rml-background/60 px-3 py-2.5"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-rml-text">
                                            {item.title}
                                        </p>
                                        <p className="mt-0.5 text-xs text-rml-muted">
                                            {item.detail}
                                        </p>
                                    </div>
                                    <StatusBadge
                                        label={item.badge}
                                        tone={item.tone ?? 'warning'}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="rml-card p-5 lg:col-span-1">
                    <div className="mb-4 flex items-center gap-2">
                        <ClipboardList className="h-4 w-4 text-rml-blue" />
                        <h2 className="text-sm font-semibold text-rml-text">
                            Recent activity
                        </h2>
                    </div>
                    <ul className="space-y-3">
                        {recentActivity.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-start justify-between gap-2 border-b border-rml-border pb-3 last:border-0 last:pb-0"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-rml-text">
                                        {item.title}
                                    </p>
                                    <p className="text-xs text-rml-muted">
                                        {item.detail}
                                    </p>
                                </div>
                                <StatusBadge
                                    label={item.badge}
                                    tone={item.tone ?? 'info'}
                                />
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="rml-card p-5 lg:col-span-1">
                    <div className="mb-4 flex items-center gap-2">
                        <FileWarning className="h-4 w-4 text-rml-amber" />
                        <h2 className="text-sm font-semibold text-rml-text">
                            Pending evidence
                        </h2>
                    </div>
                    <ul className="space-y-3">
                        {pendingEvidence.map((item) => (
                            <li
                                key={item.id}
                                className="rounded-lg border border-dashed border-rml-border px-3 py-2.5"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="font-mono text-sm font-medium text-rml-text">
                                            {item.title}
                                        </p>
                                        <p className="mt-0.5 text-xs text-rml-muted">
                                            {item.detail}
                                        </p>
                                    </div>
                                    <StatusBadge
                                        label={item.badge}
                                        tone={item.tone ?? 'warning'}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            <section className="space-y-3 overflow-hidden">
                <div className="flex items-center gap-2">
                    <AlertTriangle className="h-4 w-4 text-rml-muted" />
                    <h2 className="text-sm font-semibold text-rml-text">
                        Leads awaiting audit
                    </h2>
                </div>

                <FilterBar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Search leads…"
                    onOpenMobileFilters={() => setFiltersOpen(true)}
                >
                    <Select
                        aria-label="Scheme"
                        value={scheme}
                        onChange={(e) => setScheme(e.target.value)}
                        options={[
                            { label: 'All schemes', value: '' },
                            { label: 'Insulation', value: 'Insulation' },
                            { label: 'Double Glazing', value: 'Double Glazing' },
                            { label: 'Heat Pumps', value: 'Heat Pumps' },
                        ]}
                        className="w-48"
                    />
                </FilterBar>

                <MobileFilterDrawer
                    open={filtersOpen}
                    onClose={() => setFiltersOpen(false)}
                    onReset={() => setScheme('')}
                >
                    <Select
                        label="Scheme"
                        value={scheme}
                        onChange={(e) => setScheme(e.target.value)}
                        options={[
                            { label: 'All schemes', value: '' },
                            { label: 'Insulation', value: 'Insulation' },
                            { label: 'Double Glazing', value: 'Double Glazing' },
                            { label: 'Heat Pumps', value: 'Heat Pumps' },
                        ]}
                    />
                </MobileFilterDrawer>

                {isMobile ? (
                    filteredLeads.length === 0 ? (
                        <EmptyState
                            title="No leads match"
                            description="Try another search or clear filters."
                            actionLabel="Clear search"
                            onAction={() => {
                                setSearch('');
                                setScheme('');
                            }}
                        />
                    ) : (
                        <MobileCardList
                            items={filteredLeads.map((lead) => ({
                                id: lead.id,
                                title: (
                                    <span className="font-mono">{lead.id}</span>
                                ),
                                subtitle: `${lead.scheme} · Zone ${lead.zone}`,
                                meta: (
                                    <StatusBadge
                                        label={lead.status}
                                        tone={lead.statusTone ?? 'warning'}
                                    />
                                ),
                                body: (
                                    <p>
                                        Size:{' '}
                                        <span className="font-semibold">
                                            {lead.size} m²
                                        </span>
                                    </p>
                                ),
                            }))}
                        />
                    )
                ) : (
                    <div className="overflow-x-auto">
                        <DataTable
                            data={filteredLeads}
                            getRowId={(row) => row.id}
                            columns={[
                                {
                                    id: 'id',
                                    header: 'Lead ID',
                                    cell: (row) => (
                                        <span className="font-mono font-medium">
                                            {row.id}
                                        </span>
                                    ),
                                },
                                {
                                    id: 'scheme',
                                    header: 'Scheme',
                                    cell: (row) => row.scheme,
                                },
                                {
                                    id: 'zone',
                                    header: 'Zone',
                                    cell: (row) => row.zone,
                                },
                                {
                                    id: 'size',
                                    header: 'Size',
                                    cell: (row) => `${row.size} m²`,
                                },
                                {
                                    id: 'status',
                                    header: 'Status',
                                    cell: (row) => (
                                        <StatusBadge
                                            label={row.status}
                                            tone={row.statusTone ?? 'warning'}
                                        />
                                    ),
                                },
                            ]}
                        />
                    </div>
                )}
            </section>
        </AppLayout>
    );
}
