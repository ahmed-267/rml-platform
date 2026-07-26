import type { MouseEvent as ReactMouseEvent } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { cn } from '@/lib/cn';

const PREV_URL_KEY = 'rml:prev-url';

type BackLinkProps = {
    href: string;
    label: string;
    /** When true, show the label beside the arrow (auth / public pages). */
    showLabel?: boolean;
    /**
     * Prefer previous page (session trail / referrer / history).
     * Falls back to `href` when unavailable.
     */
    useHistory?: boolean;
    className?: string;
};

function isSameOriginUrl(url: string, origin: string): boolean {
    if (!url) {
        return false;
    }

    try {
        return new URL(url, origin).origin === origin;
    } catch {
        return false;
    }
}

function normalizeUrl(url: string): string {
    try {
        const parsed = new URL(url, window.location.origin);
        return `${parsed.origin}${parsed.pathname}${parsed.search}`;
    } catch {
        return url;
    }
}

/** Call from app bootstrap so Inertia navigations leave a reliable trail. */
export function rememberCurrentUrlAsPrevious(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        sessionStorage.setItem(PREV_URL_KEY, normalizeUrl(window.location.href));
    } catch {
        // Ignore private-mode / blocked storage.
    }
}

function readPreviousUrl(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return sessionStorage.getItem(PREV_URL_KEY);
    } catch {
        return null;
    }
}

/**
 * Client-safe back navigation: remembered Inertia URL → same-origin referrer → fallback.
 * Never uses history.back() after checkout POSTs (that can replay a forbidden URL).
 */
export function navigateBack(fallbackHref = '/'): void {
    if (typeof window === 'undefined') {
        return;
    }

    const origin = window.location.origin;
    const current = normalizeUrl(window.location.href);

    const isSafeListUrl = (url: string): boolean => {
        try {
            const path = new URL(url, origin).pathname;
            // Skip POST-only / action endpoints that 403/405 on GET.
            if (
                /\/(purchase|pay|cancel|whatsapp|reply|invite)(\/|$)/i.test(
                    path,
                )
            ) {
                return false;
            }
            return true;
        } catch {
            return false;
        }
    };

    const remembered = readPreviousUrl();
    if (
        remembered &&
        isSameOriginUrl(remembered, origin) &&
        normalizeUrl(remembered) !== current &&
        isSafeListUrl(remembered)
    ) {
        router.visit(remembered);
        return;
    }

    const referrer = document.referrer;
    if (
        referrer &&
        isSameOriginUrl(referrer, origin) &&
        normalizeUrl(referrer) !== current &&
        isSafeListUrl(referrer)
    ) {
        router.visit(referrer);
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
    useHistory = true,
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
