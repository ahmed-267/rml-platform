import { Link, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
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
    const homeHref = items[0]?.href ?? '/dashboard';
    const footerBrand = translations.brand.footer_brand;
    const logOutLabel = translations.common?.log_out ?? 'Log out';

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
                <Link href={homeHref} onClick={onNavigate} className="inline-flex">
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
