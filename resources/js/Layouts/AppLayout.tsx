import { PropsWithChildren, ReactNode, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { PortalSidebar } from '@/Components/layout/PortalSidebar';
import { PortalHeader } from '@/Components/layout/PortalHeader';
import { MobileSidebarDrawer } from '@/Components/layout/MobileSidebarDrawer';
import { Alert } from '@/Components/ui/Alert';
import {
    filterNavByPermissions,
    getNavForPortal,
    navPathname,
    translateNavItems,
} from '@/config/navigation';
import type { PageProps } from '@/types';

export interface AppLayoutProps extends PropsWithChildren {
    title: string;
    subtitle?: string;
    headerActions?: ReactNode;
}

export default function AppLayout({
    title,
    subtitle,
    headerActions,
    children,
}: AppLayoutProps) {
    const page = usePage<PageProps>();
    const { auth, flash, translations } = page.props;
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const user = auth.user;
    const currentPath = navPathname(page.url || '/dashboard');
    const headerKey = `${currentPath}:${title}:${subtitle ?? ''}`;

    const navItems = translateNavItems(
        filterNavByPermissions(
            getNavForPortal(user?.portal),
            user?.permissions ?? [],
            user?.primary_role,
        ),
        user?.portal === 'admin'
            ? translations.admin?.nav
            : user?.portal === 'seller'
              ? translations.seller?.nav
              : user?.portal === 'buyer'
                ? translations.buyer?.nav
                : user?.portal === 'auditor'
                  ? translations.auditor?.nav
                  : undefined,
    );

    return (
        <div className="min-h-screen overflow-x-hidden bg-rml-background">
            <div className="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col">
                <PortalSidebar items={navItems} currentPath={currentPath} />
            </div>

            <MobileSidebarDrawer
                open={mobileNavOpen}
                onClose={() => setMobileNavOpen(false)}
                items={navItems}
                currentPath={currentPath}
            />

            <div className="lg:pl-64">
                <PortalHeader
                    key={headerKey}
                    title={title}
                    subtitle={subtitle}
                    onMenuClick={() => setMobileNavOpen(true)}
                    actions={headerActions}
                />

                <main className="overflow-x-hidden px-4 py-4 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-content space-y-3">
                        {flash?.success && (
                            <Alert variant="success">{flash.success}</Alert>
                        )}
                        {flash?.error && (
                            <Alert variant="error">{flash.error}</Alert>
                        )}
                        {flash?.warning && (
                            <Alert variant="warning">{flash.warning}</Alert>
                        )}
                        {flash?.info && (
                            <Alert variant="info">{flash.info}</Alert>
                        )}
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
