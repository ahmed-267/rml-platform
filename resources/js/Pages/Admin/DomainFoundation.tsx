import { Head, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardCheck,
    Database,
    FileText,
    MessageSquare,
    Package,
    ShoppingCart,
    Wallet,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { BackLink } from '@/Components/ui';
import { DataTable } from '@/Components/ui/DataTable';
import { KpiCard } from '@/Components/ui/KpiCard';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { useIsMobile } from '@/hooks/use-media-query';
import { MobileCardList } from '@/Components/ui/MobileCardList';
import type { PageProps } from '@/types';

type Counts = Record<string, number>;

type LeadSample = {
    reference: string;
    status: string;
    size_m2: number | string | null;
};

type ZonePrice = {
    code: string;
    price_per_m2: number | string | null;
};

export default function DomainFoundation({
    counts,
    samples,
}: {
    counts: Counts;
    samples: {
        leads: LeadSample[];
        zone_prices: ZonePrice[];
    };
}) {
    const isMobile = useIsMobile();
    const { translations } = usePage<PageProps>().props;
    const common = translations.admin.common;

    return (
        <AppLayout
            title="Domain foundation"
            subtitle="Seeded domain entities and pricing diagnostics"
        >
            <Head title="Domain Foundation" />

            <div className="flex flex-wrap items-center gap-3">
                <BackLink href={route('admin.dashboard')} label={common.back} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Schemes"
                    value={counts.schemes}
                    icon={Database}
                    tone="info"
                />
                <KpiCard
                    label="Zones"
                    value={counts.zones}
                    icon={Database}
                    tone="default"
                />
                <KpiCard
                    label="Leads"
                    value={counts.leads}
                    icon={FileText}
                    tone="success"
                />
                <KpiCard
                    label="Packages"
                    value={counts.packages}
                    icon={Package}
                    tone="info"
                />
                <KpiCard
                    label="Seller companies"
                    value={counts.seller_companies}
                    icon={Building2}
                    tone="default"
                />
                <KpiCard
                    label="Buyer companies"
                    value={counts.buyer_companies}
                    icon={ShoppingCart}
                    tone="default"
                />
                <KpiCard
                    label="Payments"
                    value={counts.payments}
                    icon={Wallet}
                    tone="warning"
                />
                <KpiCard
                    label="Audits"
                    value={counts.audits}
                    icon={ClipboardCheck}
                    tone="info"
                />
                <KpiCard
                    label="Message threads"
                    value={counts.message_threads}
                    icon={MessageSquare}
                    tone="default"
                />
                <KpiCard
                    label="Purchases"
                    value={counts.purchases}
                    icon={ShoppingCart}
                    tone="success"
                />
                <KpiCard
                    label="Commissions"
                    value={counts.commissions}
                    icon={Wallet}
                    tone="warning"
                />
                <KpiCard
                    label="Audit logs"
                    value={counts.audit_logs}
                    icon={ClipboardCheck}
                    tone="danger"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <section className="rml-card p-5">
                    <h2 className="mb-4 text-sm font-semibold text-rml-text">
                        Zone pricing (seeded)
                    </h2>
                    <ul className="space-y-2">
                        {samples.zone_prices.map((zone) => (
                            <li
                                key={zone.code}
                                className="flex items-center justify-between rounded-lg border border-rml-border px-3 py-2 text-sm"
                            >
                                <span className="font-mono font-semibold">
                                    {zone.code}
                                </span>
                                <StatusBadge
                                    label={`€${Number(zone.price_per_m2 ?? 0).toFixed(2)}/m²`}
                                    tone="success"
                                />
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="space-y-3 overflow-hidden">
                    <h2 className="text-sm font-semibold text-rml-text">
                        Sample leads
                    </h2>
                    {isMobile ? (
                        <MobileCardList
                            items={samples.leads.map((lead) => ({
                                id: lead.reference,
                                title: (
                                    <span className="font-mono">
                                        {lead.reference}
                                    </span>
                                ),
                                subtitle: `${lead.size_m2 ?? '—'} m²`,
                                meta: (
                                    <StatusBadge
                                        label={lead.status.replaceAll('_', ' ')}
                                        tone="info"
                                    />
                                ),
                            }))}
                        />
                    ) : (
                        <DataTable
                            data={samples.leads}
                            getRowId={(row) => row.reference}
                            columns={[
                                {
                                    id: 'reference',
                                    header: 'Lead ID',
                                    cell: (row) => (
                                        <span className="font-mono font-medium">
                                            {row.reference}
                                        </span>
                                    ),
                                },
                                {
                                    id: 'size',
                                    header: 'Size',
                                    cell: (row) =>
                                        row.size_m2
                                            ? `${row.size_m2} m²`
                                            : '—',
                                },
                                {
                                    id: 'status',
                                    header: 'Status',
                                    cell: (row) => (
                                        <StatusBadge
                                            label={row.status.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                            tone="info"
                                        />
                                    ),
                                },
                            ]}
                        />
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
