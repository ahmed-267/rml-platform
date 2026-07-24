import {
    CartesianGrid,
    Legend,
    Line,
    LineChart,
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

export interface RevenueMarginPoint {
    month: string;
    revenue: number;
    cost: number;
    margin: number;
}

interface RevenueMarginChartProps {
    data: RevenueMarginPoint[];
    title: string;
    subtitle: string;
    emptyTitle: string;
    emptyDescription: string;
    labels: {
        revenue: string;
        cost: string;
        margin: string;
    };
}

export function RevenueMarginChart({
    data,
    title,
    subtitle,
    emptyTitle,
    emptyDescription,
    labels,
}: RevenueMarginChartProps) {
    const hasData = data.some(
        (row) => row.revenue > 0 || row.cost > 0 || row.margin !== 0,
    );

    return (
        <ChartCard
            title={title}
            subtitle={subtitle}
            emptyTitle={emptyTitle}
            emptyDescription={emptyDescription}
            isEmpty={!hasData}
        >
            <ResponsiveContainer width="100%" height="100%">
                <LineChart
                    data={data}
                    margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                >
                    <CartesianGrid
                        strokeDasharray="3 3"
                        stroke={RML_CHART_COLORS.border}
                        vertical={false}
                    />
                    <XAxis
                        dataKey="month"
                        tick={{ fill: RML_CHART_COLORS.muted, fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                    />
                    <YAxis
                        tick={{ fill: RML_CHART_COLORS.muted, fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                        width={44}
                    />
                    <Tooltip
                        contentStyle={chartTooltipStyle}
                        formatter={(value) =>
                            typeof value === 'number'
                                ? value.toLocaleString(undefined, {
                                      style: 'currency',
                                      currency: 'EUR',
                                      maximumFractionDigits: 0,
                                  })
                                : value
                        }
                    />
                    <Legend
                        wrapperStyle={{ fontSize: 12, paddingTop: 8 }}
                        iconType="circle"
                    />
                    <Line
                        type="monotone"
                        dataKey="revenue"
                        name={labels.revenue}
                        stroke={RML_CHART_COLORS.blue}
                        strokeWidth={2.5}
                        dot={{ r: 3, fill: RML_CHART_COLORS.blue }}
                        activeDot={{ r: 5 }}
                    />
                    <Line
                        type="monotone"
                        dataKey="cost"
                        name={labels.cost}
                        stroke={RML_CHART_COLORS.amber}
                        strokeWidth={2.5}
                        dot={{ r: 3, fill: RML_CHART_COLORS.amber }}
                        activeDot={{ r: 5 }}
                    />
                    <Line
                        type="monotone"
                        dataKey="margin"
                        name={labels.margin}
                        stroke={RML_CHART_COLORS.primary}
                        strokeWidth={2.5}
                        dot={{ r: 3, fill: RML_CHART_COLORS.primary }}
                        activeDot={{ r: 5 }}
                    />
                </LineChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
