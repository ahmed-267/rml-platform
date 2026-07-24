import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function ProfileCard({
    title,
    actions,
    children,
    className,
}: {
    title: string;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={cn('rml-card p-4', className)}>
            <div className="mb-3 flex items-start justify-between gap-2">
                <h2 className="text-sm font-semibold text-rml-text">{title}</h2>
                {actions ? (
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        {actions}
                    </div>
                ) : null}
            </div>
            {children}
        </section>
    );
}

export function ProfileField({
    label,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-rml-muted">{label}</dt>
            <dd className="truncate text-sm text-rml-text">{value || '—'}</dd>
        </div>
    );
}
