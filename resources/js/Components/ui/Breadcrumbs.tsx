import { Link } from '@inertiajs/react';
import { ChevronRight, Home } from 'lucide-react';
import { cn } from '@/lib/cn';

export type BreadcrumbItem = {
    label: string;
    href?: string;
};

export function Breadcrumbs({
    items,
    className,
    homeHref = '/',
    homeLabel = 'Home',
}: {
    items: BreadcrumbItem[];
    className?: string;
    homeHref?: string;
    homeLabel?: string;
}) {
    const trail = [{ label: homeLabel, href: homeHref }, ...items];

    return (
        <nav aria-label="Breadcrumb" className={cn('mb-1', className)}>
            <ol className="flex flex-wrap items-center gap-1 text-sm text-rml-muted">
                {trail.map((item, index) => {
                    const last = index === trail.length - 1;
                    const isHome = index === 0;

                    return (
                        <li key={`${item.label}-${index}`} className="inline-flex items-center gap-1">
                            {index > 0 && (
                                <ChevronRight
                                    className="h-3.5 w-3.5 shrink-0 text-rml-border"
                                    aria-hidden
                                />
                            )}
                            {last || !item.href ? (
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1 truncate font-medium',
                                        last ? 'text-rml-text' : '',
                                    )}
                                    aria-current={last ? 'page' : undefined}
                                >
                                    {isHome && (
                                        <Home
                                            className="h-3.5 w-3.5 shrink-0"
                                            aria-hidden
                                        />
                                    )}
                                    {item.label}
                                </span>
                            ) : (
                                <Link
                                    href={item.href}
                                    prefetch
                                    className="inline-flex items-center gap-1 truncate transition hover:text-rml-primary"
                                >
                                    {isHome && (
                                        <Home
                                            className="h-3.5 w-3.5 shrink-0"
                                            aria-hidden
                                        />
                                    )}
                                    {item.label}
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
