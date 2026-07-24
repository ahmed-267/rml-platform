import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Tabs } from '@/Components/ui';
import type { PageProps } from '@/types';
import CommissionsTab from './CommissionsTab';
import GeneralTab from './GeneralTab';
import LogsTab from './LogsTab';
import SchemesTab from './SchemesTab';
import type {
    AuditLogRow,
    CommissionRuleRow,
    SchemeRow,
    TemplateRow,
} from './types';
import type { Paginator } from '@/lib/admin-helpers';

export default function SettingsIndex({
    tab,
    schemes,
    commission_rules,
    templates,
    general,
    logs,
    log_filters,
    log_users = [],
    log_actions = [],
}: {
    tab: string;
    schemes: SchemeRow[];
    commission_rules: CommissionRuleRow[];
    templates: TemplateRow[];
    general: Record<string, string | null>;
    logs: Paginator<AuditLogRow> | null;
    log_filters: {
        action?: string | null;
        search?: string | null;
        user_id?: string | number | null;
        sort?: string | null;
        direction?: string | null;
        per_page?: string | number | null;
    };
    log_users?: { id: number; name: string }[];
    log_actions?: string[];
    zones?: unknown[];
    pricing_rules?: unknown[];
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.settings;
    const common = translations.admin.common;
    const auditT = translations.admin.audit_logs;
    const templateTypes =
        (translations.admin.settings as {
            template_types?: Record<string, string>;
        }).template_types ?? {};

    const activeTab = ['schemes', 'commissions', 'general', 'logs'].includes(tab)
        ? tab
        : 'schemes';

    const changeTab = (nextTab: string) => {
        router.get(
            route('admin.settings.index'),
            { tab: nextTab },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            },
        );
    };

    const tabItems = [
        { id: 'schemes', label: t.tab_schemes, count: schemes.length },
        {
            id: 'commissions',
            label: t.tab_commissions,
            count: commission_rules.length,
        },
        { id: 'general', label: t.tab_general },
        { id: 'logs', label: t.tab_logs },
    ];

    return (
        <AppLayout title={t.index_title} subtitle={t.index_subtitle}>
            <Head title={t.index_title} />

            <Tabs
                items={tabItems}
                value={activeTab}
                onChange={changeTab}
                className="space-y-3"
            >
                {activeTab === 'schemes' && (
                    <SchemesTab schemes={schemes} t={t} common={common} />
                )}
                {activeTab === 'commissions' && (
                    <CommissionsTab
                        rules={commission_rules}
                        t={t}
                        common={common}
                    />
                )}
                {activeTab === 'general' && (
                    <GeneralTab
                        general={general}
                        templates={templates}
                        t={t}
                        common={common}
                        templateTypes={templateTypes}
                    />
                )}
                {activeTab === 'logs' && (
                    <LogsTab
                        logs={logs}
                        filters={log_filters}
                        logUsers={log_users}
                        logActions={log_actions}
                        t={t}
                        auditT={auditT}
                        common={common}
                        locale={app.locale}
                    />
                )}
            </Tabs>
        </AppLayout>
    );
}
