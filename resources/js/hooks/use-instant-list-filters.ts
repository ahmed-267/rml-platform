import { DependencyList, useEffect, useRef } from 'react';

/**
 * Instant list filtering: debounce search, apply other filter deps immediately.
 * Skips the initial mount so SSR/Inertia props are not double-fetched.
 */
export function useInstantListFilters(
    applyFilters: () => void,
    search: string,
    filterDeps: DependencyList,
    searchDelayMs = 350,
): void {
    const applyRef = useRef(applyFilters);
    applyRef.current = applyFilters;

    const firstSearch = useRef(true);
    useEffect(() => {
        if (firstSearch.current) {
            firstSearch.current = false;
            return;
        }
        const id = window.setTimeout(() => applyRef.current(), searchDelayMs);
        return () => window.clearTimeout(id);
    }, [search, searchDelayMs]);

    const firstFilters = useRef(true);
    useEffect(() => {
        if (firstFilters.current) {
            firstFilters.current = false;
            return;
        }
        applyRef.current();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, filterDeps);
}
