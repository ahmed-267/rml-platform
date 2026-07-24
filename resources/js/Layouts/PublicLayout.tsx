import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import RmlLogo from '@/Components/branding/RmlLogo';
import { LanguageSwitcher } from '@/Components/layout/LanguageSwitcher';
import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import type { PageProps } from '@/types';
import { cn } from '@/lib/cn';

export interface PublicLayoutProps extends PropsWithChildren {
    headerActions?: ReactNode;
}

export default function PublicLayout({
    children,
    headerActions,
}: PublicLayoutProps) {
    const { translations, flash } = usePage<PageProps>().props;
    const [mobileOpen, setMobileOpen] = useState(false);

    const publicLinks = [
        { label: translations.nav.home ?? 'Home', href: '#home' },
        { label: translations.nav.buy_leads ?? 'Buy Leads', href: '#buy-leads' },
        { label: translations.nav.sell_leads ?? 'Sell Leads', href: '#sell-leads' },
        {
            label: translations.nav.free_installation ?? 'Free Installation',
            href: '#free-installation',
        },
        { label: translations.nav.contact ?? 'Contact', href: '#contact' },
    ];

    useEffect(() => {
        if (!mobileOpen) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setMobileOpen(false);
            }
        };

        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [mobileOpen]);

    return (
        <div className="min-h-screen overflow-x-hidden bg-rml-background">
            <header className="sticky top-0 z-40 border-b border-rml-border bg-white/95 shadow-sm backdrop-blur">
                <div className="mx-auto flex h-14 max-w-content items-center justify-between gap-3 px-4 sm:px-6 lg:h-[58px] lg:px-8">
                    <Link href="/#home" className="inline-flex shrink-0">
                        <RmlLogo showWordmark />
                    </Link>

                    <nav className="hidden items-center gap-1 lg:flex">
                        {publicLinks.map((link) => (
                            <a
                                key={link.href}
                                href={link.href}
                                className="rounded-md px-3 py-1.5 text-sm font-medium text-rml-muted transition hover:bg-rml-background hover:text-rml-primary"
                            >
                                {link.label}
                            </a>
                        ))}
                    </nav>

                    <div className="flex items-center gap-2">
                        <LanguageSwitcher className="hidden sm:inline-flex" />
                        {headerActions ?? (
                            <>
                                <Link href="/login" className="hidden sm:inline-flex">
                                    <Button variant="ghost" size="sm">
                                        {translations.nav.login}
                                    </Button>
                                </Link>
                                <Link href="/register" className="hidden sm:inline-flex">
                                    <Button size="sm">
                                        {translations.nav.register}
                                    </Button>
                                </Link>
                            </>
                        )}
                        <Button
                            variant="ghost"
                            size="sm"
                            className="!px-2 lg:hidden"
                            aria-label={
                                mobileOpen
                                    ? (translations.common?.close_menu ??
                                      'Close menu')
                                    : (translations.common?.open_menu ??
                                      'Open menu')
                            }
                            onClick={() => setMobileOpen((open) => !open)}
                        >
                            {mobileOpen ? (
                                <X className="h-5 w-5" />
                            ) : (
                                <Menu className="h-5 w-5" />
                            )}
                        </Button>
                    </div>
                </div>

                <div
                    className={cn(
                        'border-t border-rml-border bg-white lg:hidden',
                        mobileOpen ? 'block' : 'hidden',
                    )}
                >
                    <div className="space-y-1 px-4 py-3">
                        <div className="mb-3 sm:hidden">
                            <LanguageSwitcher />
                        </div>
                        {publicLinks.map((link) => (
                            <a
                                key={link.href}
                                href={link.href}
                                onClick={() => setMobileOpen(false)}
                                className="block rounded-lg px-3 py-2.5 text-sm font-medium text-rml-text hover:bg-rml-background"
                            >
                                {link.label}
                            </a>
                        ))}
                        <div className="flex gap-2 border-t border-rml-border pt-3 sm:hidden">
                            <Link href="/login" className="flex-1">
                                <Button variant="outline" fullWidth size="sm">
                                    {translations.nav.login}
                                </Button>
                            </Link>
                            <Link href="/register" className="flex-1">
                                <Button fullWidth size="sm">
                                    {translations.nav.register}
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </header>

            {flash?.success && (
                <div className="mx-auto max-w-content px-4 pt-4 sm:px-6 lg:px-8">
                    <Alert variant="success">{flash.success}</Alert>
                </div>
            )}
            {flash?.error && (
                <div className="mx-auto max-w-content px-4 pt-4 sm:px-6 lg:px-8">
                    <Alert variant="error">{flash.error}</Alert>
                </div>
            )}

            <main>{children}</main>
        </div>
    );
}
