import { LucideIcon, TrendingDown, TrendingUp } from 'lucide-react';
import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface KpiCardProps {
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'info';
    trend?: ReactNode;
    /** Signed percent change, e.g. 12.5 or -3 */
    deltaPercent?: number | null;
    deltaLabel?: string;
    className?: string;
}

const toneStyles: Record<
    NonNullable<KpiCardProps['tone']>,
    { icon: string; bar: string }
> = {
    default: {
        icon: 'bg-slate-100 text-slate-700',
        bar: 'from-slate-200/80 to-transparent',
    },
    success: {
        icon: 'bg-rml-primary-light text-rml-primary',
        bar: 'from-rml-primary/15 to-transparent',
    },
    warning: {
        icon: 'bg-amber-50 text-rml-amber',
        bar: 'from-amber-200/50 to-transparent',
    },
    danger: {
        icon: 'bg-red-50 text-rml-red',
        bar: 'from-red-200/50 to-transparent',
    },
    info: {
        icon: 'bg-rml-blue-light text-rml-blue',
        bar: 'from-sky-200/50 to-transparent',
    },
};

export function KpiCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'default',
    trend,
    deltaPercent,
    deltaLabel,
    className,
}: KpiCardProps) {
    const styles = toneStyles[tone];
    const hasDelta = typeof deltaPercent === 'number' && !Number.isNaN(deltaPercent);
    const up = hasDelta && deltaPercent >= 0;

    return (
        <div
            className={cn(
                'rml-card relative overflow-hidden p-5',
                className,
            )}
        >
            <div
                className={cn(
                    'pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-b',
                    styles.bar,
                )}
                aria-hidden
            />
            <div className="relative flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-2">
                    <p className="text-xs font-semibold uppercase tracking-wide text-rml-muted">
                        {label}
                    </p>
                    <p className="text-2xl font-bold tracking-tight text-rml-text sm:text-3xl">
                        {value}
                    </p>
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        {hasDelta && (
                            <span
                                className={cn(
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                    up
                                        ? 'bg-emerald-50 text-rml-primary'
                                        : 'bg-red-50 text-rml-red',
                                )}
                            >
                                {up ? (
                                    <TrendingUp className="h-3.5 w-3.5" aria-hidden />
                                ) : (
                                    <TrendingDown className="h-3.5 w-3.5" aria-hidden />
                                )}
                                {up ? '+' : ''}
                                {deltaPercent.toFixed(1)}%
                                {deltaLabel ? (
                                    <span className="font-normal text-rml-muted">
                                        {deltaLabel}
                                    </span>
                                ) : null}
                            </span>
                        )}
                        {trend}
                        {hint && (
                            <span className="text-rml-muted">{hint}</span>
                        )}
                    </div>
                </div>
                {Icon && (
                    <div
                        className={cn(
                            'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl shadow-sm ring-1 ring-black/5',
                            styles.icon,
                        )}
                    >
                        <Icon className="h-5 w-5" aria-hidden />
                    </div>
                )}
            </div>
        </div>
    );
}
