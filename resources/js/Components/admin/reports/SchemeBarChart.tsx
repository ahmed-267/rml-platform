import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
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
import type { NamedValuePoint } from '@/Components/admin/reports/DistributionDonutChart';

interface SchemeBarChartProps {
    data: NamedValuePoint[];
    title: string;
    subtitle: string;
    emptyTitle: string;
    emptyDescription: string;
    valueLabel: string;
}

export function SchemeBarChart({
    data,
    title,
    subtitle,
    emptyTitle,
    emptyDescription,
    valueLabel,
}: SchemeBarChartProps) {
    const chartData = data.filter((row) => row.value > 0);
    const hasData = chartData.length > 0;

    return (
        <ChartCard
            title={title}
            subtitle={subtitle}
            emptyTitle={emptyTitle}
            emptyDescription={emptyDescription}
            isEmpty={!hasData}
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart
                    data={chartData}
                    margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                >
                    <CartesianGrid
                        strokeDasharray="3 3"
                        stroke={RML_CHART_COLORS.border}
                        vertical={false}
                    />
                    <XAxis
                        dataKey="label"
                        tick={{ fill: RML_CHART_COLORS.muted, fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                        interval={0}
                        angle={-12}
                        textAnchor="end"
                        height={48}
                    />
                    <YAxis
                        allowDecimals={false}
                        tick={{ fill: RML_CHART_COLORS.muted, fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                        width={32}
                    />
                    <Tooltip
                        contentStyle={chartTooltipStyle}
                        cursor={{ fill: RML_CHART_COLORS.background }}
                        formatter={(value) => [
                            typeof value === 'number' ? value : Number(value),
                            valueLabel,
                        ]}
                    />
                    <Legend
                        wrapperStyle={{ fontSize: 12, paddingTop: 4 }}
                        iconType="circle"
                    />
                    <Bar
                        dataKey="value"
                        name={valueLabel}
                        fill={RML_CHART_COLORS.primary}
                        radius={[6, 6, 0, 0]}
                        maxBarSize={48}
                    />
                </BarChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
