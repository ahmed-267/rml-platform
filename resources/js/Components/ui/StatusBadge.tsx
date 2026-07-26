import { cn } from '@/lib/cn';

/** Shared badge tones for StatusBadge and status helpers. */
export type BadgeTone =
    | 'neutral'
    | 'success'
    | 'warning'
    | 'danger'
    | 'info';

export interface StatusBadgeProps {
    label: string;
    tone?: BadgeTone;
    className?: string;
    mono?: boolean;
}

const toneClasses: Record<BadgeTone, string> = {
    neutral: 'bg-slate-100 text-slate-700 ring-slate-200',
    success: 'bg-rml-primary-light text-rml-primary ring-green-200',
    warning: 'bg-amber-50 text-rml-amber ring-amber-200',
    danger: 'bg-red-50 text-rml-red ring-red-200',
    info: 'bg-rml-blue-light text-rml-blue ring-blue-200',
};

export function StatusBadge({
    label,
    tone = 'neutral',
    className,
    mono = false,
}: StatusBadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
                toneClasses[tone],
                mono && 'font-mono',
                className,
            )}
        >
            {label}
        </span>
    );
}
