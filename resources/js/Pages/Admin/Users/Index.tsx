import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Tabs } from '@/Components/ui';
import SellersIndex from '@/Pages/Admin/Sellers/Index';
import BuyersIndex from '@/Pages/Admin/Buyers/Index';
import AuditorsPanel from '@/Pages/Admin/Users/AuditorsPanel';
import UsersPanel from '@/Pages/Admin/Users/UsersPanel';
import type { PageProps } from '@/types';
import type { Paginator } from '@/lib/admin-helpers';

type UserTab = 'users' | 'sellers' | 'buyers' | 'auditors';

export default function UsersIndex({
    tab = 'users',
    users,
    sellers,
    buyers,
    auditors,
    filters,
    filterOptions,
    permissions,
}: {
    tab?: UserTab;
    users?: Paginator<Record<string, unknown>>;
    sellers?: Paginator<Record<string, unknown>>;
    buyers?: Paginator<Record<string, unknown>>;
    auditors?: Paginator<Record<string, unknown>>;
    filters: Record<string, unknown>;
    filterOptions: Record<string, unknown>;
    permissions?: {
        can_create?: boolean;
        can_edit?: boolean;
        can_suspend?: boolean;
        can_delete?: boolean;
    };
}) {
    const { translations, auth } = usePage<PageProps>().props;
    const t = translations.admin.users;
    const nav = translations.admin.nav;
    const perms = auth.user?.permissions ?? [];
    const isSuperAdmin = auth.user?.primary_role === 'super_admin';
    const can = (permission: string) =>
        isSuperAdmin || perms.includes(permission);

    const tabItems = [
        can('manage_users')
            ? { id: 'users' as const, label: t.tab_users ?? nav.users }
            : null,
        can('manage_sellers')
            ? { id: 'sellers' as const, label: t.tab_sellers ?? nav.sellers }
            : null,
        can('manage_buyers')
            ? { id: 'buyers' as const, label: t.tab_buyers ?? nav.buyers }
            : null,
        can('manage_users') ||
        can('accept_reject_leads') ||
        can('audit_leads')
            ? {
                  id: 'auditors' as const,
                  label: t.tab_auditors ?? 'Internal Auditors',
              }
            : null,
    ].filter(Boolean) as Array<{ id: UserTab; label: string }>;

    const activeTab: UserTab =
        tabItems.some((item) => item.id === tab)
            ? (tab as UserTab)
            : (tabItems[0]?.id ?? 'users');

    const changeTab = (id: string) => {
        router.get(
            route('admin.users.index'),
            { tab: id },
            { preserveState: false, replace: true },
        );
    };

    return (
        <AppLayout title={t.index_title} subtitle={t.index_subtitle}>
            <Head title={t.index_title} />

            <Tabs items={tabItems} value={activeTab} onChange={changeTab}>
                {activeTab === 'sellers' && sellers ? (
                    <SellersIndex
                        embedded
                        sellers={sellers as never}
                        filters={filters as never}
                        filterOptions={filterOptions as never}
                    />
                ) : activeTab === 'buyers' && buyers ? (
                    <BuyersIndex
                        embedded
                        buyers={buyers as never}
                        filters={filters as never}
                        filterOptions={filterOptions as never}
                    />
                ) : activeTab === 'auditors' && auditors ? (
                    <AuditorsPanel
                        auditors={auditors as never}
                        filters={filters as never}
                        filterOptions={filterOptions as never}
                        permissions={permissions}
                    />
                ) : users ? (
                    <UsersPanel
                        users={users as never}
                        filters={filters as never}
                        filterOptions={filterOptions as never}
                    />
                ) : null}
            </Tabs>
        </AppLayout>
    );
}
