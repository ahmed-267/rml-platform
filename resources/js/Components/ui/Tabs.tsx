import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface TabItem {
    id: string;
    label: string;
    count?: number;
    disabled?: boolean;
}

export interface TabsProps {
    items: TabItem[];
    value: string;
    onChange: (id: string) => void;
    className?: string;
    children?: ReactNode;
}

export function Tabs({ items, value, onChange, className, children }: TabsProps) {
    return (
        <div className={cn('space-y-4', className)}>
            <div
                role="tablist"
                className="flex gap-1 overflow-x-auto border-b border-rml-border"
            >
                {items.map((item) => {
                    const selected = item.id === value;
                    return (
                        <button
                            key={item.id}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            disabled={item.disabled}
                            onClick={() => onChange(item.id)}
                            className={cn(
                                'relative whitespace-nowrap px-3 py-2.5 text-sm font-medium transition-colors',
                                selected
                                    ? 'text-rml-primary'
                                    : 'text-rml-muted hover:text-rml-text',
                                item.disabled && 'cursor-not-allowed opacity-50',
                            )}
                        >
                            <span className="inline-flex items-center gap-2">
                                {item.label}
                                {typeof item.count === 'number' && (
                                    <span className="rounded-full bg-rml-background px-2 py-0.5 text-xs text-rml-muted">
                                        {item.count}
                                    </span>
                                )}
                            </span>
                            {selected && (
                                <span className="absolute inset-x-0 -bottom-px h-0.5 bg-rml-primary" />
                            )}
                        </button>
                    );
                })}
            </div>
            {children}
        </div>
    );
}
