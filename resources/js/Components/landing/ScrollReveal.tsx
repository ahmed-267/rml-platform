import { PropsWithChildren, useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/cn';
import { usePrefersReducedMotion } from '@/hooks/use-prefers-reduced-motion';

type RevealDirection = 'up' | 'down' | 'left' | 'right' | 'none';

interface ScrollRevealProps extends PropsWithChildren {
    className?: string;
    delayMs?: number;
    direction?: RevealDirection;
    once?: boolean;
}

const hiddenByDirection: Record<RevealDirection, string> = {
    up: 'translate-y-6 opacity-0',
    down: '-translate-y-6 opacity-0',
    left: 'translate-x-6 opacity-0',
    right: '-translate-x-6 opacity-0',
    none: 'opacity-0',
};

export default function ScrollReveal({
    children,
    className,
    delayMs = 0,
    direction = 'up',
    once = true,
}: ScrollRevealProps) {
    const ref = useRef<HTMLDivElement | null>(null);
    const reducedMotion = usePrefersReducedMotion();
    const [visible, setVisible] = useState(reducedMotion);

    useEffect(() => {
        if (reducedMotion) {
            setVisible(true);
            return;
        }

        const node = ref.current;
        if (!node || typeof IntersectionObserver === 'undefined') {
            setVisible(true);
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting) {
                    setVisible(true);
                    if (once) {
                        observer.disconnect();
                    }
                } else if (!once) {
                    setVisible(false);
                }
            },
            { threshold: 0.16, rootMargin: '0px 0px -8% 0px' },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, [once, reducedMotion]);

    return (
        <div
            ref={ref}
            className={cn(
                'transition-all duration-700 ease-out will-change-transform',
                visible
                    ? 'translate-x-0 translate-y-0 opacity-100'
                    : hiddenByDirection[direction],
                className,
            )}
            style={
                reducedMotion
                    ? undefined
                    : { transitionDelay: visible ? `${delayMs}ms` : '0ms' }
            }
        >
            {children}
        </div>
    );
}
