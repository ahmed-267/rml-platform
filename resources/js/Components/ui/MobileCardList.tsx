import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface MobileCardListItem {
    id: string;
    title: ReactNode;
    subtitle?: ReactNode;
    meta?: ReactNode;
    actions?: ReactNode;
    body?: ReactNode;
}

export interface MobileCardListProps {
    items: MobileCardListItem[];
    emptyMessage?: string;
    className?: string;
    onItemClick?: (id: string) => void;
}

export function MobileCardList({
    items,
    emptyMessage = 'No records found.',
    className,
    onItemClick,
}: MobileCardListProps) {
    if (items.length === 0) {
        return (
            <div
                className={cn(
                    'rounded-xl border border-dashed border-rml-border bg-white px-4 py-10 text-center text-sm text-rml-muted',
                    className,
                )}
            >
                {emptyMessage}
            </div>
        );
    }

    return (
        <div className={cn('space-y-3', className)}>
            {items.map((item) => (
                <article
                    key={item.id}
                    className={cn(
                        'rml-card p-4',
                        onItemClick && 'cursor-pointer active:bg-rml-background',
                    )}
                    onClick={() => onItemClick?.(item.id)}
                >
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0 space-y-1">
                            <div className="font-semibold text-rml-text">
                                {item.title}
                            </div>
                            {item.subtitle && (
                                <div className="text-sm text-rml-muted">
                                    {item.subtitle}
                                </div>
                            )}
                        </div>
                        {item.meta}
                    </div>
                    {item.body && (
                        <div className="mt-3 space-y-2 text-sm text-rml-text">
                            {item.body}
                        </div>
                    )}
                    {item.actions && (
                        <div className="mt-4 flex flex-wrap gap-2 border-t border-rml-border pt-3">
                            {item.actions}
                        </div>
                    )}
                </article>
            ))}
        </div>
    );
}
