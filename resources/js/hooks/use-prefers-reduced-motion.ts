import { useEffect, useState } from 'react';

/** SSR-safe prefers-reduced-motion detection. */
export function usePrefersReducedMotion(): boolean {
    const getMatches = (): boolean => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    };

    const [reduced, setReduced] = useState(getMatches);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const media = window.matchMedia('(prefers-reduced-motion: reduce)');
        const onChange = (event: MediaQueryListEvent) => {
            setReduced(event.matches);
        };

        setReduced(media.matches);
        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, []);

    return reduced;
}
