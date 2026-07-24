import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartCard } from '@/Components/admin/reports/ChartCard';
import {
    chartTooltipStyle,
    RML_CHART_COLORS,
} from '@/Components/admin/reports/chart-theme';

export interface BuyerPerformancePoint {
    name: string;
    leads_bought: number;
    spent: number;
    avg_per_lead: number;
}

interface BuyerPerformanceChartProps {
    data: BuyerPerformancePoint[];
    title: string;
    subtitle: string;
    emptyTitle: string;
    emptyDescription: string;
    labels: {
        leadsBought: string;
        spent: string;
        avgPerLead: string;
    };
}

export function BuyerPerformanceChart({
    data,
    title,
    subtitle,
    emptyTitle,
    emptyDescription,
    labels,
}: BuyerPerformanceChartProps) {
    const hasData = data.length > 0;

    return (
        <ChartCard
            title={title}
            subtitle={subtitle}
            emptyTitle={emptyTitle}
            emptyDescription={emptyDescription}
            isEmpty={!hasData}
            className="min-h-[360px]"
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart
                    layout="vertical"
                    data={data}
                    margin={{ top: 8, right: 16, left: 8, bottom: 0 }}
                >
                    <CartesianGrid
                        strokeDasharray="3 3"
                        stroke={RML_CHART_COLORS.border}
                        horizontal={false}
                    />
                    <XAxis
                        type="number"
                        allowDecimals={false}
                        tick={{ fill: RML_CHART_COLORS.muted, fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                    />
                    <YAxis
                        type="category"
                        dataKey="name"
                        width={96}
                        tick={{ fill: RML_CHART_COLORS.muted, fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                    />
                    <Tooltip
                        contentStyle={chartTooltipStyle}
                        cursor={{ fill: RML_CHART_COLORS.background }}
                        formatter={(value, _name, item) => {
                            const payload = item?.payload as
                                | BuyerPerformancePoint
                                | undefined;
                            if (!payload) {
                                return [value, labels.leadsBought];
                            }

                            return [
                                `${payload.leads_bought} · ${payload.spent.toLocaleString(
                                    undefined,
                                    {
                                        style: 'currency',
                                        currency: 'EUR',
                                        maximumFractionDigits: 0,
                                    },
                                )} · ${labels.avgPerLead}: ${payload.avg_per_lead.toLocaleString(
                                    undefined,
                                    {
                                        style: 'currency',
                                        currency: 'EUR',
                                        maximumFractionDigits: 0,
                                    },
                                )}`,
                                labels.leadsBought,
                            ];
                        }}
                    />
                    <Bar
                        dataKey="leads_bought"
                        name={labels.leadsBought}
                        fill={RML_CHART_COLORS.blue}
                        radius={[0, 4, 4, 0]}
                        maxBarSize={22}
                    />
                </BarChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
