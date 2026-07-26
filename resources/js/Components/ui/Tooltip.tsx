import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function Tooltip({
    label,
    children,
    className,
    side = 'top',
}: {
    label: string;
    children: ReactNode;
    className?: string;
    side?: 'top' | 'bottom';
}) {
    return (
        <span className={cn('group relative inline-flex shrink-0', className)}>
            {children}
            <span
                role="tooltip"
                className={cn(
                    'pointer-events-none absolute left-1/2 z-50 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100',
                    side === 'top' ? 'bottom-full mb-1.5' : 'top-full mt-1.5',
                )}
            >
                {label}
            </span>
        </span>
    );
}
