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

export interface LeadVolumePoint {
    month: string;
    submitted: number;
    accepted: number;
    rejected: number;
}

interface LeadVolumeChartProps {
    data: LeadVolumePoint[];
    title: string;
    subtitle: string;
    emptyTitle: string;
    emptyDescription: string;
    labels: {
        submitted: string;
        accepted: string;
        rejected: string;
    };
}

export function LeadVolumeChart({
    data,
    title,
    subtitle,
    emptyTitle,
    emptyDescription,
    labels,
}: LeadVolumeChartProps) {
    const hasData = data.some(
        (row) => row.submitted > 0 || row.accepted > 0 || row.rejected > 0,
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
                <BarChart
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
                        allowDecimals={false}
                        tick={{ fill: RML_CHART_COLORS.muted, fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                        width={32}
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
                        dataKey="submitted"
                        name={labels.submitted}
                        fill={RML_CHART_COLORS.blue}
                        radius={[4, 4, 0, 0]}
                        maxBarSize={28}
                    />
                    <Bar
                        dataKey="accepted"
                        name={labels.accepted}
                        fill={RML_CHART_COLORS.primary}
                        radius={[4, 4, 0, 0]}
                        maxBarSize={28}
                    />
                    <Bar
                        dataKey="rejected"
                        name={labels.rejected}
                        fill={RML_CHART_COLORS.red}
                        radius={[4, 4, 0, 0]}
                        maxBarSize={28}
                    />
                </BarChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
