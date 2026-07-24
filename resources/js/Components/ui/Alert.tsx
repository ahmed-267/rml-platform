import { ReactNode } from 'react';
import { AlertCircle, AlertTriangle, CheckCircle2, Info, X } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import type { PageProps } from '@/types';

export type AlertVariant = 'info' | 'success' | 'warning' | 'error';

export interface AlertProps {
    title?: string;
    children: ReactNode;
    variant?: AlertVariant;
    onDismiss?: () => void;
    className?: string;
}

const variantConfig: Record<
    AlertVariant,
    { icon: typeof Info; classes: string; iconClass: string }
> = {
    info: {
        icon: Info,
        classes: 'bg-rml-blue-light border-blue-200 text-rml-blue',
        iconClass: 'text-rml-blue',
    },
    success: {
        icon: CheckCircle2,
        classes: 'bg-rml-primary-lighter border-green-200 text-green-800',
        iconClass: 'text-rml-primary',
    },
    warning: {
        icon: AlertTriangle,
        classes: 'bg-amber-50 border-amber-200 text-amber-900',
        iconClass: 'text-rml-amber',
    },
    error: {
        icon: AlertCircle,
        classes: 'bg-red-50 border-red-200 text-red-900',
        iconClass: 'text-rml-red',
    },
};

export function Alert({
    title,
    children,
    variant = 'info',
    onDismiss,
    className,
}: AlertProps) {
    const config = variantConfig[variant];
    const Icon = config.icon;
    const { translations } = usePage<PageProps>().props;
    const dismissLabel = translations.common?.dismiss ?? 'Dismiss';

    return (
        <div
            role="alert"
            className={cn(
                'flex gap-3 rounded-xl border px-4 py-3',
                config.classes,
                className,
            )}
        >
            <Icon className={cn('mt-0.5 h-5 w-5 shrink-0', config.iconClass)} />
            <div className="min-w-0 flex-1">
                {title && (
                    <p className="text-sm font-semibold text-rml-text">{title}</p>
                )}
                <div className="text-sm text-rml-text/90">{children}</div>
            </div>
            {onDismiss && (
                <button
                    type="button"
                    onClick={onDismiss}
                    className="rounded-md p-1 text-rml-muted hover:bg-white/60 hover:text-rml-text"
                    aria-label={dismissLabel}
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}
