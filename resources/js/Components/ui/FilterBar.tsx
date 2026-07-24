import { ReactNode } from 'react';
import { Filter, Search } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import { Button } from '@/Components/ui/Button';
import type { PageProps } from '@/types';

export interface FilterBarProps {
    search?: string;
    onSearchChange?: (value: string) => void;
    searchPlaceholder?: string;
    searchLabel?: string;
    children?: ReactNode;
    onOpenMobileFilters?: () => void;
    actions?: ReactNode;
    /** Extra actions (e.g. Create) aligned to the far right. */
    endActions?: ReactNode;
    /**
     * Compact admin list layout: fixed search width, inline filters,
     * single desktop row where space allows.
     */
    compact?: boolean;
    className?: string;
}

export function FilterBar({
    search,
    onSearchChange,
    searchPlaceholder,
    searchLabel,
    children,
    onOpenMobileFilters,
    actions,
    endActions,
    compact = false,
    className,
}: FilterBarProps) {
    const { translations } = usePage<PageProps>().props;
    const resolvedSearchLabel =
        searchLabel ?? translations.common?.search ?? 'Search';
    const filtersLabel =
        translations.admin?.common?.filters ??
        translations.seller?.common?.filters ??
        translations.buyer?.common?.filters ??
        'Filters';
    const resolvedPlaceholder = searchPlaceholder ?? `${resolvedSearchLabel}…`;

    return (
        <div
            className={cn(
                'rounded-xl border border-rml-border bg-white p-3 sm:p-4',
                className,
            )}
        >
            <div
                className={cn(
                    'flex flex-col gap-3',
                    compact
                        ? 'lg:flex-row lg:flex-wrap lg:items-end lg:gap-3'
                        : 'lg:flex-row lg:items-end lg:gap-3',
                )}
            >
                {typeof search === 'string' && onSearchChange && (
                    <div
                        className={cn(
                            'min-w-0 space-y-1.5',
                            compact
                                ? 'w-full lg:w-[22rem] lg:max-w-[26rem] lg:shrink-0'
                                : 'flex-1',
                        )}
                    >
                        <label className="block text-sm font-medium text-rml-text">
                            {resolvedSearchLabel}
                        </label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-rml-muted" />
                            <input
                                value={search}
                                onChange={(event) =>
                                    onSearchChange(event.target.value)
                                }
                                placeholder={resolvedPlaceholder}
                                aria-label={resolvedSearchLabel}
                                className="block w-full rounded-lg border border-rml-border bg-white py-2.5 pl-9 pr-3.5 text-sm text-rml-text shadow-sm placeholder:text-rml-muted/70 rml-focus-ring focus:border-rml-primary"
                            />
                        </div>
                    </div>
                )}

                <div
                    className={cn(
                        compact
                            ? 'flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end lg:w-auto'
                            : 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end',
                    )}
                >
                    {children}
                </div>

                <div
                    className={cn(
                        'flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center',
                        compact
                            ? 'w-full lg:ml-auto lg:w-auto lg:shrink-0 lg:pb-0.5'
                            : 'lg:ml-auto lg:shrink-0 lg:pb-0.5',
                    )}
                >
                    <div className="flex items-center gap-2">
                        {onOpenMobileFilters && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="lg:hidden"
                                onClick={onOpenMobileFilters}
                            >
                                <Filter className="h-4 w-4" />
                                {filtersLabel}
                            </Button>
                        )}
                        {actions}
                    </div>
                    {endActions ? (
                        <div className="flex w-full items-center gap-2 sm:w-auto sm:ml-auto lg:ml-2">
                            {endActions}
                        </div>
                    ) : null}
                </div>
            </div>
        </div>
    );
}
