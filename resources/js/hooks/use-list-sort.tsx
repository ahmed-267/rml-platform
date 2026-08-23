import { useCallback, useMemo, useState, type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import {
    SortableHeader,
    type SortDirection,
} from '@/Components/ui/SortableHeader';
import { nextSortDirection, resolveSortDirection } from '@/lib/list-helpers';

type FiltersBag = {
    sort?: string | null;
    direction?: string | null;
    [key: string]: string | number | null | undefined;
};

/**
 * Shared server-side list sorting for Index tables.
 * Preserves filters/search/pagination when toggling sort.
 */
export function useServerListSort(options: {
    routeName: string;
    filters: FiltersBag;
    queryParams: Record<string, string | number | undefined>;
    defaultSort?: string;
    defaultDirection?: SortDirection;
    sortAscLabel?: string;
    sortDescLabel?: string;
    preserveScroll?: boolean;
}): {
    currentSort: string;
    currentDirection: SortDirection;
    handleSort: (column: string) => void;
    sortableHeader: (label: string, column: string) => ReactNode;
} {
    const {
        routeName,
        filters,
        queryParams,
        defaultSort = 'date',
        defaultDirection = 'desc',
        sortAscLabel = 'Sort ascending',
        sortDescLabel = 'Sort descending',
        preserveScroll = true,
    } = options;

    const currentSort = filters.sort ?? defaultSort;
    const currentDirection = resolveSortDirection(
        filters.direction,
        defaultDirection,
    );

    const handleSort = useCallback(
        (column: string) => {
            router.get(
                route(routeName),
                {
                    ...queryParams,
                    sort: column,
                    direction: nextSortDirection(
                        currentSort,
                        column,
                        currentDirection,
                    ),
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll,
                },
            );
        },
        [
            routeName,
            queryParams,
            currentSort,
            currentDirection,
            preserveScroll,
        ],
    );

    const sortableHeader = useCallback(
        (label: string, column: string) => (
            <SortableHeader
                label={label}
                column={column}
                currentSort={currentSort}
                currentDirection={currentDirection}
                onSort={handleSort}
                sortAscLabel={sortAscLabel}
                sortDescLabel={sortDescLabel}
            />
        ),
        [
            currentSort,
            currentDirection,
            handleSort,
            sortAscLabel,
            sortDescLabel,
        ],
    );

    return {
        currentSort,
        currentDirection,
        handleSort,
        sortableHeader,
    };
}

/**
 * Client-side sort for settings / small in-memory tables.
 */
export function useClientTableSort<T>(
    rows: T[],
    options: {
        defaultSort: string;
        defaultDirection?: SortDirection;
        accessors: Record<
            string,
            (row: T) => string | number | null | undefined
        >;
        sortAscLabel?: string;
        sortDescLabel?: string;
    },
): {
    sortedRows: T[];
    currentSort: string;
    currentDirection: SortDirection;
    handleSort: (column: string) => void;
    sortableHeader: (label: string, column: string) => ReactNode;
} {
    const {
        defaultSort,
        defaultDirection = 'asc',
        accessors,
        sortAscLabel = 'Sort ascending',
        sortDescLabel = 'Sort descending',
    } = options;

    const [currentSort, setCurrentSort] = useState(defaultSort);
    const [currentDirection, setCurrentDirection] =
        useState<SortDirection>(defaultDirection);

    const handleSort = useCallback(
        (column: string) => {
            setCurrentDirection((prev) =>
                nextSortDirection(currentSort, column, prev),
            );
            setCurrentSort(column);
        },
        [currentSort],
    );

    const sortedRows = useMemo(() => {
        const accessor = accessors[currentSort];
        if (!accessor) {
            return rows;
        }
        const copy = [...rows];
        copy.sort((a, b) => {
            const av = accessor(a);
            const bv = accessor(b);
            const aNull = av == null || av === '';
            const bNull = bv == null || bv === '';
            if (aNull && bNull) {
                return 0;
            }
            if (aNull) {
                return 1;
            }
            if (bNull) {
                return -1;
            }
            if (typeof av === 'number' && typeof bv === 'number') {
                return currentDirection === 'asc' ? av - bv : bv - av;
            }
            const as = String(av).toLowerCase();
            const bs = String(bv).toLowerCase();
            const cmp = as.localeCompare(bs, undefined, { numeric: true });
            return currentDirection === 'asc' ? cmp : -cmp;
        });
        return copy;
    }, [rows, accessors, currentSort, currentDirection]);

    const sortableHeader = useCallback(
        (label: string, column: string) => (
            <SortableHeader
                label={label}
                column={column}
                currentSort={currentSort}
                currentDirection={currentDirection}
                onSort={handleSort}
                sortAscLabel={sortAscLabel}
                sortDescLabel={sortDescLabel}
            />
        ),
        [
            currentSort,
            currentDirection,
            handleSort,
            sortAscLabel,
            sortDescLabel,
        ],
    );

    return {
        sortedRows,
        currentSort,
        currentDirection,
        handleSort,
        sortableHeader,
    };
}
