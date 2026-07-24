import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { cn } from '@/lib/cn';

type BackLinkProps = {
    href: string;
    label: string;
    className?: string;
};

/**
 * Icon-only back control — left of title/content on detail pages.
 */
export function BackLink({ href, label, className }: BackLinkProps) {
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
