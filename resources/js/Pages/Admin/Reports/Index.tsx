import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
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
    FilterBar,
    FormInput,
    KpiCard,
    Select,
    StatusBadge,
    Tabs,
} from '@/Components/ui';
import { formatMoney } from '@/lib/admin-helpers';
import { useClientTableSort } from '@/hooks/use-list-sort';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

type ReportTab = 'overview' | 'charts' | 'performance' | 'tables';

const REPORT_TABS: ReportTab[] = [
    'overview',
    'charts',
    'performance',
    'tables',
];

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
    filters: {
        date_from?: string | null;
        date_to?: string | null;
        scheme_id?: number | string | null;
        tab?: string | null;
    };
    filterOptions: {
        schemes: Array<{ id: number; name: string }>;
    };
}

export default function ReportsIndex({
    summary,
    lead_pipeline,
    registrations_by_status,
    monthly_sold,
    charts,
    seller_performance,
    buyer_performance,
    filters,
    filterOptions,
}: ReportsPageProps) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.admin.reports;
    const common = translations.admin.common;
    const leadStatuses = translations.lead_statuses;
    const statuses = translations.statuses;

    const activeTab: ReportTab = REPORT_TABS.includes(filters.tab as ReportTab)
        ? (filters.tab as ReportTab)
        : 'overview';

    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );

    useEffect(() => {
        setDateFrom(filters.date_from ?? '');
        setDateTo(filters.date_to ?? '');
        setSchemeId(
            filters.scheme_id != null ? String(filters.scheme_id) : '',
        );
    }, [filters.date_from, filters.date_to, filters.scheme_id]);

    const queryParams = (
        overrides: Record<string, string | undefined> = {},
    ): Record<string, string | undefined> => ({
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        scheme_id: schemeId || undefined,
        tab: activeTab,
        ...overrides,
    });

    const exportParams = (): Record<string, string | undefined> => ({
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        scheme_id:
            filters.scheme_id != null ? String(filters.scheme_id) : undefined,
    });

    const applyFilters = () => {
        router.get(route('admin.reports.index'), queryParams({ tab: activeTab }), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setDateFrom('');
        setDateTo('');
        setSchemeId('');
        router.get(
            route('admin.reports.index'),
            { tab: activeTab },
            { preserveState: true, replace: true },
        );
    };

    const changeTab = (tab: string) => {
        router.get(
            route('admin.reports.index'),
            queryParams({ tab }),
            { preserveState: true, replace: true },
        );
    };

    const schemeOptions = [
        { value: '', label: t.all_schemes },
        ...filterOptions.schemes.map((scheme) => ({
            value: String(scheme.id),
            label: scheme.name,
        })),
    ];

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

    const sortLabels = {
        sortAscLabel: common.sort_asc,
        sortDescLabel: common.sort_desc,
    };

    const {
        sortedRows: sortedPipelineRows,
        sortableHeader: pipelineSortableHeader,
    } = useClientTableSort(pipelineRows, {
        defaultSort: 'status',
        defaultDirection: 'asc',
        ...sortLabels,
        accessors: {
            status: (row) =>
                leadStatusLabel(row.status, leadStatuses).toLowerCase(),
            count: (row) => row.count,
        },
    });

    const {
        sortedRows: sortedRegistrationRows,
        sortableHeader: registrationSortableHeader,
    } = useClientTableSort(registrationRows, {
        defaultSort: 'status',
        defaultDirection: 'asc',
        ...sortLabels,
        accessors: {
            status: (row) => (statuses[row.status] ?? row.status).toLowerCase(),
            count: (row) => row.count,
        },
    });

    const {
        sortedRows: sortedMonthlyRows,
        sortableHeader: monthlySortableHeader,
    } = useClientTableSort(monthlyRows, {
        defaultSort: 'month',
        defaultDirection: 'asc',
        ...sortLabels,
        accessors: {
            month: (row) => row.month,
            count: (row) => row.count,
        },
    });

    const {
        sortedRows: sortedSellerPerformance,
        sortableHeader: sellerSortableHeader,
    } = useClientTableSort(seller_performance, {
        defaultSort: 'name',
        defaultDirection: 'asc',
        ...sortLabels,
        accessors: {
            name: (row) => row.name,
            submitted: (row) => row.submitted,
            accepted: (row) => row.accepted,
            rate: (row) => row.acceptance_rate,
        },
    });

    const {
        sortedRows: sortedBuyerPerformance,
        sortableHeader: buyerSortableHeader,
    } = useClientTableSort(buyer_performance, {
        defaultSort: 'name',
        defaultDirection: 'asc',
        ...sortLabels,
        accessors: {
            name: (row) => row.name,
            leads: (row) => row.leads_bought,
            spent: (row) => row.spent,
            avg: (row) => row.avg_per_lead,
        },
    });

    const overviewPanel = (
        <div className="space-y-6">
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
                <h2 className="text-base font-semibold text-rml-text">
                    {t.lead_pipeline}
                </h2>
                {pipelineRows.length === 0 ? (
                    <p className="text-sm text-rml-muted">{common.empty}</p>
                ) : (
                    <DataTable
                        data={sortedPipelineRows}
                        getRowId={(r) => r.status}
                        columns={[
                            {
                                id: 'status',
                                header: pipelineSortableHeader(
                                    common.status,
                                    'status',
                                ),
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
                                header: pipelineSortableHeader(t.count, 'count'),
                                cell: (r) => r.count,
                            },
                        ]}
                    />
                )}
            </section>
        </div>
    );

    const chartsPanel = (
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
    );

    const performancePanel = (
        <div className="space-y-6">
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
                        data={sortedSellerPerformance}
                        getRowId={(r) => r.name}
                        columns={[
                            {
                                id: 'name',
                                header: sellerSortableHeader(
                                    common.company,
                                    'name',
                                ),
                                cell: (r) => r.name,
                            },
                            {
                                id: 'submitted',
                                header: sellerSortableHeader(
                                    t.legend_submitted,
                                    'submitted',
                                ),
                                cell: (r) => r.submitted,
                            },
                            {
                                id: 'accepted',
                                header: sellerSortableHeader(
                                    t.legend_accepted,
                                    'accepted',
                                ),
                                cell: (r) => r.accepted,
                            },
                            {
                                id: 'rate',
                                header: sellerSortableHeader(
                                    t.acceptance_rate,
                                    'rate',
                                ),
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
                        data={sortedBuyerPerformance}
                        getRowId={(r) => r.name}
                        columns={[
                            {
                                id: 'name',
                                header: buyerSortableHeader(
                                    common.company,
                                    'name',
                                ),
                                cell: (r) => r.name,
                            },
                            {
                                id: 'leads',
                                header: buyerSortableHeader(
                                    t.legend_leads_bought,
                                    'leads',
                                ),
                                cell: (r) => r.leads_bought,
                            },
                            {
                                id: 'spent',
                                header: buyerSortableHeader(
                                    t.legend_spent,
                                    'spent',
                                ),
                                cell: (r) => formatMoney(r.spent),
                            },
                            {
                                id: 'avg',
                                header: buyerSortableHeader(
                                    t.legend_avg_per_lead,
                                    'avg',
                                ),
                                cell: (r) => formatMoney(r.avg_per_lead),
                            },
                        ]}
                    />
                )}
            </section>
        </div>
    );

    const tablesPanel = (
        <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.registrations}
                </h2>
                {registrationRows.length === 0 ? (
                    <p className="text-sm text-rml-muted">{common.empty}</p>
                ) : (
                    <DataTable
                        data={sortedRegistrationRows}
                        getRowId={(r) => r.status}
                        columns={[
                            {
                                id: 'status',
                                header: registrationSortableHeader(
                                    common.status,
                                    'status',
                                ),
                                cell: (r) => (
                                    <StatusBadge
                                        label={
                                            statuses[r.status] ?? r.status
                                        }
                                        tone={leadStatusTone(r.status)}
                                    />
                                ),
                            },
                            {
                                id: 'count',
                                header: registrationSortableHeader(
                                    t.count,
                                    'count',
                                ),
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
                    <p className="text-sm text-rml-muted">{common.empty}</p>
                ) : (
                    <DataTable
                        data={sortedMonthlyRows}
                        getRowId={(r) => r.month}
                        columns={[
                            {
                                id: 'month',
                                header: monthlySortableHeader(t.month, 'month'),
                                cell: (r) => r.month,
                            },
                            {
                                id: 'count',
                                header: monthlySortableHeader(t.count, 'count'),
                                cell: (r) => r.count,
                            },
                        ]}
                    />
                )}
            </section>
        </div>
    );

    return (
        <AppLayout title={t.index_title} subtitle={t.index_subtitle}>
            <Head title={t.index_title} />

            <div className="space-y-4">
                <FilterBar
                    compact
                    actions={
                        <>
                            <Button size="sm" onClick={applyFilters}>
                                {common.apply}
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={resetFilters}
                            >
                                {common.reset}
                            </Button>
                        </>
                    }
                    endActions={
                        <>
                            <a href={route('admin.reports.export.pdf', exportParams())}>
                                <Button variant="outline" size="sm" type="button">
                                    {t.export_pdf}
                                </Button>
                            </a>
                            <a href={route('admin.reports.export.csv', exportParams())}>
                                <Button variant="outline" size="sm" type="button">
                                    {t.export_csv}
                                </Button>
                            </a>
                        </>
                    }
                >
                    <div className="w-full sm:w-[11rem]">
                        <FormInput
                            type="date"
                            label={t.filter_date_from}
                            value={dateFrom}
                            onChange={(event) =>
                                setDateFrom(event.target.value)
                            }
                        />
                    </div>
                    <div className="w-full sm:w-[11rem]">
                        <FormInput
                            type="date"
                            label={t.filter_date_to}
                            value={dateTo}
                            onChange={(event) => setDateTo(event.target.value)}
                        />
                    </div>
                    <div className="w-full sm:w-[12.5rem]">
                        <Select
                            label={t.filter_scheme}
                            aria-label={t.filter_scheme}
                            value={schemeId}
                            onChange={(event) =>
                                setSchemeId(event.target.value)
                            }
                            options={schemeOptions}
                        />
                    </div>
                </FilterBar>

                <Tabs
                    items={[
                        { id: 'overview', label: t.tab_overview },
                        { id: 'charts', label: t.tab_charts },
                        { id: 'performance', label: t.tab_performance },
                        { id: 'tables', label: t.tab_tables },
                    ]}
                    value={activeTab}
                    onChange={changeTab}
                >
                    {activeTab === 'overview' && overviewPanel}
                    {activeTab === 'charts' && chartsPanel}
                    {activeTab === 'performance' && performancePanel}
                    {activeTab === 'tables' && tablesPanel}
                </Tabs>
            </div>
        </AppLayout>
    );
}
