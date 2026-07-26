import { Link, router, usePage } from '@inertiajs/react';
import { ExternalLink, LogOut } from 'lucide-react';
import { cn } from '@/lib/cn';
import RmlLogo from '@/Components/branding/RmlLogo';
import { isNavItemActive, type NavItem } from '@/config/navigation';
import type { PageProps } from '@/types';

export interface PortalSidebarProps {
    items: NavItem[];
    currentPath: string;
    className?: string;
    onNavigate?: () => void;
}

export function PortalSidebar({
    items,
    currentPath,
    className,
    onNavigate,
}: PortalSidebarProps) {
    const { translations } = usePage<PageProps>().props;
    const footerBrand = translations.brand?.footer_brand ?? 'RML Energy Saving';
    const logOutLabel = translations.common?.log_out ?? 'Log out';
    const homeSiteLabel =
        translations.common?.public_site ?? 'Back to public site';

    const handleLogout = () => {
        onNavigate?.();
        router.post(route('logout'));
    };

    return (
        <aside
            className={cn(
                'flex h-full w-64 flex-col bg-rml-sidebar text-white',
                className,
            )}
        >
            <div className="flex h-16 items-center border-b border-white/10 px-5">
                <Link
                    href="/"
                    prefetch
                    onClick={onNavigate}
                    className="inline-flex"
                    aria-label={homeSiteLabel}
                >
                    <RmlLogo showWordmark variant="light" />
                </Link>
            </div>
            <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                {items.map((item) => {
                    const active = isNavItemActive(item, currentPath, items);
                    const Icon = item.icon;

                    return (
                        <Link
                            key={`${item.labelKey ?? item.label}:${item.href}`}
                            href={item.href}
                            prefetch
                            onClick={onNavigate}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                active
                                    ? 'bg-white/10 text-white'
                                    : 'text-slate-300 hover:bg-white/5 hover:text-white',
                            )}
                        >
                            {Icon && <Icon className="h-4 w-4 shrink-0" />}
                            <span>{item.label}</span>
                        </Link>
                    );
                })}
            </nav>
            <div className="space-y-3 border-t border-white/10 px-3 py-4">
                <Link
                    href="/"
                    prefetch
                    onClick={onNavigate}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-3 py-2.5 text-sm font-semibold text-white shadow-md shadow-emerald-900/30 transition hover:from-emerald-400 hover:to-teal-400"
                >
                    <ExternalLink className="h-4 w-4 shrink-0" aria-hidden />
                    <span>{homeSiteLabel}</span>
                </Link>
                <button
                    type="button"
                    onClick={handleLogout}
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
                >
                    <LogOut className="h-4 w-4 shrink-0" />
                    <span>{logOutLabel}</span>
                </button>
                <p className="px-3 text-xs text-slate-400">{footerBrand}</p>
            </div>
        </aside>
    );
}
