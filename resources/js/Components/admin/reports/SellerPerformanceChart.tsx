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

export interface SellerPerformancePoint {
    name: string;
    submitted: number;
    accepted: number;
    acceptance_rate: number;
}

interface SellerPerformanceChartProps {
    data: SellerPerformancePoint[];
    title: string;
    subtitle: string;
    emptyTitle: string;
    emptyDescription: string;
    labels: {
        accepted: string;
        submitted: string;
    };
}

export function SellerPerformanceChart({
    data,
    title,
    subtitle,
    emptyTitle,
    emptyDescription,
    labels,
}: SellerPerformanceChartProps) {
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
                    />
                    <Legend
                        wrapperStyle={{ fontSize: 12, paddingTop: 8 }}
                        iconType="circle"
                    />
                    <Bar
                        dataKey="accepted"
                        name={labels.accepted}
                        fill={RML_CHART_COLORS.primary}
                        radius={[0, 4, 4, 0]}
                        maxBarSize={18}
                    />
                    <Bar
                        dataKey="submitted"
                        name={labels.submitted}
                        fill={RML_CHART_COLORS.muted}
                        radius={[0, 4, 4, 0]}
                        maxBarSize={18}
                    />
                </BarChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
