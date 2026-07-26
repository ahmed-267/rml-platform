import { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { LanguageSwitcher } from '@/Components/layout/LanguageSwitcher';
import { Button } from '@/Components/ui/Button';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import type { PageProps } from '@/types';
import { cn } from '@/lib/cn';

export interface PortalHeaderProps {
    title: string;
    subtitle?: string;
    onMenuClick?: () => void;
    actions?: ReactNode;
    breadcrumbs?: ReactNode;
    className?: string;
}

export function PortalHeader({
    title,
    subtitle,
    onMenuClick,
    actions,
    breadcrumbs,
    className,
}: PortalHeaderProps) {
    const { auth, translations } = usePage<PageProps>().props;
    const user = auth.user;
    const common = translations.common ?? {};

    return (
        <header
            className={cn(
                'sticky top-0 z-30 border-b border-rml-border bg-white/95 backdrop-blur',
                className,
            )}
        >
            <div className="flex items-start gap-3 px-4 py-3 sm:px-6 lg:px-8">
                {onMenuClick && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="mt-0.5 !px-2 lg:hidden"
                        onClick={onMenuClick}
                        aria-label={common.open_navigation ?? 'Open navigation'}
                    >
                        <Menu className="h-5 w-5" />
                    </Button>
                )}

                <div className="min-w-0 flex-1 space-y-1">
                    {breadcrumbs ? (
                        <div className="max-w-full overflow-x-auto">
                            {breadcrumbs}
                        </div>
                    ) : null}
                    <h1 className="truncate text-lg font-bold leading-tight text-rml-text sm:text-xl">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="truncate text-sm text-rml-muted">
                            {subtitle}
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 items-center gap-2 self-center sm:gap-3">
                    <LanguageSwitcher />
                    {actions}
                    {user && (
                        <Link
                            href="/profile"
                            prefetch
                            className="hidden items-center gap-2 rounded-lg border border-rml-border px-2.5 py-1.5 sm:flex"
                        >
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-rml-primary-light text-xs font-semibold text-rml-primary">
                                {user.name
                                    .split(' ')
                                    .map((part) => part[0])
                                    .slice(0, 2)
                                    .join('')
                                    .toUpperCase()}
                            </span>
                            <span className="max-w-[9rem] truncate text-sm font-medium text-rml-text">
                                {user.name}
                            </span>
                            {user.approval_status &&
                                user.approval_status !== 'approved' && (
                                    <StatusBadge
                                        label={user.approval_status}
                                        tone="warning"
                                    />
                                )}
                        </Link>
                    )}
                </div>
            </div>
        </header>
    );
}
