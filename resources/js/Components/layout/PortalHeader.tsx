import { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Bell, Menu } from 'lucide-react';
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
    className?: string;
}

export function PortalHeader({
    title,
    subtitle,
    onMenuClick,
    actions,
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
            <div className="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                {onMenuClick && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="!px-2 lg:hidden"
                        onClick={onMenuClick}
                        aria-label={common.open_navigation ?? 'Open navigation'}
                    >
                        <Menu className="h-5 w-5" />
                    </Button>
                )}

                <div className="min-w-0 flex-1">
                    <h1 className="truncate text-lg font-bold text-rml-text sm:text-xl">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="truncate text-sm text-rml-muted">
                            {subtitle}
                        </p>
                    )}
                </div>

                <div className="flex items-center gap-2 sm:gap-3">
                    <LanguageSwitcher className="hidden sm:inline-flex" />
                    {actions}
                    <Button
                        variant="ghost"
                        size="sm"
                        className="!px-2"
                        aria-label={common.notifications ?? 'Notifications'}
                    >
                        <Bell className="h-5 w-5" />
                    </Button>
                    {user && (
                        <Link
                            href="/profile"
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
                            <span className="min-w-0">
                                <span className="block max-w-[120px] truncate text-sm font-medium text-rml-text">
                                    {user.name}
                                </span>
                                {user.primary_role && (
                                    <StatusBadge
                                        label={user.primary_role.replaceAll(
                                            '_',
                                            ' ',
                                        )}
                                        tone="neutral"
                                        className="mt-0.5 capitalize"
                                    />
                                )}
                            </span>
                        </Link>
                    )}
                </div>
            </div>
        </header>
    );
}
