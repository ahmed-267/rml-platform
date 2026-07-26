import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Tabs } from '@/Components/ui';
import LeadsBoughtIndex from '@/Pages/Admin/LeadsBought/Index';
import LeadsSoldIndex from '@/Pages/Admin/LeadsSold/Index';
import type { PageProps } from '@/types';
import type { Paginator } from '@/lib/admin-helpers';

type LeadTab = 'registered' | 'sold';

export default function AdminLeadsIndex({
    tab,
    leads,
    filters,
    filterOptions,
    summary,
}: {
    tab: LeadTab;
    leads: Paginator<Record<string, unknown>>;
    filters: Record<string, unknown>;
    filterOptions: Record<string, unknown>;
    summary?: {
        total_sold: number;
        total_revenue: number;
        total_margin: number;
    };
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.admin.leads;
    const nav = translations.admin.nav;
    const activeTab: LeadTab =
        tab === 'sold' || tab === 'registered' ? tab : 'registered';

    const changeTab = (id: string) => {
        router.get(
            route('admin.leads.index'),
            { tab: id },
            { preserveState: false, replace: true },
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
                ]}
                value={activeTab}
                onChange={changeTab}
            >
                {activeTab === 'sold' && summary ? (
                    <LeadsSoldIndex
                        embedded
                        leads={leads as never}
                        filters={filters as never}
                        filterOptions={filterOptions as never}
                        summary={summary}
                    />
                ) : (
                    <LeadsBoughtIndex
                        embedded
                        leads={leads as never}
                        filters={filters as never}
                        filterOptions={filterOptions as never}
                    />
                )}
            </Tabs>
        </AppLayout>
    );
}
