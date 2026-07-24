import { SVGAttributes } from 'react';
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import type { PageProps } from '@/types';

type RmlLogoProps = SVGAttributes<SVGElement> & {
    showWordmark?: boolean;
    variant?: 'default' | 'light' | 'dark';
};

/**
 * Logo 2 — RML Energy Exchange
 * House outline + exchange arrows + energy bolt.
 */
export default function RmlLogo({
    className,
    showWordmark = false,
    variant = 'default',
    ...props
}: RmlLogoProps) {
    const { translations } = usePage<PageProps>().props;
    const logoSubtitle = translations.brand?.logo_subtitle ?? 'Energy Saving';

    const markColor =
        variant === 'light'
            ? '#ffffff'
            : variant === 'dark'
              ? '#0f172a'
              : '#16a34a';

    const textColor =
        variant === 'light'
            ? 'text-white'
            : variant === 'dark'
              ? 'text-rml-sidebar'
              : 'text-rml-text';

    const subtitleColor =
        variant === 'light' ? 'text-slate-300' : 'text-rml-muted';

    return (
        <span className={cn('inline-flex items-center gap-2.5', className)}>
            <svg
                viewBox="0 0 48 48"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden={!showWordmark}
                className="h-9 w-9 shrink-0"
                {...props}
            >
                <title>RML</title>
                {/* House outline */}
                <path
                    d="M8 22.5L24 9l16 13.5V40a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2V22.5z"
                    fill="none"
                    stroke={markColor}
                    strokeWidth="2.2"
                    strokeLinejoin="round"
                />
                {/* Door */}
                <path
                    d="M21 42V31h6v11"
                    fill="none"
                    stroke={markColor}
                    strokeWidth="2"
                    strokeLinecap="round"
                />
                {/* Exchange arrows */}
                <path
                    d="M15 25.5h10.5M23 22.5l2.5 3-2.5 3"
                    fill="none"
                    stroke={markColor}
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                <path
                    d="M33 31.5H22.5M25 28.5l-2.5 3 2.5 3"
                    fill="none"
                    stroke={markColor}
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                {/* Energy bolt */}
                <path
                    d="M27.5 14.5l-3.2 5.2h2.7l-2.3 5.6 5.4-7.2h-2.8l2.2-3.6z"
                    fill={markColor}
                />
            </svg>
            {showWordmark && (
                <span className={cn('flex flex-col leading-tight', textColor)}>
                    <span className="text-sm font-bold tracking-tight">RML</span>
                    <span
                        className={cn(
                            'text-[10px] font-medium uppercase tracking-[0.08em]',
                            subtitleColor,
                        )}
                    >
                        {logoSubtitle}
                    </span>
                </span>
            )}
        </span>
    );
}
