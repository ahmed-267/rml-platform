import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function FormSection({
    title,
    description,
    children,
    className,
    bare = false,
}: {
    title?: string;
    description?: string;
    children: ReactNode;
    className?: string;
    /** Skip card chrome — heading + spaced body, no hairline dividers. */
    bare?: boolean;
}) {
    const heading = (title || description) && (
        <div className="space-y-1">
            {title && (
                <h2 className="text-base font-semibold text-rml-text">
                    {title}
                </h2>
            )}
            {description && (
                <p className="text-sm text-rml-muted">{description}</p>
            )}
        </div>
    );

    if (bare) {
        return (
            <div className={cn('space-y-4', className)}>
                {heading}
                <div className="space-y-4">{children}</div>
            </div>
        );
    }

    return (
        <section className={cn('rml-card space-y-4 p-5 sm:p-6', className)}>
            {heading}
            <div className="space-y-4">{children}</div>
        </section>
    );
}

export function SectionDivider({ className }: { className?: string }) {
    return (
        <div
            className={cn('h-px w-full bg-rml-border', className)}
            role="separator"
        />
    );
}
