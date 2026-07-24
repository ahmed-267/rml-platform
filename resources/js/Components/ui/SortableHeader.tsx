import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { cn } from '@/lib/cn';

export type SortDirection = 'asc' | 'desc';

export interface SortableHeaderProps {
    label: string;
    column: string;
    currentSort: string;
    currentDirection: SortDirection;
    onSort: (column: string) => void;
    sortAscLabel: string;
    sortDescLabel: string;
    className?: string;
}

export function SortableHeader({
    label,
    column,
    currentSort,
    currentDirection,
    onSort,
    sortAscLabel,
    sortDescLabel,
    className,
}: SortableHeaderProps) {
    const active = currentSort === column;
    const ariaLabel = active
        ? currentDirection === 'asc'
            ? sortAscLabel
            : sortDescLabel
        : sortAscLabel;

    return (
        <button
            type="button"
            onClick={() => onSort(column)}
            className={cn(
                'inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide transition-colors',
                active
                    ? 'text-rml-text'
                    : 'text-rml-muted hover:text-rml-text',
                className,
            )}
            aria-label={`${label}: ${ariaLabel}`}
            title={ariaLabel}
        >
            <span>{label}</span>
            {active ? (
                currentDirection === 'asc' ? (
                    <ArrowUp className="h-3.5 w-3.5 shrink-0" aria-hidden />
                ) : (
                    <ArrowDown className="h-3.5 w-3.5 shrink-0" aria-hidden />
                )
            ) : (
                <ArrowUpDown
                    className="h-3.5 w-3.5 shrink-0 opacity-40"
                    aria-hidden
                />
            )}
        </button>
    );
}
