import type { ReactNode } from 'react';
import { EmptyState } from '@/Components/ui';
import { cn } from '@/lib/cn';

interface ChartCardProps {
    title: string;
    subtitle?: string;
    emptyTitle: string;
    emptyDescription?: string;
    isEmpty: boolean;
    className?: string;
    children: ReactNode;
}

export function ChartCard({
    title,
    subtitle,
    emptyTitle,
    emptyDescription,
    isEmpty,
    className,
    children,
}: ChartCardProps) {
    return (
        <section
            className={cn(
                'rml-card flex min-h-[320px] flex-col p-5 sm:p-6',
                className,
            )}
        >
            <div className="mb-4 space-y-1">
                <h3 className="text-base font-semibold text-rml-text">{title}</h3>
                {subtitle && (
                    <p className="text-sm text-rml-muted">{subtitle}</p>
                )}
            </div>
            <div className="min-h-0 flex-1">
                {isEmpty ? (
                    <EmptyState
                        title={emptyTitle}
                        description={emptyDescription}
                        className="h-full border-0 bg-rml-background py-10"
                    />
                ) : (
                    <div className="h-[260px] w-full sm:h-[280px]">{children}</div>
                )}
            </div>
        </section>
    );
}
