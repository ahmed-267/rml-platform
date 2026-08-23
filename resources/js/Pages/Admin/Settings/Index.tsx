import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Tabs } from '@/Components/ui';
import type { PageProps } from '@/types';
import CommissionsTab from './CommissionsTab';
import GeneralTab from './GeneralTab';
import LeadsPackagesTab from './LeadsPackagesTab';
import LogsTab from './LogsTab';
import SchemesTab from './SchemesTab';
import type {
    AuditLogRow,
    CommissionRuleRow,
    SchemeRow,
    TemplateRow,
} from './types';
import type { Paginator } from '@/lib/admin-helpers';

type SettingsBag = Record<string, string | number | boolean | null>;

export default function SettingsIndex({
    tab,
    schemes,
    commission_rules,
    agreement_templates = [],
    terms_templates = [],
    gdpr_templates = [],
    general,
    package_settings = {},
    lead_settings = {},
    pricing_settings = {},
    payout_settings = {},
    reservation_settings = {},
    catastro_settings = {},
    scheme_requirements = [],
    logs,
    log_filters,
    log_users = [],
    log_actions = [],
    can_delete_logs = false,
}: {
    tab: string;
    schemes: SchemeRow[];
    commission_rules: CommissionRuleRow[];
    templates?: TemplateRow[];
    agreement_templates?: TemplateRow[];
    terms_templates?: TemplateRow[];
    gdpr_templates?: TemplateRow[];
    general: Record<string, string | null>;
    package_settings?: SettingsBag;
    lead_settings?: SettingsBag;
    pricing_settings?: SettingsBag;
    payout_settings?: SettingsBag;
    reservation_settings?: SettingsBag;
    catastro_settings?: SettingsBag;
    scheme_requirements?: Array<{
        id: number;
        name: string;
        slug: string;
        require_internal_audit?: boolean;
        require_homeowner_agreement?: boolean;
        require_epc?: boolean;
        require_photos?: boolean;
        min_measurement?: number | null;
        max_measurement?: number | null;
    }>;
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
    can_delete_logs?: boolean;
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

    const activeTab = [
        'schemes',
        'commissions',
        'leads_packages',
        'general',
        'logs',
    ].includes(tab)
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
        {
            id: 'leads_packages',
            label:
                (t as { tab_leads_packages?: string }).tab_leads_packages ??
                'Leads & Packages',
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
                {activeTab === 'leads_packages' && (
                    <LeadsPackagesTab
                        leadSettings={lead_settings}
                        packageSettings={package_settings}
                        pricingSettings={pricing_settings}
                        payoutSettings={payout_settings}
                        reservationSettings={reservation_settings}
                        catastroSettings={catastro_settings}
                        schemes={scheme_requirements}
                        t={t}
                        common={common}
                    />
                )}
                {activeTab === 'general' && (
                    <GeneralTab
                        general={general}
                        packageSettings={package_settings}
                        agreementTemplates={agreement_templates}
                        termsTemplates={terms_templates}
                        gdprTemplates={gdpr_templates}
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
                        canDeleteLogs={can_delete_logs}
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
