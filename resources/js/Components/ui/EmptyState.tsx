import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { Button } from '@/Components/ui/Button';

export interface EmptyStateProps {
    icon?: LucideIcon;
    title: string;
    description?: string;
    actionLabel?: string;
    onAction?: () => void;
    children?: ReactNode;
    className?: string;
}

export function EmptyState({
    icon: Icon,
    title,
    description,
    actionLabel,
    onAction,
    children,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-xl border border-dashed border-rml-border bg-white px-6 py-12 text-center',
                className,
            )}
        >
            {Icon && (
                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rml-primary-lighter text-rml-primary">
                    <Icon className="h-6 w-6" />
                </div>
            )}
            <h3 className="text-base font-semibold text-rml-text">{title}</h3>
            {description && (
                <p className="mt-1 max-w-sm text-sm text-rml-muted">{description}</p>
            )}
            {(actionLabel || children) && (
                <div className="mt-5 flex flex-wrap items-center justify-center gap-2">
                    {actionLabel && onAction && (
                        <Button onClick={onAction}>{actionLabel}</Button>
                    )}
                    {children}
                </div>
            )}
        </div>
    );
}
