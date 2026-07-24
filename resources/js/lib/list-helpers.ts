import type { SortDirection } from '@/Components/ui/SortableHeader';

export interface Paginator<T> {
    data: T[];
    links?: Array<{ url: string | null; label: string; active: boolean }>;
    meta?: {
        current_page: number;
        last_page: number;
        per_page?: number;
        total?: number;
    };
    current_page?: number;
    last_page?: number;
    per_page?: number;
}

export function paginationMeta<T>(paginator: Paginator<T>): {
    page: number;
    pageCount: number;
    perPage: number;
} {
    return {
        page: paginator.meta?.current_page ?? paginator.current_page ?? 1,
        pageCount: paginator.meta?.last_page ?? paginator.last_page ?? 1,
        perPage:
            paginator.meta?.per_page ??
            paginator.per_page ??
            10,
    };
}

export function paginationLabels(common: Record<string, string | undefined>) {
    return {
        itemsPerPage: common.items_per_page ?? 'Items per page',
        page: common.pagination_page ?? 'Page',
        of: common.pagination_of ?? 'of',
        first: common.pagination_first ?? 'First page',
        previous: common.pagination_previous ?? 'Previous page',
        next: common.pagination_next ?? 'Next page',
        last: common.pagination_last ?? 'Last page',
    };
}

export function resolveSortDirection(
    direction: string | null | undefined,
    fallback: SortDirection = 'desc',
): SortDirection {
    if (direction === 'asc' || direction === 'desc') {
        return direction;
    }

    return fallback;
}

export function nextSortDirection(
    currentSort: string,
    column: string,
    currentDirection: SortDirection,
): SortDirection {
    return currentSort === column && currentDirection === 'asc' ? 'desc' : 'asc';
}
