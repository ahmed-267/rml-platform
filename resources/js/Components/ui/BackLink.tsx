import type { MouseEvent as ReactMouseEvent } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { cn } from '@/lib/cn';

type BackLinkProps = {
    href: string;
    label: string;
    /** When true, show the label beside the arrow (auth / public pages). */
    showLabel?: boolean;
    /**
     * Prefer browser history when safe (same-origin previous page).
     * Falls back to `href` when history/referrer is unavailable or unsafe.
     */
    useHistory?: boolean;
    className?: string;
};

function isSameOriginReferrer(referrer: string, origin: string): boolean {
    if (!referrer) {
        return false;
    }

    try {
        return new URL(referrer).origin === origin;
    } catch {
        return false;
    }
}

/**
 * Client-safe back navigation: history when available & same-origin, else href.
 * Only touches `window` inside this click-time helper (SSR-safe).
 */
export function navigateBack(fallbackHref = '/'): void {
    if (typeof window === 'undefined') {
        return;
    }

    const referrer = document.referrer;

    // External / unsafe referrer → home (avoid leaving the app oddly).
    if (referrer && !isSameOriginReferrer(referrer, window.location.origin)) {
        router.visit(fallbackHref);
        return;
    }

    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(fallbackHref);
}

/**
 * Back control — left of title/content on detail pages.
 * Default: bordered icon button. Optional visible label for auth flows.
 */
export function BackLink({
    href,
    label,
    showLabel = false,
    useHistory = false,
    className,
}: BackLinkProps) {
    const onHistoryClick = (event: ReactMouseEvent<HTMLAnchorElement>) => {
        if (!useHistory) {
            return;
        }

        event.preventDefault();
        navigateBack(href);
    };

    const sharedClassName = showLabel
        ? cn(
              'group inline-flex max-w-full items-center gap-2 rounded-lg text-sm font-medium text-rml-muted transition-colors hover:text-rml-text rml-focus-ring',
              className,
          )
        : cn(
              'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-rml-border bg-white text-rml-text shadow-sm transition-colors hover:bg-rml-background rml-focus-ring',
              className,
          );

    if (useHistory) {
        return (
            <a
                href={href}
                aria-label={label}
                title={label}
                onClick={onHistoryClick}
                className={sharedClassName}
            >
                {showLabel ? (
                    <>
                        <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-rml-border bg-white text-rml-text shadow-sm transition-colors group-hover:bg-rml-background">
                            <ArrowLeft className="h-4 w-4" aria-hidden />
                        </span>
                        <span className="truncate">{label}</span>
                    </>
                ) : (
                    <ArrowLeft className="h-4 w-4" aria-hidden />
                )}
            </a>
        );
    }

    if (showLabel) {
        return (
            <Link
                href={href}
                aria-label={label}
                title={label}
                className={sharedClassName}
            >
                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-rml-border bg-white text-rml-text shadow-sm transition-colors group-hover:bg-rml-background">
                    <ArrowLeft className="h-4 w-4" aria-hidden />
                </span>
                <span className="truncate">{label}</span>
            </Link>
        );
    }

    return (
        <Link
            href={href}
            aria-label={label}
            title={label}
            className={sharedClassName}
        >
            <ArrowLeft className="h-4 w-4" aria-hidden />
        </Link>
    );
}
