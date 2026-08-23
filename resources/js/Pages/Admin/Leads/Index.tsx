import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    AdminLeadsMapPanel,
    type AdminMapPayload,
} from '@/Components/admin/AdminLeadsMapPanel';
import { Tabs } from '@/Components/ui';
import LeadsBoughtIndex from '@/Pages/Admin/LeadsBought/Index';
import LeadsSoldIndex from '@/Pages/Admin/LeadsSold/Index';
import PackagesIndex from '@/Pages/Admin/Packages/Index';
import type { PageProps } from '@/types';
import type { Paginator } from '@/lib/admin-helpers';

type LeadTab = 'registered' | 'sold' | 'packages';
type LeadView = 'table' | 'map';

export default function AdminLeadsIndex({
    tab,
    view = 'table',
    leads,
    packages,
    filters,
    filterOptions,
    summary,
    map = null,
    nearby = null,
    can_manage = false,
    can_create = false,
    can_sell = false,
}: {
    tab: LeadTab;
    view?: LeadView;
    leads?: Paginator<Record<string, unknown>>;
    packages?: Paginator<Record<string, unknown>>;
    filters: Record<string, unknown>;
    filterOptions: Record<string, unknown>;
    summary?: {
        total_sold: number;
        total_revenue: number;
        total_margin: number;
    };
    map?: AdminMapPayload | null;
    nearby?: {
        active: boolean;
        installer: { company_name: string } | null;
        radius_km: number;
        summary: { matched_count: number };
    } | null;
    can_manage?: boolean;
    can_create?: boolean;
    can_sell?: boolean;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.admin.leads;
    const nav = translations.admin.nav;
    const activeTab: LeadTab =
        tab === 'sold' || tab === 'packages' || tab === 'registered'
            ? tab
            : 'registered';
    const activeView: LeadView =
        activeTab === 'packages'
            ? 'table'
            : view === 'map'
              ? 'map'
              : 'table';

    const changeTab = (id: string) => {
        const nextView = id === 'packages' ? 'table' : activeView;
        router.get(
            route('admin.leads.index'),
            {
                tab: id,
                view: nextView,
                ...(nextView === 'map' ? { marker_set: 'both' } : {}),
            },
            {
                preserveState: false,
                replace: true,
            },
        );
    };

    return (
        <AppLayout title={t.index_title} subtitle={t.index_subtitle}>
            <Head title={t.index_title} />

            <Tabs
                items={[
                    {
                        id: 'registered',
                        label: t.tab_registered ?? nav.leads_registered,
                    },
                    {
                        id: 'sold',
                        label: t.tab_sold ?? nav.leads_sold,
                    },
                    {
                        id: 'packages',
                        label: t.tab_packages ?? nav.packages ?? 'Lead Packages',
                    },
                ]}
                value={activeTab}
                onChange={changeTab}
            >
                {activeTab === 'packages' && packages ? (
                    <PackagesIndex
                        embedded
                        packages={packages as never}
                        filters={filters as never}
                        filterOptions={filterOptions as never}
                        can_manage={can_manage}
                    />
                ) : activeView === 'map' && map && activeTab !== 'packages' ? (
                    <AdminLeadsMapPanel
                        tab={activeTab === 'sold' ? 'sold' : 'registered'}
                        map={map}
                        filterOptions={{
                            statuses:
                                (filterOptions.statuses as string[]) ?? [],
                            schemes:
                                (filterOptions.schemes as Array<{
                                    id: number;
                                    name: string;
                                }>) ?? [],
                            zones:
                                (filterOptions.zones as Array<{
                                    id: number;
                                    code: string;
                                    name: string;
                                }>) ?? [],
                            installers:
                                (filterOptions.installers as Array<{
                                    id: number;
                                    name: string;
                                }>) ??
                                map.installer_options,
                            match_installers:
                                (filterOptions.match_installers as Array<{
                                    id: number;
                                    name: string;
                                    city?: string | null;
                                    base_location?: string | null;
                                    matched_count?: number;
                                }>) ?? map.installer_options,
                            radius_options_km:
                                (filterOptions.radius_options_km as
                                    | number[]
                                    | undefined) ?? map.radius_options_km,
                        }}
                    />
                ) : activeTab === 'sold' && summary && leads ? (
                    <LeadsSoldIndex
                        embedded
                        leads={leads as never}
                        filters={filters as never}
                        filterOptions={filterOptions as never}
                        summary={summary}
                    />
                ) : leads ? (
                    <LeadsBoughtIndex
                        embedded
                        leads={leads as never}
                        filters={filters as never}
                        filterOptions={filterOptions as never}
                        nearby={nearby as never}
                        can_create={can_create}
                        can_sell={can_sell}
                    />
                ) : null}
            </Tabs>
        </AppLayout>
    );
}
