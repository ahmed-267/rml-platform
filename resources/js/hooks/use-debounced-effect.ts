import { DependencyList, useEffect, useRef } from 'react';

/** Run effect after `delayMs` when deps change (skip first mount if skipFirst). */
export function useDebouncedEffect(
    effect: () => void,
    deps: DependencyList,
    delayMs = 300,
    skipFirst = true,
): void {
    const first = useRef(true);

    useEffect(() => {
        if (skipFirst && first.current) {
            first.current = false;
            return;
        }

        const id = window.setTimeout(effect, delayMs);
        return () => window.clearTimeout(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);
}
