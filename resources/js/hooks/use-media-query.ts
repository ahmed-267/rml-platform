import { useEffect, useState } from 'react';

/**
 * SSR-safe media query hook using matchMedia (not continuous resize listeners).
 * Initial state is false on the server; client updates after mount to avoid hydration mismatch.
 */
export function useMediaQuery(query: string): boolean {
    const [matches, setMatches] = useState(false);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const mediaQueryList = window.matchMedia(query);
        const onChange = (event: MediaQueryListEvent) => {
            setMatches(event.matches);
        };

        setMatches(mediaQueryList.matches);

        mediaQueryList.addEventListener('change', onChange);

        return () => {
            mediaQueryList.removeEventListener('change', onChange);
        };
    }, [query]);

    return matches;
}

export const mediaQueries = {
    mobile: '(max-width: 767px)',
    tablet: '(min-width: 768px) and (max-width: 1023px)',
    desktop: '(min-width: 1024px)',
    largeDesktop: '(min-width: 1280px)',
} as const;

export function useIsMobile(): boolean {
    return useMediaQuery(mediaQueries.mobile);
}

export function useIsTablet(): boolean {
    return useMediaQuery(mediaQueries.tablet);
}

export function useIsDesktop(): boolean {
    return useMediaQuery(mediaQueries.desktop);
}
