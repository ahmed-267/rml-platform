import { Head, usePage } from '@inertiajs/react';
import { BuyerPerformanceChart } from '@/Components/admin/reports/BuyerPerformanceChart';
import { DistributionDonutChart } from '@/Components/admin/reports/DistributionDonutChart';
import { LeadVolumeChart } from '@/Components/admin/reports/LeadVolumeChart';
import { RevenueMarginChart } from '@/Components/admin/reports/RevenueMarginChart';
import { SchemeBarChart } from '@/Components/admin/reports/SchemeBarChart';
import { SellerPerformanceChart } from '@/Components/admin/reports/SellerPerformanceChart';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    KpiCard,
    StatusBadge,
} from '@/Components/ui';
import { formatMoney } from '@/lib/admin-helpers';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface ReportsPageProps {
    summary: {
        total_leads_submitted: number;
        leads_sold: number;
        acceptance_rate: number;
        buyer_revenue_paid: number;
        seller_payouts_paid: number;
        total_margin: number;
        commissions_due: number;
        commissions_paid: number;
        total_purchases: number;
        approved_sellers: number;
        approved_buyers: number;
    };
    lead_pipeline: Record<string, number>;
    registrations_by_status: Record<string, number>;
    monthly_sold: Record<string, number>;
    charts: {
        lead_volume: Array<{
            month: string;
            submitted: number;
            accepted: number;
            rejected: number;
        }>;
        leads_by_zone: Array<{ label: string; value: number }>;
        leads_by_scheme: Array<{ label: string; value: number }>;
        revenue_margin: Array<{
            month: string;
            revenue: number;
            cost: number;
            margin: number;
        }>;
        seller_performance: Array<{
            name: string;
            submitted: number;
            accepted: number;
            acceptance_rate: number;
        }>;
        buyer_performance: Array<{
            name: string;
            leads_bought: number;
            spent: number;
            avg_per_lead: number;
        }>;
    };
    seller_performance: Array<{
        name: string;
        submitted: number;
        accepted: number;
        acceptance_rate: number;
    }>;
    buyer_performance: Array<{
        name: string;
        leads_bought: number;
        spent: number;
        avg_per_lead: number;
    }>;
}

export default function ReportsIndex({
    summary,
    lead_pipeline,
    registrations_by_status,
    monthly_sold,
    charts,
    seller_performance,
    buyer_performance,
}: ReportsPageProps) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.admin.reports;
    const common = translations.admin.common;
    const leadStatuses = translations.lead_statuses;
    const statuses = translations.statuses;

    const pipelineRows = Object.entries(lead_pipeline).map(
        ([status, count]) => ({
            status,
            count,
        }),
    );

    const registrationRows = Object.entries(registrations_by_status).map(
        ([status, count]) => ({
            status,
            count,
        }),
    );

    const monthlyRows = Object.entries(monthly_sold).map(([month, count]) => ({
        month,
        count,
    }));

    return (
        <AppLayout title={t.index_title} subtitle={t.index_subtitle}>
            <Head title={t.index_title} />

            <div className="flex flex-wrap gap-2">
                <a href={route('admin.reports.export.pdf')}>
                    <Button variant="outline" size="sm" type="button">
                        {t.export_pdf}
                    </Button>
                </a>
                <a href={route('admin.reports.export.csv')}>
                    <Button variant="outline" size="sm" type="button">
                        {t.export_csv}
                    </Button>
                </a>
            </div>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.summary}
                </h2>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        label={t.total_leads_submitted}
                        value={summary.total_leads_submitted}
                        tone="info"
                    />
                    <KpiCard
                        label={t.leads_sold}
                        value={summary.leads_sold}
                        tone="success"
                    />
                    <KpiCard
                        label={t.acceptance_rate}
                        value={`${summary.acceptance_rate}%`}
                        tone="default"
                    />
                    <KpiCard
                        label={t.buyer_revenue}
                        value={formatMoney(summary.buyer_revenue_paid)}
                        tone="info"
                    />
                    <KpiCard
                        label={t.seller_payouts}
                        value={formatMoney(summary.seller_payouts_paid)}
                        tone="default"
                    />
                    <KpiCard
                        label={t.total_margin}
                        value={formatMoney(summary.total_margin)}
                        tone="success"
                    />
                    <KpiCard
                        label={t.commissions_due}
                        value={formatMoney(summary.commissions_due)}
                        tone="warning"
                    />
                    <KpiCard
                        label={t.commissions_paid}
                        value={formatMoney(summary.commissions_paid)}
                        tone="success"
                    />
                    <KpiCard
                        label={t.total_purchases}
                        value={summary.total_purchases}
                        tone="info"
                    />
                    <KpiCard
                        label={t.approved_sellers}
                        value={summary.approved_sellers}
                        tone="default"
                    />
                    <KpiCard
                        label={t.approved_buyers}
                        value={summary.approved_buyers}
                        tone="default"
                    />
                </div>
            </section>

            <section className="space-y-3">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.charts_section}
                    </h2>
                    <p className="mt-1 text-sm text-rml-muted">
                        {t.charts_section_subtitle}
                    </p>
                </div>
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <LeadVolumeChart
                        data={charts.lead_volume}
                        title={t.chart_lead_volume_title}
                        subtitle={t.chart_lead_volume_subtitle}
                        emptyTitle={t.chart_empty_title}
                        emptyDescription={t.chart_empty_description}
                        labels={{
                            submitted: t.legend_submitted,
                            accepted: t.legend_accepted,
                            rejected: t.legend_rejected,
                        }}
                    />
                    <DistributionDonutChart
                        data={charts.leads_by_zone}
                        title={t.chart_leads_by_zone_title}
                        subtitle={t.chart_leads_by_zone_subtitle}
                        emptyTitle={t.chart_empty_title}
                        emptyDescription={t.chart_empty_description}
                        valueLabel={t.count}
                    />
                    <RevenueMarginChart
                        data={charts.revenue_margin}
                        title={t.chart_revenue_margin_title}
                        subtitle={t.chart_revenue_margin_subtitle}
                        emptyTitle={t.chart_empty_title}
                        emptyDescription={t.chart_empty_description}
                        labels={{
                            revenue: t.legend_revenue,
                            cost: t.legend_cost,
                            margin: t.legend_margin,
                        }}
                    />
                    <SchemeBarChart
                        data={charts.leads_by_scheme}
                        title={t.chart_leads_by_scheme_title}
                        subtitle={t.chart_leads_by_scheme_subtitle}
                        emptyTitle={t.chart_empty_title}
                        emptyDescription={t.chart_empty_description}
                        valueLabel={t.count}
                    />
                </div>
            </section>

            <section className="space-y-3">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.performance_section}
                    </h2>
                    <p className="mt-1 text-sm text-rml-muted">
                        {t.performance_section_subtitle}
                    </p>
                </div>
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <SellerPerformanceChart
                        data={charts.seller_performance}
                        title={t.chart_seller_performance_title}
                        subtitle={t.chart_seller_performance_subtitle}
                        emptyTitle={t.chart_empty_title}
                        emptyDescription={t.chart_empty_description}
                        labels={{
                            accepted: t.legend_accepted,
                            submitted: t.legend_submitted,
                        }}
                    />
                    <BuyerPerformanceChart
                        data={charts.buyer_performance}
                        title={t.chart_buyer_performance_title}
                        subtitle={t.chart_buyer_performance_subtitle}
                        emptyTitle={t.chart_empty_title}
                        emptyDescription={t.chart_empty_description}
                        labels={{
                            leadsBought: t.legend_leads_bought,
                            spent: t.legend_spent,
                            avgPerLead: t.legend_avg_per_lead,
                        }}
                    />
                </div>
            </section>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.seller_performance_table}
                </h2>
                {seller_performance.length === 0 ? (
                    <EmptyState
                        title={t.chart_empty_title}
                        description={t.chart_empty_description}
                    />
                ) : (
                    <DataTable
                        data={seller_performance}
                        getRowId={(r) => r.name}
                        columns={[
                            {
                                id: 'name',
                                header: common.company,
                                cell: (r) => r.name,
                            },
                            {
                                id: 'submitted',
                                header: t.legend_submitted,
                                cell: (r) => r.submitted,
                            },
                            {
                                id: 'accepted',
                                header: t.legend_accepted,
                                cell: (r) => r.accepted,
                            },
                            {
                                id: 'rate',
                                header: t.acceptance_rate,
                                cell: (r) => `${r.acceptance_rate}%`,
                            },
                        ]}
                    />
                )}
            </section>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.buyer_performance_table}
                </h2>
                {buyer_performance.length === 0 ? (
                    <EmptyState
                        title={t.chart_empty_title}
                        description={t.chart_empty_description}
                    />
                ) : (
                    <DataTable
                        data={buyer_performance}
                        getRowId={(r) => r.name}
                        columns={[
                            {
                                id: 'name',
                                header: common.company,
                                cell: (r) => r.name,
                            },
                            {
                                id: 'leads',
                                header: t.legend_leads_bought,
                                cell: (r) => r.leads_bought,
                            },
                            {
                                id: 'spent',
                                header: t.legend_spent,
                                cell: (r) => formatMoney(r.spent),
                            },
                            {
                                id: 'avg',
                                header: t.legend_avg_per_lead,
                                cell: (r) => formatMoney(r.avg_per_lead),
                            },
                        ]}
                    />
                )}
            </section>

            <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
                <section className="space-y-3">
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.lead_pipeline}
                    </h2>
                    {pipelineRows.length === 0 ? (
                        <p className="text-sm text-rml-muted">{common.empty}</p>
                    ) : (
                        <DataTable
                            data={pipelineRows}
                            getRowId={(r) => r.status}
                            columns={[
                                {
                                    id: 'status',
                                    header: common.status,
                                    cell: (r) => (
                                        <StatusBadge
                                            label={leadStatusLabel(
                                                r.status,
                                                leadStatuses,
                                            )}
                                            tone={leadStatusTone(r.status)}
                                        />
                                    ),
                                },
                                {
                                    id: 'count',
                                    header: t.count,
                                    cell: (r) => r.count,
                                },
                            ]}
                        />
                    )}
                </section>

                <div className="space-y-6">
                    <section className="space-y-3">
                        <h2 className="text-base font-semibold text-rml-text">
                            {t.registrations}
                        </h2>
                        {registrationRows.length === 0 ? (
                            <p className="text-sm text-rml-muted">
                                {common.empty}
                            </p>
                        ) : (
                            <DataTable
                                data={registrationRows}
                                getRowId={(r) => r.status}
                                columns={[
                                    {
                                        id: 'status',
                                        header: common.status,
                                        cell: (r) => (
                                            <StatusBadge
                                                label={
                                                    statuses[r.status] ??
                                                    r.status
                                                }
                                                tone={leadStatusTone(r.status)}
                                            />
                                        ),
                                    },
                                    {
                                        id: 'count',
                                        header: t.count,
                                        cell: (r) => r.count,
                                    },
                                ]}
                            />
                        )}
                    </section>

                    <section className="space-y-3">
                        <h2 className="text-base font-semibold text-rml-text">
                            {t.monthly_sold}
                        </h2>
                        {monthlyRows.length === 0 ? (
                            <p className="text-sm text-rml-muted">
                                {common.empty}
                            </p>
                        ) : (
                            <DataTable
                                data={monthlyRows}
                                getRowId={(r) => r.month}
                                columns={[
                                    {
                                        id: 'month',
                                        header: t.month,
                                        cell: (r) => r.month,
                                    },
                                    {
                                        id: 'count',
                                        header: t.count,
                                        cell: (r) => r.count,
                                    },
                                ]}
                            />
                        )}
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
