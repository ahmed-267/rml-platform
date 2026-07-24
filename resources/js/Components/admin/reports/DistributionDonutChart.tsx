import {
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
} from 'recharts';
import { ChartCard } from '@/Components/admin/reports/ChartCard';
import {
    chartTooltipStyle,
    ZONE_CHART_PALETTE,
} from '@/Components/admin/reports/chart-theme';

export interface NamedValuePoint {
    label: string;
    value: number;
}

interface DistributionDonutChartProps {
    data: NamedValuePoint[];
    title: string;
    subtitle: string;
    emptyTitle: string;
    emptyDescription: string;
    valueLabel: string;
}

export function DistributionDonutChart({
    data,
    title,
    subtitle,
    emptyTitle,
    emptyDescription,
    valueLabel,
}: DistributionDonutChartProps) {
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
                <PieChart>
                    <Pie
                        data={chartData}
                        dataKey="value"
                        nameKey="label"
                        cx="50%"
                        cy="46%"
                        innerRadius="52%"
                        outerRadius="72%"
                        paddingAngle={2}
                        stroke="#ffffff"
                        strokeWidth={2}
                    >
                        {chartData.map((entry, index) => (
                            <Cell
                                key={`${entry.label}-${index}`}
                                fill={
                                    ZONE_CHART_PALETTE[
                                        index % ZONE_CHART_PALETTE.length
                                    ]
                                }
                            />
                        ))}
                    </Pie>
                    <Tooltip
                        contentStyle={chartTooltipStyle}
                        formatter={(value) => [
                            typeof value === 'number' ? value : Number(value),
                            valueLabel,
                        ]}
                    />
                    <Legend
                        verticalAlign="bottom"
                        wrapperStyle={{ fontSize: 12 }}
                        iconType="circle"
                    />
                </PieChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
