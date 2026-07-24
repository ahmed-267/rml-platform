import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { cn } from '@/lib/cn';

type BackLinkProps = {
    href: string;
    label: string;
    /** When true, show the label beside the arrow (auth / public pages). */
    showLabel?: boolean;
    className?: string;
};

/**
 * Back control — left of title/content on detail pages.
 * Default: bordered icon button. Optional visible label for auth flows.
 */
export function BackLink({
    href,
    label,
    showLabel = false,
    className,
}: BackLinkProps) {
    if (showLabel) {
        return (
            <Link
                href={href}
                aria-label={label}
                title={label}
                className={cn(
                    'group inline-flex max-w-full items-center gap-2 rounded-lg text-sm font-medium text-rml-muted transition-colors hover:text-rml-text rml-focus-ring',
                    className,
                )}
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
            className={cn(
                'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-rml-border bg-white text-rml-text shadow-sm transition-colors hover:bg-rml-background rml-focus-ring',
                className,
            )}
        >
            <ArrowLeft className="h-4 w-4" aria-hidden />
        </Link>
    );
}
