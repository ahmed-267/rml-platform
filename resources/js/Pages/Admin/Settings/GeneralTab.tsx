import { useState, type ReactNode } from 'react';
import { router, useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { Button, Checkbox, FormInput, Select, Textarea } from '@/Components/ui';
import { cn } from '@/lib/cn';
import DocumentsTab from './DocumentsTab';
import {
    AGREEMENT_TYPES,
    GDPR_TYPES,
    TERMS_TYPES,
    type TemplateRow,
} from './types';

const LOCALE_OPTIONS = [
    { label: '🇬🇧 English', value: 'en' },
    { label: '🇪🇸 Español', value: 'es' },
    { label: '🇫🇷 Français', value: 'fr' },
];

const CURRENCY_OPTIONS = [
    { label: 'EUR €', value: 'EUR' },
    { label: 'GBP £', value: 'GBP' },
    { label: 'USD $', value: 'USD' },
];

function SectionCard({
    id,
    title,
    description,
    open,
    onToggle,
    children,
}: {
    id: string;
    title: string;
    description?: string;
    open: boolean;
    onToggle: () => void;
    children: ReactNode;
}) {
    return (
        <div className="rounded-lg border border-rml-border bg-white">
            <button
                type="button"
                id={`${id}-header`}
                aria-expanded={open}
                aria-controls={`${id}-panel`}
                className="flex w-full items-center justify-between gap-2 px-4 py-3 text-left"
                onClick={onToggle}
            >
                <div className="min-w-0">
                    <span className="text-sm font-semibold text-rml-text">
                        {title}
                    </span>
                    {description && (
                        <p className="mt-0.5 text-xs text-rml-muted">
                            {description}
                        </p>
                    )}
                </div>
                <ChevronDown
                    className={cn(
                        'h-4 w-4 shrink-0 text-rml-muted transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </button>
            {open && (
                <div
                    id={`${id}-panel`}
                    className="border-t border-rml-border px-4 py-3"
                >
                    {children}
                </div>
            )}
        </div>
    );
}

export default function GeneralTab({
    general,
    packageSettings = {},
    agreementTemplates = [],
    termsTemplates = [],
    gdprTemplates = [],
    t,
    common,
    templateTypes,
}: {
    general: Record<string, string | null>;
    packageSettings?: Record<string, string | number | boolean | null>;
    agreementTemplates?: TemplateRow[];
    termsTemplates?: TemplateRow[];
    gdprTemplates?: TemplateRow[];
    t: Record<string, string>;
    common: Record<string, string>;
    templateTypes: Record<string, string>;
}) {
    const [platformOpen, setPlatformOpen] = useState(true);
    const [packagesOpen, setPackagesOpen] = useState(true);
    const [agreementsOpen, setAgreementsOpen] = useState(true);
    const [termsOpen, setTermsOpen] = useState(false);
    const [gdprOpen, setGdprOpen] = useState(false);

    const generalForm = useForm({
        support_email: general.support_email ?? '',
        support_phone: general.support_phone ?? '',
        default_locale: general.default_locale ?? 'en',
        default_currency: (general.default_currency ?? 'EUR').toUpperCase(),
        bank_transfer_instructions: general.bank_transfer_instructions ?? '',
        company_name: general.company_name ?? '',
        package_default_status: String(
            packageSettings.default_status ?? 'available',
        ),
        package_allow_mixed_scheme: Boolean(
            packageSettings.allow_mixed_scheme ?? true,
        ),
        package_allow_without_buyer: Boolean(
            packageSettings.allow_without_buyer ?? true,
        ),
        package_reservation_lock: Boolean(
            packageSettings.reservation_lock ?? true,
        ),
        package_expiry_days: String(packageSettings.expiry_days ?? 14),
    });

    const saveGeneral = () => {
        generalForm.put(route('admin.settings.general.update'));
    };

    return (
        <div className="space-y-4">
            <SectionCard
                id="platform"
                title={t.platform_settings}
                description={
                    t.platform_settings_help ??
                    'Support contacts, default language, and currency'
                }
                open={platformOpen}
                onToggle={() => setPlatformOpen((prev) => !prev)}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <FormInput
                        label={t.support_email}
                        value={generalForm.data.support_email}
                        error={generalForm.errors.support_email}
                        onChange={(e) =>
                            generalForm.setData(
                                'support_email',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        label={t.support_phone}
                        value={generalForm.data.support_phone}
                        error={generalForm.errors.support_phone}
                        onChange={(e) =>
                            generalForm.setData(
                                'support_phone',
                                e.target.value,
                            )
                        }
                    />
                    <Select
                        label={t.default_language}
                        value={generalForm.data.default_locale}
                        error={generalForm.errors.default_locale}
                        onChange={(e) =>
                            generalForm.setData(
                                'default_locale',
                                e.target.value,
                            )
                        }
                        options={LOCALE_OPTIONS}
                    />
                    <Select
                        label={t.default_currency}
                        value={generalForm.data.default_currency}
                        error={generalForm.errors.default_currency}
                        onChange={(e) =>
                            generalForm.setData(
                                'default_currency',
                                e.target.value,
                            )
                        }
                        options={CURRENCY_OPTIONS}
                    />
                    <FormInput
                        label={t.company_name}
                        value={generalForm.data.company_name}
                        error={generalForm.errors.company_name}
                        onChange={(e) =>
                            generalForm.setData(
                                'company_name',
                                e.target.value,
                            )
                        }
                        className="sm:col-span-2"
                    />
                    <Textarea
                        label={t.bank_transfer_instructions}
                        value={generalForm.data.bank_transfer_instructions}
                        error={
                            generalForm.errors.bank_transfer_instructions
                        }
                        onChange={(e) =>
                            generalForm.setData(
                                'bank_transfer_instructions',
                                e.target.value,
                            )
                        }
                        rows={3}
                        className="sm:col-span-2"
                    />
                </div>
                <div className="pt-3">
                    <Button
                        size="sm"
                        onClick={saveGeneral}
                        disabled={generalForm.processing}
                    >
                        {common.save}
                    </Button>
                </div>
            </SectionCard>

            <SectionCard
                id="packages"
                title={t.package_settings ?? 'Package settings'}
                description={
                    (t as { leads_packages?: { moved_from_general?: string } })
                        .leads_packages?.moved_from_general ??
                    'Package workflow settings now live under Leads & Packages.'
                }
                open={packagesOpen}
                onToggle={() => setPackagesOpen((prev) => !prev)}
            >
                <p className="text-sm text-rml-muted">
                    {(t as { leads_packages?: { moved_from_general?: string } })
                        .leads_packages?.moved_from_general ??
                        'Configure lead rules, packages, pricing, payouts, reservations, and scheme requirements in the Leads & Packages tab.'}
                </p>
                <div className="pt-3">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.get(route('admin.settings.index'), {
                                tab: 'leads_packages',
                            })
                        }
                    >
                        {t.tab_leads_packages ?? 'Leads & Packages'}
                    </Button>
                </div>
            </SectionCard>

            <div className="border-t border-rml-border pt-1" />

            <SectionCard
                id="agreements"
                title={t.section_agreements ?? t.tab_agreements ?? 'Agreements'}
                description={
                    t.section_agreements_help ??
                    'Seller, buyer, and homeowner agreement documents'
                }
                open={agreementsOpen}
                onToggle={() => setAgreementsOpen((prev) => !prev)}
            >
                <DocumentsTab
                    embedded
                    tabKind="agreements"
                    templates={agreementTemplates}
                    allowedTypes={AGREEMENT_TYPES}
                    t={t}
                    common={common}
                    templateTypes={templateTypes}
                />
            </SectionCard>

            <SectionCard
                id="terms"
                title={t.section_terms ?? t.tab_terms ?? 'Terms'}
                description={
                    t.section_terms_help ??
                    'Seller and buyer terms documents'
                }
                open={termsOpen}
                onToggle={() => setTermsOpen((prev) => !prev)}
            >
                <DocumentsTab
                    embedded
                    tabKind="terms"
                    templates={termsTemplates}
                    allowedTypes={TERMS_TYPES}
                    t={t}
                    common={common}
                    templateTypes={templateTypes}
                />
            </SectionCard>

            <SectionCard
                id="gdpr"
                title={
                    t.section_gdpr ?? t.tab_gdpr ?? 'GDPR / Privacy documents'
                }
                description={
                    t.section_gdpr_help ??
                    'GDPR and privacy policy documents'
                }
                open={gdprOpen}
                onToggle={() => setGdprOpen((prev) => !prev)}
            >
                <DocumentsTab
                    embedded
                    tabKind="gdpr"
                    templates={gdprTemplates}
                    allowedTypes={GDPR_TYPES}
                    t={t}
                    common={common}
                    templateTypes={templateTypes}
                />
            </SectionCard>
        </div>
    );
}
