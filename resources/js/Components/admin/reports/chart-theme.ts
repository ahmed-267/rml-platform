export const RML_CHART_COLORS = {
    primary: '#16a34a',
    blue: '#2563eb',
    amber: '#d97706',
    red: '#dc2626',
    muted: '#6b7280',
    navy: '#0f172a',
    border: '#e5e7eb',
    background: '#f8fafc',
    softGreen: '#dcfce7',
    softBlue: '#eff6ff',
} as const;

export const ZONE_CHART_PALETTE = [
    RML_CHART_COLORS.primary,
    RML_CHART_COLORS.blue,
    RML_CHART_COLORS.amber,
    RML_CHART_COLORS.navy,
    RML_CHART_COLORS.muted,
    RML_CHART_COLORS.red,
] as const;

export const chartTooltipStyle = {
    borderRadius: 8,
    border: `1px solid ${RML_CHART_COLORS.border}`,
    boxShadow: '0 4px 12px rgba(15, 23, 42, 0.08)',
    fontSize: 12,
} as const;
