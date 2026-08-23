import { useState, type ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { Button, Checkbox, FormInput, Select } from '@/Components/ui';
import { cn } from '@/lib/cn';

type SchemeRequirement = {
    id: number;
    name: string;
    slug: string;
    require_internal_audit?: boolean;
    require_homeowner_agreement?: boolean;
    require_epc?: boolean;
    require_photos?: boolean;
    min_measurement?: number | null;
    max_measurement?: number | null;
};

type SchemeFormRow = {
    id: number;
    require_internal_audit: boolean;
    require_homeowner_agreement: boolean;
    require_epc: boolean;
    require_photos: boolean;
    min_measurement: string;
    max_measurement: string;
};

type LeadsPackagesFormData = {
    default_status_after_submission: string;
    require_internal_audit: boolean;
    allow_evidence_later: boolean;
    allow_seller_company_leads: boolean;
    allow_seller_agent_leads: boolean;
    rml_internal_creates_payouts: boolean;
    min_property_area_m2: string;
    max_property_area_m2: string;
    reservation_hours: string;
    sale_lock_hours: string;
    allow_rejected_resubmit: boolean;
    duplicate_detection: string;
    default_status: string;
    allow_manual_creation: boolean;
    allow_installer_based_creation: boolean;
    allow_without_installer: boolean;
    allow_without_buyer: boolean;
    allow_mixed_scheme: boolean;
    allow_mixed_zone: boolean;
    reservation_lock: boolean;
    release_leads_on_expiry: boolean;
    expiry_days: string;
    min_leads: string;
    max_leads: string;
    min_area_m2: string;
    max_area_m2: string;
    default_search_radius_km: string;
    max_lead_distance_km: string;
    package_reservation_hours: string;
    calculation_method: string;
    fixed_selling_price: string;
    minimum_selling_price: string;
    maximum_discount_percent: string;
    package_discount_percent_max: string;
    tax_percent: string;
    pricing_allow_manual_override: boolean;
    payout_method: string;
    payout_fixed_amount: string;
    payout_rate_per_m2: string;
    payout_percentage: string;
    rml_internal_payouts: boolean;
    company_payouts: boolean;
    agent_payouts: boolean;
    staff_payout_to_company: boolean;
    payout_allow_manual_override: boolean;
    lead_reservation_hours: string;
    abandoned_expires_hours: string;
    return_leads_to_listed: boolean;
    return_packages_to_available: boolean;
    allow_edit_reserved_package: boolean;
    on_payment_fail: string;
    catastro_enabled: boolean;
    schemes: SchemeFormRow[];
};

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

function boolVal(value: unknown, defaultVal = false): boolean {
    if (value === null || value === undefined) {
        return defaultVal;
    }

    return Boolean(value);
}

function strVal(value: unknown, defaultVal = ''): string {
    if (value === null || value === undefined) {
        return defaultVal;
    }

    return String(value);
}

function schemeToFormRow(scheme: SchemeRequirement): SchemeFormRow {
    return {
        id: scheme.id,
        require_internal_audit: boolVal(scheme.require_internal_audit),
        require_homeowner_agreement: boolVal(
            scheme.require_homeowner_agreement,
        ),
        require_epc: boolVal(scheme.require_epc),
        require_photos: boolVal(scheme.require_photos),
        min_measurement: strVal(scheme.min_measurement),
        max_measurement: strVal(scheme.max_measurement),
    };
}

function buildInitialFormData(
    leadSettings: Record<string, string | number | boolean | null>,
    packageSettings: Record<string, string | number | boolean | null>,
    pricingSettings: Record<string, string | number | boolean | null>,
    payoutSettings: Record<string, string | number | boolean | null>,
    reservationSettings: Record<string, string | number | boolean | null>,
    catastroSettings: Record<string, string | number | boolean | null>,
    schemes: SchemeRequirement[],
): LeadsPackagesFormData {
    return {
        default_status_after_submission: strVal(
            leadSettings.default_status_after_submission,
            'pending_validation',
        ),
        require_internal_audit: boolVal(
            leadSettings.require_internal_audit,
            true,
        ),
        allow_evidence_later: boolVal(
            leadSettings.allow_evidence_later,
            true,
        ),
        allow_seller_company_leads: boolVal(
            leadSettings.allow_seller_company_leads,
            true,
        ),
        allow_seller_agent_leads: boolVal(
            leadSettings.allow_seller_agent_leads,
            true,
        ),
        rml_internal_creates_payouts: boolVal(
            leadSettings.rml_internal_creates_payouts,
        ),
        min_property_area_m2: strVal(leadSettings.min_property_area_m2, '1'),
        max_property_area_m2: strVal(
            leadSettings.max_property_area_m2,
            '10000',
        ),
        reservation_hours: strVal(leadSettings.reservation_hours, '24'),
        sale_lock_hours: strVal(leadSettings.sale_lock_hours, '48'),
        allow_rejected_resubmit: boolVal(
            leadSettings.allow_rejected_resubmit,
            true,
        ),
        duplicate_detection: strVal(
            leadSettings.duplicate_detection,
            'soft',
        ),
        default_status: strVal(packageSettings.default_status, 'available'),
        allow_manual_creation: boolVal(
            packageSettings.allow_manual_creation,
            true,
        ),
        allow_installer_based_creation: boolVal(
            packageSettings.allow_installer_based_creation,
            true,
        ),
        allow_without_installer: boolVal(
            packageSettings.allow_without_installer,
            true,
        ),
        allow_without_buyer: boolVal(
            packageSettings.allow_without_buyer,
            true,
        ),
        allow_mixed_scheme: boolVal(packageSettings.allow_mixed_scheme, true),
        allow_mixed_zone: boolVal(packageSettings.allow_mixed_zone, true),
        reservation_lock: boolVal(packageSettings.reservation_lock, true),
        release_leads_on_expiry: boolVal(
            packageSettings.release_leads_on_expiry,
            true,
        ),
        expiry_days: strVal(packageSettings.expiry_days, '14'),
        min_leads: strVal(packageSettings.min_leads, '1'),
        max_leads: strVal(packageSettings.max_leads, '100'),
        min_area_m2: strVal(packageSettings.min_area_m2, '0'),
        max_area_m2: strVal(packageSettings.max_area_m2, '100000'),
        default_search_radius_km: strVal(
            packageSettings.default_search_radius_km,
            '50',
        ),
        max_lead_distance_km: strVal(
            packageSettings.max_lead_distance_km,
            '200',
        ),
        package_reservation_hours: strVal(
            packageSettings.reservation_hours ??
                reservationSettings.package_reservation_hours,
            '48',
        ),
        calculation_method: strVal(
            pricingSettings.calculation_method,
            'per_m2',
        ),
        fixed_selling_price: strVal(pricingSettings.fixed_selling_price),
        minimum_selling_price: strVal(
            pricingSettings.minimum_selling_price,
            '0',
        ),
        maximum_discount_percent: strVal(
            pricingSettings.maximum_discount_percent,
            '25',
        ),
        package_discount_percent_max: strVal(
            pricingSettings.package_discount_percent_max,
            '15',
        ),
        tax_percent: strVal(pricingSettings.tax_percent, '0'),
        pricing_allow_manual_override: boolVal(
            pricingSettings.pricing_allow_manual_override ??
                pricingSettings.allow_manual_override,
            true,
        ),
        payout_method: strVal(
            payoutSettings.payout_method ?? payoutSettings.method,
            'per_m2',
        ),
        payout_fixed_amount: strVal(
            payoutSettings.payout_fixed_amount ?? payoutSettings.fixed_amount,
        ),
        payout_rate_per_m2: strVal(
            payoutSettings.payout_rate_per_m2 ?? payoutSettings.rate_per_m2,
            '2',
        ),
        payout_percentage: strVal(
            payoutSettings.payout_percentage ?? payoutSettings.percentage,
        ),
        rml_internal_payouts: boolVal(
            payoutSettings.rml_internal_payouts,
        ),
        company_payouts: boolVal(payoutSettings.company_payouts, true),
        agent_payouts: boolVal(payoutSettings.agent_payouts, true),
        staff_payout_to_company: boolVal(
            payoutSettings.staff_payout_to_company,
            true,
        ),
        payout_allow_manual_override: boolVal(
            payoutSettings.payout_allow_manual_override ??
                payoutSettings.allow_manual_override,
            true,
        ),
        lead_reservation_hours: strVal(
            reservationSettings.lead_reservation_hours,
            '24',
        ),
        abandoned_expires_hours: strVal(
            reservationSettings.abandoned_expires_hours,
            '72',
        ),
        return_leads_to_listed: boolVal(
            reservationSettings.return_leads_to_listed,
            true,
        ),
        return_packages_to_available: boolVal(
            reservationSettings.return_packages_to_available,
            true,
        ),
        allow_edit_reserved_package: boolVal(
            reservationSettings.allow_edit_reserved_package,
        ),
        on_payment_fail: strVal(
            reservationSettings.on_payment_fail,
            'release',
        ),
        catastro_enabled: boolVal(catastroSettings.enabled, true),
        schemes: schemes.map(schemeToFormRow),
    };
}

export default function LeadsPackagesTab({
    leadSettings,
    packageSettings,
    pricingSettings,
    payoutSettings,
    reservationSettings,
    catastroSettings = {},
    schemes,
    t,
    common,
}: {
    leadSettings: Record<string, string | number | boolean | null>;
    packageSettings: Record<string, string | number | boolean | null>;
    pricingSettings: Record<string, string | number | boolean | null>;
    payoutSettings: Record<string, string | number | boolean | null>;
    reservationSettings: Record<string, string | number | boolean | null>;
    catastroSettings?: Record<string, string | number | boolean | null>;
    schemes: SchemeRequirement[];
    t: Record<string, any>;
    common: Record<string, string>;
}) {
    const [leadsOpen, setLeadsOpen] = useState(true);
    const [packagesOpen, setPackagesOpen] = useState(true);
    const [pricingOpen, setPricingOpen] = useState(false);
    const [payoutsOpen, setPayoutsOpen] = useState(false);
    const [reservationOpen, setReservationOpen] = useState(false);
    const [catastroOpen, setCatastroOpen] = useState(false);
    const [schemesOpen, setSchemesOpen] = useState(false);

    const form = useForm<LeadsPackagesFormData>(
        buildInitialFormData(
            leadSettings,
            packageSettings,
            pricingSettings,
            payoutSettings,
            reservationSettings,
            catastroSettings,
            schemes,
        ),
    );

    const save = () => {
        form.put(route('admin.settings.leads-packages.update'));
    };

    const updateScheme = (
        index: number,
        field: keyof Omit<SchemeFormRow, 'id'>,
        value: string | boolean,
    ) => {
        const next = [...form.data.schemes];
        next[index] = { ...next[index], [field]: value };
        form.setData('schemes', next);
    };

    const schemeError = (index: number, field: string): string | undefined => {
        const key = `schemes.${index}.${field}`;
        return form.errors[key as keyof typeof form.errors];
    };

    return (
        <div className="space-y-4">
            <SectionCard
                id="leads"
                title={
                    t.leads_packages?.section_leads ?? 'Lead settings'
                }
                description={
                    t.leads_packages?.section_leads_help ??
                    'Submission defaults, seller permissions, and duplicate detection'
                }
                open={leadsOpen}
                onToggle={() => setLeadsOpen((prev) => !prev)}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <Select
                        label={
                            t.leads_packages?.default_status_after_submission ??
                            'Default status after submission'
                        }
                        value={form.data.default_status_after_submission}
                        error={form.errors.default_status_after_submission}
                        onChange={(e) =>
                            form.setData(
                                'default_status_after_submission',
                                e.target.value,
                            )
                        }
                        options={[
                            {
                                label:
                                    t.leads_packages?.status_pending_validation ??
                                    'Pending validation',
                                value: 'pending_validation',
                            },
                            {
                                label:
                                    t.leads_packages?.status_pending_evidence ??
                                    'Pending evidence',
                                value: 'pending_evidence',
                            },
                            {
                                label:
                                    t.leads_packages?.status_listed ??
                                    'Listed',
                                value: 'listed',
                            },
                        ]}
                    />
                    <Select
                        label={
                            t.leads_packages?.duplicate_detection ??
                            'Duplicate detection'
                        }
                        value={form.data.duplicate_detection}
                        error={form.errors.duplicate_detection}
                        onChange={(e) =>
                            form.setData(
                                'duplicate_detection',
                                e.target.value,
                            )
                        }
                        options={[
                            {
                                label:
                                    t.leads_packages?.duplicate_off ?? 'Off',
                                value: 'off',
                            },
                            {
                                label:
                                    t.leads_packages?.duplicate_soft ??
                                    'Soft',
                                value: 'soft',
                            },
                            {
                                label:
                                    t.leads_packages?.duplicate_strict ??
                                    'Strict',
                                value: 'strict',
                            },
                        ]}
                    />
                    <FormInput
                        type="number"
                        min={0}
                        label={
                            t.leads_packages?.min_property_area_m2 ??
                            'Minimum property area (m²)'
                        }
                        value={form.data.min_property_area_m2}
                        error={form.errors.min_property_area_m2}
                        onChange={(e) =>
                            form.setData(
                                'min_property_area_m2',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={0}
                        label={
                            t.leads_packages?.max_property_area_m2 ??
                            'Maximum property area (m²)'
                        }
                        value={form.data.max_property_area_m2}
                        error={form.errors.max_property_area_m2}
                        onChange={(e) =>
                            form.setData(
                                'max_property_area_m2',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={1}
                        label={
                            t.leads_packages?.reservation_hours ??
                            'Lead reservation hours'
                        }
                        value={form.data.reservation_hours}
                        error={form.errors.reservation_hours}
                        onChange={(e) =>
                            form.setData('reservation_hours', e.target.value)
                        }
                    />
                    <FormInput
                        type="number"
                        min={1}
                        label={
                            t.leads_packages?.sale_lock_hours ??
                            'Sale lock hours'
                        }
                        value={form.data.sale_lock_hours}
                        error={form.errors.sale_lock_hours}
                        onChange={(e) =>
                            form.setData('sale_lock_hours', e.target.value)
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.require_internal_audit ??
                            'Require internal audit'
                        }
                        checked={form.data.require_internal_audit}
                        onChange={(e) =>
                            form.setData(
                                'require_internal_audit',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_evidence_later ??
                            'Allow evidence later'
                        }
                        checked={form.data.allow_evidence_later}
                        onChange={(e) =>
                            form.setData(
                                'allow_evidence_later',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_seller_company_leads ??
                            'Allow seller company leads'
                        }
                        checked={form.data.allow_seller_company_leads}
                        onChange={(e) =>
                            form.setData(
                                'allow_seller_company_leads',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_seller_agent_leads ??
                            'Allow seller agent leads'
                        }
                        checked={form.data.allow_seller_agent_leads}
                        onChange={(e) =>
                            form.setData(
                                'allow_seller_agent_leads',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.rml_internal_creates_payouts ??
                            'RML internal creates payouts'
                        }
                        checked={form.data.rml_internal_creates_payouts}
                        onChange={(e) =>
                            form.setData(
                                'rml_internal_creates_payouts',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_rejected_resubmit ??
                            'Allow rejected resubmit'
                        }
                        checked={form.data.allow_rejected_resubmit}
                        onChange={(e) =>
                            form.setData(
                                'allow_rejected_resubmit',
                                e.target.checked,
                            )
                        }
                    />
                </div>
            </SectionCard>

            <SectionCard
                id="packages"
                title={
                    t.leads_packages?.section_packages ?? 'Package settings'
                }
                description={
                    t.leads_packages?.section_packages_help ??
                    'Creation rules, limits, and package reservation behaviour'
                }
                open={packagesOpen}
                onToggle={() => setPackagesOpen((prev) => !prev)}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <Select
                        label={
                            t.leads_packages?.default_status ??
                            'Default package status'
                        }
                        value={form.data.default_status}
                        error={form.errors.default_status}
                        onChange={(e) =>
                            form.setData('default_status', e.target.value)
                        }
                        options={[
                            {
                                label:
                                    t.leads_packages?.status_draft ?? 'Draft',
                                value: 'draft',
                            },
                            {
                                label:
                                    t.leads_packages?.status_available ??
                                    'Available',
                                value: 'available',
                            },
                        ]}
                    />
                    <FormInput
                        type="number"
                        min={1}
                        max={365}
                        label={
                            t.leads_packages?.expiry_days ??
                            'Package expiry (days)'
                        }
                        value={form.data.expiry_days}
                        error={form.errors.expiry_days}
                        onChange={(e) =>
                            form.setData('expiry_days', e.target.value)
                        }
                    />
                    <FormInput
                        type="number"
                        min={1}
                        label={
                            t.leads_packages?.min_leads ?? 'Minimum leads'
                        }
                        value={form.data.min_leads}
                        error={form.errors.min_leads}
                        onChange={(e) =>
                            form.setData('min_leads', e.target.value)
                        }
                    />
                    <FormInput
                        type="number"
                        min={1}
                        label={
                            t.leads_packages?.max_leads ?? 'Maximum leads'
                        }
                        value={form.data.max_leads}
                        error={form.errors.max_leads}
                        onChange={(e) =>
                            form.setData('max_leads', e.target.value)
                        }
                    />
                    <FormInput
                        type="number"
                        min={0}
                        label={
                            t.leads_packages?.min_area_m2 ??
                            'Minimum package area (m²)'
                        }
                        value={form.data.min_area_m2}
                        error={form.errors.min_area_m2}
                        onChange={(e) =>
                            form.setData('min_area_m2', e.target.value)
                        }
                    />
                    <FormInput
                        type="number"
                        min={0}
                        label={
                            t.leads_packages?.max_area_m2 ??
                            'Maximum package area (m²)'
                        }
                        value={form.data.max_area_m2}
                        error={form.errors.max_area_m2}
                        onChange={(e) =>
                            form.setData('max_area_m2', e.target.value)
                        }
                    />
                    <FormInput
                        type="number"
                        min={1}
                        label={
                            t.leads_packages?.default_search_radius_km ??
                            'Default search radius (km)'
                        }
                        value={form.data.default_search_radius_km}
                        error={form.errors.default_search_radius_km}
                        onChange={(e) =>
                            form.setData(
                                'default_search_radius_km',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={1}
                        label={
                            t.leads_packages?.max_lead_distance_km ??
                            'Maximum lead distance (km)'
                        }
                        value={form.data.max_lead_distance_km}
                        error={form.errors.max_lead_distance_km}
                        onChange={(e) =>
                            form.setData(
                                'max_lead_distance_km',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={1}
                        label={
                            t.leads_packages?.package_reservation_hours ??
                            'Package reservation hours'
                        }
                        value={form.data.package_reservation_hours}
                        error={form.errors.package_reservation_hours}
                        onChange={(e) =>
                            form.setData(
                                'package_reservation_hours',
                                e.target.value,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_manual_creation ??
                            'Allow manual creation'
                        }
                        checked={form.data.allow_manual_creation}
                        onChange={(e) =>
                            form.setData(
                                'allow_manual_creation',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_installer_based_creation ??
                            'Allow installer-based creation'
                        }
                        checked={form.data.allow_installer_based_creation}
                        onChange={(e) =>
                            form.setData(
                                'allow_installer_based_creation',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_without_installer ??
                            'Allow without installer'
                        }
                        checked={form.data.allow_without_installer}
                        onChange={(e) =>
                            form.setData(
                                'allow_without_installer',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_without_buyer ??
                            'Allow without buyer'
                        }
                        checked={form.data.allow_without_buyer}
                        onChange={(e) =>
                            form.setData(
                                'allow_without_buyer',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_mixed_scheme ??
                            'Allow mixed scheme'
                        }
                        checked={form.data.allow_mixed_scheme}
                        onChange={(e) =>
                            form.setData(
                                'allow_mixed_scheme',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_mixed_zone ??
                            'Allow mixed zone'
                        }
                        checked={form.data.allow_mixed_zone}
                        onChange={(e) =>
                            form.setData(
                                'allow_mixed_zone',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.reservation_lock ??
                            'Reservation lock'
                        }
                        checked={form.data.reservation_lock}
                        onChange={(e) =>
                            form.setData(
                                'reservation_lock',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.release_leads_on_expiry ??
                            'Release leads on expiry'
                        }
                        checked={form.data.release_leads_on_expiry}
                        onChange={(e) =>
                            form.setData(
                                'release_leads_on_expiry',
                                e.target.checked,
                            )
                        }
                    />
                </div>
            </SectionCard>

            <SectionCard
                id="pricing"
                title={
                    t.leads_packages?.section_pricing ?? 'Pricing settings'
                }
                description={
                    t.leads_packages?.section_pricing_help ??
                    'Default selling price calculation and discount limits'
                }
                open={pricingOpen}
                onToggle={() => setPricingOpen((prev) => !prev)}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <Select
                        label={
                            t.leads_packages?.calculation_method ??
                            'Calculation method'
                        }
                        value={form.data.calculation_method}
                        error={form.errors.calculation_method}
                        onChange={(e) =>
                            form.setData(
                                'calculation_method',
                                e.target.value,
                            )
                        }
                        options={[
                            {
                                label:
                                    t.leads_packages?.method_fixed ?? 'Fixed',
                                value: 'fixed',
                            },
                            {
                                label:
                                    t.leads_packages?.method_per_m2 ??
                                    'Per m²',
                                value: 'per_m2',
                            },
                        ]}
                    />
                    <FormInput
                        label={
                            t.leads_packages?.currency ?? 'Currency'
                        }
                        value="EUR"
                        readOnly
                        disabled
                        hint={
                            t.leads_packages?.currency_locked ??
                            'Currency is locked to EUR'
                        }
                    />
                    <FormInput
                        type="number"
                        min={0}
                        step="0.01"
                        label={
                            t.leads_packages?.fixed_selling_price ??
                            'Fixed selling price'
                        }
                        value={form.data.fixed_selling_price}
                        error={form.errors.fixed_selling_price}
                        onChange={(e) =>
                            form.setData(
                                'fixed_selling_price',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={0}
                        step="0.01"
                        label={
                            t.leads_packages?.minimum_selling_price ??
                            'Minimum selling price'
                        }
                        value={form.data.minimum_selling_price}
                        error={form.errors.minimum_selling_price}
                        onChange={(e) =>
                            form.setData(
                                'minimum_selling_price',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={0}
                        max={100}
                        step="0.01"
                        label={
                            t.leads_packages?.maximum_discount_percent ??
                            'Maximum discount (%)'
                        }
                        value={form.data.maximum_discount_percent}
                        error={form.errors.maximum_discount_percent}
                        onChange={(e) =>
                            form.setData(
                                'maximum_discount_percent',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={0}
                        max={100}
                        step="0.01"
                        label={
                            t.leads_packages?.package_discount_percent_max ??
                            'Maximum package discount (%)'
                        }
                        value={form.data.package_discount_percent_max}
                        error={form.errors.package_discount_percent_max}
                        onChange={(e) =>
                            form.setData(
                                'package_discount_percent_max',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={0}
                        max={100}
                        step="0.01"
                        label={
                            t.leads_packages?.tax_percent ?? 'Tax (%)'
                        }
                        value={form.data.tax_percent}
                        error={form.errors.tax_percent}
                        onChange={(e) =>
                            form.setData('tax_percent', e.target.value)
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.pricing_allow_manual_override ??
                            'Allow manual price override'
                        }
                        checked={form.data.pricing_allow_manual_override}
                        onChange={(e) =>
                            form.setData(
                                'pricing_allow_manual_override',
                                e.target.checked,
                            )
                        }
                    />
                </div>
            </SectionCard>

            <SectionCard
                id="payouts"
                title={
                    t.leads_packages?.section_payouts ?? 'Payout settings'
                }
                description={
                    t.leads_packages?.section_payouts_help ??
                    'Default payout method and recipient rules'
                }
                open={payoutsOpen}
                onToggle={() => setPayoutsOpen((prev) => !prev)}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <Select
                        label={
                            t.leads_packages?.payout_method ??
                            'Payout method'
                        }
                        value={form.data.payout_method}
                        error={form.errors.payout_method}
                        onChange={(e) =>
                            form.setData('payout_method', e.target.value)
                        }
                        options={[
                            {
                                label:
                                    t.leads_packages?.method_fixed ?? 'Fixed',
                                value: 'fixed',
                            },
                            {
                                label:
                                    t.leads_packages?.method_per_m2 ??
                                    'Per m²',
                                value: 'per_m2',
                            },
                            {
                                label:
                                    t.leads_packages?.method_percentage ??
                                    'Percentage',
                                value: 'percentage',
                            },
                        ]}
                    />
                    <FormInput
                        type="number"
                        min={0}
                        step="0.01"
                        label={
                            t.leads_packages?.payout_fixed_amount ??
                            'Fixed payout amount'
                        }
                        value={form.data.payout_fixed_amount}
                        error={form.errors.payout_fixed_amount}
                        onChange={(e) =>
                            form.setData(
                                'payout_fixed_amount',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={0}
                        step="0.01"
                        label={
                            t.leads_packages?.payout_rate_per_m2 ??
                            'Payout rate per m²'
                        }
                        value={form.data.payout_rate_per_m2}
                        error={form.errors.payout_rate_per_m2}
                        onChange={(e) =>
                            form.setData(
                                'payout_rate_per_m2',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={0}
                        max={100}
                        step="0.01"
                        label={
                            t.leads_packages?.payout_percentage ??
                            'Payout percentage (%)'
                        }
                        value={form.data.payout_percentage}
                        error={form.errors.payout_percentage}
                        onChange={(e) =>
                            form.setData(
                                'payout_percentage',
                                e.target.value,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.rml_internal_payouts ??
                            'RML internal payouts'
                        }
                        checked={form.data.rml_internal_payouts}
                        onChange={(e) =>
                            form.setData(
                                'rml_internal_payouts',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.company_payouts ??
                            'Company payouts'
                        }
                        checked={form.data.company_payouts}
                        onChange={(e) =>
                            form.setData(
                                'company_payouts',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.agent_payouts ??
                            'Agent payouts'
                        }
                        checked={form.data.agent_payouts}
                        onChange={(e) =>
                            form.setData(
                                'agent_payouts',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.staff_payout_to_company ??
                            'Staff payout to company'
                        }
                        checked={form.data.staff_payout_to_company}
                        onChange={(e) =>
                            form.setData(
                                'staff_payout_to_company',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.payout_allow_manual_override ??
                            'Allow manual payout override'
                        }
                        checked={form.data.payout_allow_manual_override}
                        onChange={(e) =>
                            form.setData(
                                'payout_allow_manual_override',
                                e.target.checked,
                            )
                        }
                    />
                </div>
            </SectionCard>

            <SectionCard
                id="reservations"
                title={
                    t.leads_packages?.section_reservations ??
                    'Reservation settings'
                }
                description={
                    t.leads_packages?.section_reservations_help ??
                    'Expiry, return behaviour, and payment failure handling'
                }
                open={reservationOpen}
                onToggle={() => setReservationOpen((prev) => !prev)}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <FormInput
                        type="number"
                        min={1}
                        label={
                            t.leads_packages?.lead_reservation_hours ??
                            'Lead reservation hours'
                        }
                        value={form.data.lead_reservation_hours}
                        error={form.errors.lead_reservation_hours}
                        onChange={(e) =>
                            form.setData(
                                'lead_reservation_hours',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        type="number"
                        min={1}
                        label={
                            t.leads_packages?.abandoned_expires_hours ??
                            'Abandoned reservation expiry (hours)'
                        }
                        value={form.data.abandoned_expires_hours}
                        error={form.errors.abandoned_expires_hours}
                        onChange={(e) =>
                            form.setData(
                                'abandoned_expires_hours',
                                e.target.value,
                            )
                        }
                    />
                    <Select
                        label={
                            t.leads_packages?.on_payment_fail ??
                            'On payment failure'
                        }
                        value={form.data.on_payment_fail}
                        error={form.errors.on_payment_fail}
                        onChange={(e) =>
                            form.setData('on_payment_fail', e.target.value)
                        }
                        options={[
                            {
                                label:
                                    t.leads_packages?.on_payment_fail_release ??
                                    'Release reservation',
                                value: 'release',
                            },
                            {
                                label:
                                    t.leads_packages
                                        ?.on_payment_fail_keep_locked ??
                                    'Keep locked',
                                value: 'keep_locked',
                            },
                        ]}
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.return_leads_to_listed ??
                            'Return leads to listed'
                        }
                        checked={form.data.return_leads_to_listed}
                        onChange={(e) =>
                            form.setData(
                                'return_leads_to_listed',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.return_packages_to_available ??
                            'Return packages to available'
                        }
                        checked={form.data.return_packages_to_available}
                        onChange={(e) =>
                            form.setData(
                                'return_packages_to_available',
                                e.target.checked,
                            )
                        }
                    />
                    <Checkbox
                        label={
                            t.leads_packages?.allow_edit_reserved_package ??
                            'Allow editing reserved packages'
                        }
                        checked={form.data.allow_edit_reserved_package}
                        onChange={(e) =>
                            form.setData(
                                'allow_edit_reserved_package',
                                e.target.checked,
                            )
                        }
                    />
                </div>
            </SectionCard>

            <SectionCard
                id="catastro"
                title={
                    t.leads_packages?.section_catastro ?? 'Catastro lookup'
                }
                description={
                    t.leads_packages?.section_catastro_help ??
                    'Enable public Spanish Catastro verification for admin and auditor review'
                }
                open={catastroOpen}
                onToggle={() => setCatastroOpen((prev) => !prev)}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <Checkbox
                        label={
                            t.leads_packages?.catastro_enabled ??
                            'Enable Catastro lookup'
                        }
                        checked={form.data.catastro_enabled}
                        onChange={(e) =>
                            form.setData('catastro_enabled', e.target.checked)
                        }
                    />
                </div>
                <p className="mt-2 text-xs text-rml-muted">
                    {t.leads_packages?.catastro_note ??
                        'When disabled, Check Catastro is unavailable. Lookups never block lead submission and never store protected owner data.'}
                </p>
            </SectionCard>

            <SectionCard
                id="scheme-requirements"
                title={
                    t.leads_packages?.section_schemes ??
                    'Scheme requirements'
                }
                description={
                    t.leads_packages?.section_schemes_help ??
                    'Per-scheme evidence and measurement overrides'
                }
                open={schemesOpen}
                onToggle={() => setSchemesOpen((prev) => !prev)}
            >
                {schemes.length === 0 ? (
                    <p className="text-sm text-rml-muted">
                        {t.leads_packages?.no_schemes ??
                            'No schemes configured yet.'}
                    </p>
                ) : (
                    <div className="space-y-3">
                        {form.data.schemes.map((schemeRow, index) => {
                            const schemeMeta = schemes.find(
                                (s) => s.id === schemeRow.id,
                            );

                            return (
                                <div
                                    key={schemeRow.id}
                                    className="rounded-lg border border-rml-border bg-rml-background/40 p-3"
                                >
                                    <h4 className="text-sm font-semibold text-rml-text">
                                        {schemeMeta?.name ??
                                            `Scheme #${schemeRow.id}`}
                                    </h4>
                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                        <Checkbox
                                            label={
                                                t.leads_packages
                                                    ?.require_internal_audit ??
                                                'Require internal audit'
                                            }
                                            checked={
                                                schemeRow.require_internal_audit
                                            }
                                            onChange={(e) =>
                                                updateScheme(
                                                    index,
                                                    'require_internal_audit',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        <Checkbox
                                            label={
                                                t.leads_packages
                                                    ?.require_homeowner_agreement ??
                                                'Require homeowner agreement'
                                            }
                                            checked={
                                                schemeRow.require_homeowner_agreement
                                            }
                                            onChange={(e) =>
                                                updateScheme(
                                                    index,
                                                    'require_homeowner_agreement',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        <Checkbox
                                            label={
                                                t.leads_packages
                                                    ?.require_epc ??
                                                'Require EPC'
                                            }
                                            checked={schemeRow.require_epc}
                                            onChange={(e) =>
                                                updateScheme(
                                                    index,
                                                    'require_epc',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        <Checkbox
                                            label={
                                                t.leads_packages
                                                    ?.require_photos ??
                                                'Require photos'
                                            }
                                            checked={schemeRow.require_photos}
                                            onChange={(e) =>
                                                updateScheme(
                                                    index,
                                                    'require_photos',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        <FormInput
                                            type="number"
                                            min={0}
                                            label={
                                                t.leads_packages
                                                    ?.min_measurement ??
                                                'Minimum measurement'
                                            }
                                            value={schemeRow.min_measurement}
                                            error={schemeError(
                                                index,
                                                'min_measurement',
                                            )}
                                            onChange={(e) =>
                                                updateScheme(
                                                    index,
                                                    'min_measurement',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <FormInput
                                            type="number"
                                            min={0}
                                            label={
                                                t.leads_packages
                                                    ?.max_measurement ??
                                                'Maximum measurement'
                                            }
                                            value={schemeRow.max_measurement}
                                            error={schemeError(
                                                index,
                                                'max_measurement',
                                            )}
                                            onChange={(e) =>
                                                updateScheme(
                                                    index,
                                                    'max_measurement',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </SectionCard>

            <div className="flex justify-end border-t border-rml-border pt-4">
                <Button size="sm" onClick={save} disabled={form.processing}>
                    {common.save}
                </Button>
            </div>
        </div>
    );
}
