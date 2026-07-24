import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface KpiCardProps {
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'info';
    trend?: ReactNode;
    className?: string;
}

const toneIconBg: Record<NonNullable<KpiCardProps['tone']>, string> = {
    default: 'bg-slate-100 text-slate-700',
    success: 'bg-rml-primary-light text-rml-primary',
    warning: 'bg-amber-50 text-rml-amber',
    danger: 'bg-red-50 text-rml-red',
    info: 'bg-rml-blue-light text-rml-blue',
};

export function KpiCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'default',
    trend,
    className,
}: KpiCardProps) {
    return (
        <div className={cn('rml-card p-5', className)}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-2">
                    <p className="text-sm font-medium text-rml-muted">{label}</p>
                    <p className="text-2xl font-bold tracking-tight text-rml-text">
                        {value}
                    </p>
                    {(hint || trend) && (
                        <div className="flex flex-wrap items-center gap-2 text-sm text-rml-muted">
                            {trend}
                            {hint}
                        </div>
                    )}
                </div>
                {Icon && (
                    <div
                        className={cn(
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                            toneIconBg[tone],
                        )}
                    >
                        <Icon className="h-5 w-5" />
                    </div>
                )}
            </div>
        </div>
    );
}
