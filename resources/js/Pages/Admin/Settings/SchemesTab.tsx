import { ReactNode, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import {
    Button,
    Checkbox,
    DataTable,
    EmptyState,
    FormInput,
    MobileCardList,
    Modal,
    Select,
    StatusBadge,
    TableActionButton,
    TableActions,
    Textarea,
    tableActionIcons,
} from '@/Components/ui';
import { cn } from '@/lib/cn';
import { useIsMobile } from '@/hooks/use-media-query';
import { useClientTableSort } from '@/hooks/use-list-sort';
import {
    buildSchemeSubmitData,
    emptySchemeForm,
    INPUT_KEYS,
    isSchemeFormReadyForActivation,
    LEAD_TYPES,
    PRICING_BASES,
    schemeToForm,
    ZONE_CODES,
    type SchemeFormData,
    type SchemeRow,
} from './types';

const EVIDENCE_CHECKBOX_KEYS = [
    'photos',
    'homeowner_agreement',
    'energy_certificate_status',
    'technical_memory_status',
] as const;

function labelLeadType(t: Record<string, string>, key: string): string {
    return t[`lead_type_${key}`] ?? key;
}

function labelPricingBasis(t: Record<string, string>, key: string): string {
    return t[`pricing_basis_${key}`] ?? key;
}

function labelInput(t: Record<string, string>, key: string): string {
    return t[`input_${key}`] ?? key;
}

function labelPricingFactor(t: Record<string, string>, key: string): string {
    return t[key] ?? t[`pricing_factor_${key}`] ?? key;
}

function emptyLabel(common: Record<string, string>): string {
    return common.not_available ?? '—';
}

function displayValue(
    value: string | null | undefined,
    common: Record<string, string>,
): string {
    return value?.trim() ? value : emptyLabel(common);
}

function formatPrice(
    value: number | null | undefined,
    common: Record<string, string>,
): string {
    return value != null ? `€${value.toFixed(2)}` : emptyLabel(common);
}

function formatFactor(
    value: number | null | undefined,
    common: Record<string, string>,
): string {
    return value != null ? String(value) : emptyLabel(common);
}

function requiredInputsCell(
    scheme: SchemeRow,
    t: Record<string, string>,
    common: Record<string, string>,
): string {
    const inputs = scheme.required_inputs ?? [];
    if (inputs.length === 0) {
        return emptyLabel(common);
    }

    return inputs
        .slice(0, 4)
        .map((key) => labelInput(t, key))
        .join(', ');
}

function pricingCell(
    scheme: SchemeRow,
    t: Record<string, string>,
    common: Record<string, string>,
): string {
    const fromTemplate = t.pricing_from ?? 'From :price';
    const formatFrom = (amount: number, unit: string) =>
        fromTemplate.replace(':price', `€${amount.toFixed(2)}${unit}`);

    const zoneValues = Object.values(scheme.zone_prices ?? {}).filter(
        (value): value is number => value != null,
    );

    switch (scheme.pricing_basis) {
        case 'zone_m2': {
            if (zoneValues.length > 0) {
                return formatFrom(Math.min(...zoneValues), '/m²');
            }
            if (scheme.price_per_m2 != null) {
                return formatFrom(scheme.price_per_m2, '/m²');
            }
            break;
        }
        case 'window_area_count': {
            if (scheme.price_per_m2 != null) {
                return formatFrom(scheme.price_per_m2, '/m²');
            }
            if (scheme.pricing_factors?.price_per_window != null) {
                return formatFrom(
                    Number(scheme.pricing_factors.price_per_window),
                    '/window',
                );
            }
            break;
        }
        case 'per_lead_kw': {
            if (scheme.price_per_m2 != null) {
                return formatFrom(scheme.price_per_m2, '/m²');
            }
            if (scheme.pricing_factors?.price_per_kw != null) {
                return formatFrom(
                    Number(scheme.pricing_factors.price_per_kw),
                    '/kW',
                );
            }
            if (scheme.base_price != null) {
                return formatFrom(scheme.base_price, '');
            }
            break;
        }
        case 'fixed_price': {
            if (scheme.base_price != null) {
                return formatFrom(scheme.base_price, '');
            }
            break;
        }
        default: {
            if (scheme.price_per_m2 != null) {
                return formatFrom(scheme.price_per_m2, '/m²');
            }
            if (scheme.base_price != null) {
                return formatFrom(scheme.base_price, '');
            }
        }
    }

    if (scheme.pricing_summary?.trim()) {
        return scheme.pricing_summary;
    }

    return emptyLabel(common);
}

function AccordionSection({
    id,
    title,
    open,
    onToggle,
    children,
    defaultCompact = true,
}: {
    id: string;
    title: string;
    open: boolean;
    onToggle: () => void;
    children: ReactNode;
    defaultCompact?: boolean;
}) {
    const padding = defaultCompact ? 'px-3 py-2' : 'px-4 py-3';

    return (
        <div className="rounded-lg border border-rml-border bg-white">
            <button
                type="button"
                id={`${id}-header`}
                aria-expanded={open}
                aria-controls={`${id}-panel`}
                className={cn(
                    'flex w-full items-center justify-between gap-2 text-left',
                    padding,
                )}
                onClick={onToggle}
            >
                <span className="text-sm font-semibold text-rml-text">{title}</span>
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
                    className={cn('border-t border-rml-border', padding, 'space-y-2')}
                >
                    {children}
                </div>
            )}
        </div>
    );
}

const defaultViewSections = {
    overview: true,
    pricingBasis: false,
    requiredInputs: false,
    evidence: false,
    pricing: false,
    algorithm: false,
    technical: false,
};

const defaultFormSections = {
    basics: true,
    requiredInputs: false,
    evidence: false,
    pricing: false,
    advanced: false,
};

type ViewSectionId = keyof typeof defaultViewSections;
type FormSectionId = keyof typeof defaultFormSections;

function CompactRow({
    label,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div className="grid gap-0.5 text-sm sm:grid-cols-[8rem_1fr] sm:gap-2">
            <dt className="text-rml-muted">{label}</dt>
            <dd className="text-rml-text">{value}</dd>
        </div>
    );
}

function ZonePriceGrid({
    scheme,
    common,
}: {
    scheme: SchemeRow;
    common: Record<string, string>;
}) {
    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {ZONE_CODES.map((code) => (
                <div
                    key={code}
                    className="rounded-md border border-rml-border p-2 text-center"
                >
                    <p className="text-xs font-semibold uppercase text-rml-muted">
                        {code}
                    </p>
                    <p className="text-sm font-medium">
                        {formatPrice(scheme.zone_prices[code], common)}
                        {scheme.zone_prices[code] != null ? '/m²' : ''}
                    </p>
                </div>
            ))}
        </div>
    );
}

function SchemeViewSections({
    scheme,
    t,
    common,
    openSections,
    onToggleSection,
}: {
    scheme: SchemeRow;
    t: Record<string, string>;
    common: Record<string, string>;
    openSections: Record<ViewSectionId, boolean>;
    onToggleSection: (id: ViewSectionId) => void;
}) {
    const meta = scheme.metadata ?? {};
    const algorithm = meta.algorithm ?? {};
    const eligibility = meta.eligibility ?? {};
    const pricingFactors = scheme.pricing_factors ?? meta.pricing_factors ?? {};
    const requiredInputs = scheme.required_inputs ?? meta.required_inputs ?? [];
    const evidence = meta.evidence ?? [];
    const pricingBasis = scheme.pricing_basis ?? meta.pricing_basis ?? 'custom';

    return (
        <div className="space-y-2">
            <AccordionSection
                id="overview"
                title={t.section_overview}
                open={openSections.overview}
                onToggle={() => onToggleSection('overview')}
            >
                <dl className="space-y-2">
                    <CompactRow label={common.name} value={scheme.name} />
                    <CompactRow
                        label={t.lead_type}
                        value={labelLeadType(t, scheme.lead_type)}
                    />
                    <CompactRow
                        label={common.description}
                        value={
                            <span className="whitespace-pre-wrap">
                                {displayValue(scheme.description, common)}
                            </span>
                        }
                    />
                    <CompactRow
                        label={common.status}
                        value={
                            <StatusBadge
                                label={scheme.active ? t.active : t.inactive}
                                tone={scheme.active ? 'success' : 'neutral'}
                            />
                        }
                    />
                </dl>
            </AccordionSection>

            <AccordionSection
                id="pricing-basis"
                title={t.primary_pricing_basis}
                open={openSections.pricingBasis}
                onToggle={() => onToggleSection('pricingBasis')}
            >
                <dl className="space-y-2">
                    <CompactRow
                        label={t.pricing_basis}
                        value={labelPricingBasis(t, pricingBasis)}
                    />
                    <CompactRow
                        label={t.pricing_basis_explanation}
                        value={displayValue(
                            scheme.pricing_basis_explanation ??
                                meta.pricing_basis_explanation ??
                                null,
                            common,
                        )}
                    />
                </dl>
            </AccordionSection>

            <AccordionSection
                id="required-inputs"
                title={t.required_inputs}
                open={openSections.requiredInputs}
                onToggle={() => onToggleSection('requiredInputs')}
            >
                {requiredInputs.length > 0 ? (
                    <div className="flex flex-wrap gap-1.5">
                        {requiredInputs.map((key) => (
                            <StatusBadge
                                key={key}
                                label={labelInput(t, key)}
                                tone="info"
                            />
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-rml-muted">{emptyLabel(common)}</p>
                )}
            </AccordionSection>

            <AccordionSection
                id="evidence"
                title={t.evidence}
                open={openSections.evidence}
                onToggle={() => onToggleSection('evidence')}
            >
                {evidence.length > 0 ? (
                    <div className="flex flex-wrap gap-1.5">
                        {evidence.map((key) => (
                            <StatusBadge
                                key={key}
                                label={labelInput(t, key)}
                                tone="neutral"
                            />
                        ))}
                    </div>
                ) : null}
                {eligibility.required_documents ? (
                    <p className="whitespace-pre-wrap text-sm text-rml-text">
                        {eligibility.required_documents}
                    </p>
                ) : evidence.length === 0 ? (
                    <p className="text-sm text-rml-muted">{emptyLabel(common)}</p>
                ) : null}
            </AccordionSection>

            <AccordionSection
                id="pricing"
                title={t.pricing}
                open={openSections.pricing}
                onToggle={() => onToggleSection('pricing')}
            >
                {pricingBasis === 'zone_m2' && (
                    <>
                        <ZonePriceGrid scheme={scheme} common={common} />
                        <dl className="space-y-2 pt-1">
                            <CompactRow
                                label={t.basic_price}
                                value={formatPrice(scheme.base_price, common)}
                            />
                            <CompactRow
                                label={t.price_per_m2}
                                value={formatPrice(scheme.price_per_m2, common)}
                            />
                        </dl>
                    </>
                )}

                {pricingBasis === 'window_area_count' && (
                    <dl className="space-y-2">
                        <CompactRow
                            label={t.basic_price}
                            value={formatPrice(scheme.base_price, common)}
                        />
                        <CompactRow
                            label={t.price_per_m2}
                            value={formatPrice(scheme.price_per_m2, common)}
                        />
                        <CompactRow
                            label={t.price_per_window}
                            value={formatPrice(
                                pricingFactors.price_per_window ?? null,
                                common,
                            )}
                        />
                        <CompactRow
                            label={labelPricingFactor(t, 'window_count_factor')}
                            value={formatFactor(
                                pricingFactors.window_count_factor ?? null,
                                common,
                            )}
                        />
                        <CompactRow
                            label={labelPricingFactor(t, 'glazing_area_factor')}
                            value={formatFactor(
                                pricingFactors.glazing_area_factor ?? null,
                                common,
                            )}
                        />
                    </dl>
                )}

                {pricingBasis === 'per_lead_kw' && (
                    <dl className="space-y-2">
                        <CompactRow
                            label={t.basic_price}
                            value={formatPrice(scheme.base_price, common)}
                        />
                        <CompactRow
                            label={t.price_per_kw}
                            value={formatPrice(
                                pricingFactors.price_per_kw ?? null,
                                common,
                            )}
                        />
                        {scheme.price_per_m2 != null && (
                            <CompactRow
                                label={t.price_per_m2}
                                value={formatPrice(scheme.price_per_m2, common)}
                            />
                        )}
                        <CompactRow
                            label={labelPricingFactor(t, 'kw_factor')}
                            value={formatFactor(
                                pricingFactors.kw_factor ?? null,
                                common,
                            )}
                        />
                        <CompactRow
                            label={labelPricingFactor(t, 'feasibility_factor')}
                            value={formatFactor(
                                pricingFactors.feasibility_factor ?? null,
                                common,
                            )}
                        />
                        <CompactRow
                            label={labelPricingFactor(t, 'system_type_factor')}
                            value={formatFactor(
                                pricingFactors.system_type_factor ?? null,
                                common,
                            )}
                        />
                    </dl>
                )}

                {pricingBasis === 'fixed_price' && (
                    <dl className="space-y-2">
                        <CompactRow
                            label={t.basic_price}
                            value={formatPrice(scheme.base_price, common)}
                        />
                        <CompactRow
                            label={t.distance_factor}
                            value={formatFactor(scheme.distance_factor, common)}
                        />
                    </dl>
                )}

                {pricingBasis === 'custom' && (
                    <dl className="space-y-2">
                        <CompactRow
                            label={t.basic_price}
                            value={formatPrice(scheme.base_price, common)}
                        />
                        <CompactRow
                            label={t.price_per_m2}
                            value={formatPrice(scheme.price_per_m2, common)}
                        />
                        <CompactRow
                            label={t.formula}
                            value={displayValue(algorithm.formula ?? null, common)}
                        />
                    </dl>
                )}

                <CompactRow
                    label={t.pricing_notes}
                    value={displayValue(meta.pricing_notes ?? null, common)}
                />
            </AccordionSection>

            <AccordionSection
                id="algorithm"
                title={t.pricing_algorithm}
                open={openSections.algorithm}
                onToggle={() => onToggleSection('algorithm')}
            >
                <dl className="space-y-2">
                    <CompactRow
                        label={t.formula}
                        value={displayValue(algorithm.formula ?? null, common)}
                    />
                    <CompactRow
                        label={t.size_factor}
                        value={formatFactor(scheme.size_factor, common)}
                    />
                    <CompactRow
                        label={t.distance_factor}
                        value={formatFactor(scheme.distance_factor, common)}
                    />
                    <CompactRow
                        label={t.zone_factor}
                        value={formatFactor(scheme.zone_factor, common)}
                    />
                    <CompactRow
                        label={t.pricing_notes}
                        value={displayValue(algorithm.notes ?? null, common)}
                    />
                </dl>
            </AccordionSection>

            <AccordionSection
                id="technical"
                title={t.technical_details}
                open={openSections.technical}
                onToggle={() => onToggleSection('technical')}
            >
                <p className="whitespace-pre-wrap text-sm text-rml-text">
                    {displayValue(meta.technical_details ?? null, common)}
                </p>
            </AccordionSection>
        </div>
    );
}

function toggleRequiredInput(
    form: ReturnType<typeof useForm<SchemeFormData>>,
    key: string,
    checked: boolean,
) {
    const current = form.data.required_inputs;
    const next = checked
        ? current.includes(key)
            ? current
            : [...current, key]
        : current.filter((item) => item !== key);
    form.setData('required_inputs', next);
}

function toggleEvidenceInput(
    form: ReturnType<typeof useForm<SchemeFormData>>,
    key: string,
    checked: boolean,
) {
    toggleRequiredInput(form, key, checked);

    const currentEvidence = form.data.evidence;
    const nextEvidence = checked
        ? currentEvidence.includes(key)
            ? currentEvidence
            : [...currentEvidence, key]
        : currentEvidence.filter((item) => item !== key);
    form.setData('evidence', nextEvidence);
}

function SchemeFormFields({
    form,
    t,
    common,
    openSections,
    onToggleSection,
}: {
    form: ReturnType<typeof useForm<SchemeFormData>>;
    t: Record<string, string>;
    common: Record<string, string>;
    openSections: Record<FormSectionId, boolean>;
    onToggleSection: (id: FormSectionId) => void;
}) {
    const leadTypeOptions = [
        { value: '', label: t.select_lead_type ?? '—' },
        ...LEAD_TYPES.map((value) => ({
            value,
            label: labelLeadType(t, value),
        })),
    ];

    const pricingBasisOptions = [
        { value: '', label: t.select_pricing_basis ?? '—' },
        ...PRICING_BASES.map((value) => ({
            value,
            label: labelPricingBasis(t, value),
        })),
    ];

    const statusOptions = [
        { value: '0', label: t.inactive },
        { value: '1', label: t.active },
    ];

    const pricingBasis = form.data.pricing_basis;

    return (
        <div className="space-y-2">
            <AccordionSection
                id="basics"
                title={t.section_basics}
                open={openSections.basics}
                onToggle={() => onToggleSection('basics')}
            >
                <div className="grid gap-2 sm:grid-cols-2">
                    <FormInput
                        label={common.name}
                        value={form.data.name}
                        error={form.errors.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                        className="sm:col-span-2"
                    />
                    <Select
                        label={t.lead_type}
                        value={form.data.lead_type}
                        error={form.errors.lead_type}
                        onChange={(e) => form.setData('lead_type', e.target.value)}
                        options={leadTypeOptions}
                    />
                    <Select
                        label={t.pricing_basis}
                        value={form.data.pricing_basis}
                        error={form.errors.pricing_basis}
                        onChange={(e) =>
                            form.setData('pricing_basis', e.target.value)
                        }
                        options={pricingBasisOptions}
                    />
                    <Textarea
                        label={common.description}
                        value={form.data.description}
                        error={form.errors.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        rows={2}
                        className="sm:col-span-2"
                    />
                    <Select
                        label={common.status}
                        value={form.data.active ? '1' : '0'}
                        error={form.errors.active}
                        onChange={(e) =>
                            form.setData('active', e.target.value === '1')
                        }
                        options={statusOptions}
                    />
                    {form.errors.active && (
                        <p className="text-sm text-rml-red sm:col-span-2">
                            {form.errors.active}
                        </p>
                    )}
                </div>
            </AccordionSection>

            <AccordionSection
                id="required-inputs-form"
                title={t.required_inputs}
                open={openSections.requiredInputs}
                onToggle={() => onToggleSection('requiredInputs')}
            >
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 [&_label_span]:text-xs [&_label_span]:font-normal">
                    {INPUT_KEYS.map((key) => (
                        <Checkbox
                            key={key}
                            checked={form.data.required_inputs.includes(key)}
                            onChange={(e) =>
                                toggleRequiredInput(form, key, e.target.checked)
                            }
                            label={labelInput(t, key)}
                        />
                    ))}
                </div>
            </AccordionSection>

            <AccordionSection
                id="evidence-form"
                title={t.evidence}
                open={openSections.evidence}
                onToggle={() => onToggleSection('evidence')}
            >
                <Textarea
                    label={t.required_documents}
                    value={form.data.eligibility_required_documents}
                    onChange={(e) =>
                        form.setData(
                            'eligibility_required_documents',
                            e.target.value,
                        )
                    }
                    rows={2}
                />
                <div className="grid grid-cols-1 gap-2 pt-1 sm:grid-cols-2 [&_label_span]:text-xs [&_label_span]:font-normal">
                    {EVIDENCE_CHECKBOX_KEYS.map((key) => (
                        <Checkbox
                            key={key}
                            checked={
                                form.data.evidence.includes(key) ||
                                form.data.required_inputs.includes(key)
                            }
                            onChange={(e) =>
                                toggleEvidenceInput(form, key, e.target.checked)
                            }
                            label={labelInput(t, key)}
                        />
                    ))}
                </div>
            </AccordionSection>

            <AccordionSection
                id="pricing-form"
                title={t.pricing}
                open={openSections.pricing}
                onToggle={() => onToggleSection('pricing')}
            >
                {pricingBasis === 'zone_m2' && (
                    <>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {(
                                [
                                    ['D1', 'zone_price_D1'],
                                    ['D2', 'zone_price_D2'],
                                    ['E1', 'zone_price_E1'],
                                    ['E2', 'zone_price_E2'],
                                ] as const
                            ).map(([code, key]) => (
                                <FormInput
                                    key={code}
                                    label={`${code} (€/m²)`}
                                    type="number"
                                    step="0.01"
                                    value={form.data[key]}
                                    onChange={(e) =>
                                        form.setData(key, e.target.value)
                                    }
                                />
                            ))}
                        </div>
                        <div className="grid gap-2 pt-1 sm:grid-cols-2">
                            <FormInput
                                label={t.basic_price}
                                type="number"
                                step="0.01"
                                value={form.data.base_price}
                                onChange={(e) =>
                                    form.setData('base_price', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.price_per_m2}
                                type="number"
                                step="0.01"
                                value={form.data.price_per_m2}
                                onChange={(e) =>
                                    form.setData('price_per_m2', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.size_factor}
                                type="number"
                                step="0.01"
                                value={form.data.size_factor}
                                onChange={(e) =>
                                    form.setData('size_factor', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.distance_factor}
                                type="number"
                                step="0.01"
                                value={form.data.distance_factor}
                                onChange={(e) =>
                                    form.setData('distance_factor', e.target.value)
                                }
                            />
                        </div>
                    </>
                )}

                {pricingBasis === 'window_area_count' && (
                    <div className="grid gap-2 sm:grid-cols-2">
                        <FormInput
                            label={t.price_per_window}
                            type="number"
                            step="0.01"
                            value={form.data.price_per_window}
                            onChange={(e) =>
                                form.setData('price_per_window', e.target.value)
                            }
                        />
                        <FormInput
                            label={t.price_per_m2}
                            type="number"
                            step="0.01"
                            value={form.data.price_per_m2}
                            onChange={(e) =>
                                form.setData('price_per_m2', e.target.value)
                            }
                        />
                        <FormInput
                            label={labelPricingFactor(t, 'glazing_area_factor')}
                            type="number"
                            step="0.01"
                            value={form.data.glazing_area_factor}
                            onChange={(e) =>
                                form.setData('glazing_area_factor', e.target.value)
                            }
                        />
                        <FormInput
                            label={labelPricingFactor(t, 'window_count_factor')}
                            type="number"
                            step="0.01"
                            value={form.data.window_count_factor}
                            onChange={(e) =>
                                form.setData('window_count_factor', e.target.value)
                            }
                        />
                        <FormInput
                            label={labelPricingFactor(t, 'quality_factor')}
                            type="number"
                            step="0.01"
                            value={form.data.quality_factor}
                            onChange={(e) =>
                                form.setData('quality_factor', e.target.value)
                            }
                        />
                        <FormInput
                            label={t.distance_factor}
                            type="number"
                            step="0.01"
                            value={form.data.distance_factor}
                            onChange={(e) =>
                                form.setData('distance_factor', e.target.value)
                            }
                        />
                        <FormInput
                            label={t.zone_factor}
                            type="number"
                            step="0.01"
                            value={form.data.zone_factor}
                            onChange={(e) =>
                                form.setData('zone_factor', e.target.value)
                            }
                            hint={t.optional_context_factor}
                        />
                    </div>
                )}

                {pricingBasis === 'per_lead_kw' && (
                    <div className="grid gap-2 sm:grid-cols-2">
                        <FormInput
                            label={t.basic_price}
                            type="number"
                            step="0.01"
                            value={form.data.base_price}
                            onChange={(e) =>
                                form.setData('base_price', e.target.value)
                            }
                        />
                        <FormInput
                            label={t.price_per_kw}
                            type="number"
                            step="0.01"
                            value={form.data.price_per_kw}
                            onChange={(e) =>
                                form.setData('price_per_kw', e.target.value)
                            }
                        />
                        <FormInput
                            label={labelPricingFactor(t, 'kw_factor')}
                            type="number"
                            step="0.01"
                            value={form.data.kw_factor}
                            onChange={(e) =>
                                form.setData('kw_factor', e.target.value)
                            }
                        />
                        <FormInput
                            label={labelPricingFactor(t, 'feasibility_factor')}
                            type="number"
                            step="0.01"
                            value={form.data.feasibility_factor}
                            onChange={(e) =>
                                form.setData('feasibility_factor', e.target.value)
                            }
                        />
                        <FormInput
                            label={labelPricingFactor(t, 'system_type_factor')}
                            type="number"
                            step="0.01"
                            value={form.data.system_type_factor}
                            onChange={(e) =>
                                form.setData('system_type_factor', e.target.value)
                            }
                        />
                        <FormInput
                            label={t.distance_factor}
                            type="number"
                            step="0.01"
                            value={form.data.distance_factor}
                            onChange={(e) =>
                                form.setData('distance_factor', e.target.value)
                            }
                        />
                        <FormInput
                            label={t.zone_factor}
                            type="number"
                            step="0.01"
                            value={form.data.zone_factor}
                            onChange={(e) =>
                                form.setData('zone_factor', e.target.value)
                            }
                            hint={t.optional_context_factor}
                        />
                    </div>
                )}

                {pricingBasis === 'fixed_price' && (
                    <div className="grid gap-2 sm:grid-cols-2">
                        <FormInput
                            label={t.basic_price}
                            type="number"
                            step="0.01"
                            value={form.data.base_price}
                            onChange={(e) =>
                                form.setData('base_price', e.target.value)
                            }
                        />
                        <FormInput
                            label={t.distance_factor}
                            type="number"
                            step="0.01"
                            value={form.data.distance_factor}
                            onChange={(e) =>
                                form.setData('distance_factor', e.target.value)
                            }
                        />
                    </div>
                )}

                {pricingBasis === 'custom' && (
                    <div className="grid gap-2 sm:grid-cols-2">
                        <FormInput
                            label={t.basic_price}
                            type="number"
                            step="0.01"
                            value={form.data.base_price}
                            onChange={(e) =>
                                form.setData('base_price', e.target.value)
                            }
                        />
                        <FormInput
                            label={t.price_per_m2}
                            type="number"
                            step="0.01"
                            value={form.data.price_per_m2}
                            onChange={(e) =>
                                form.setData('price_per_m2', e.target.value)
                            }
                        />
                        <Textarea
                            label={t.pricing_notes}
                            value={form.data.pricing_notes}
                            onChange={(e) =>
                                form.setData('pricing_notes', e.target.value)
                            }
                            rows={2}
                            className="sm:col-span-2"
                        />
                        <FormInput
                            label={t.formula}
                            value={form.data.algorithm_formula}
                            onChange={(e) =>
                                form.setData('algorithm_formula', e.target.value)
                            }
                            className="sm:col-span-2"
                        />
                    </div>
                )}

                {!pricingBasis && (
                    <p className="text-sm text-rml-muted">
                        {t.select_pricing_basis_hint}
                    </p>
                )}
            </AccordionSection>

            <AccordionSection
                id="advanced-form"
                title={t.section_algorithm}
                open={openSections.advanced}
                onToggle={() => onToggleSection('advanced')}
            >
                <div className="grid gap-2 sm:grid-cols-2">
                    <FormInput
                        label={t.formula}
                        value={form.data.algorithm_formula}
                        onChange={(e) =>
                            form.setData('algorithm_formula', e.target.value)
                        }
                        className="sm:col-span-2"
                    />
                    <FormInput
                        label={t.size_factor}
                        type="number"
                        step="0.01"
                        value={form.data.size_factor}
                        onChange={(e) =>
                            form.setData('size_factor', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.distance_factor}
                        type="number"
                        step="0.01"
                        value={form.data.distance_factor}
                        onChange={(e) =>
                            form.setData('distance_factor', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.zone_factor}
                        type="number"
                        step="0.01"
                        value={form.data.zone_factor}
                        onChange={(e) =>
                            form.setData('zone_factor', e.target.value)
                        }
                    />
                    <Textarea
                        label={t.pricing_notes}
                        value={form.data.pricing_notes}
                        onChange={(e) =>
                            form.setData('pricing_notes', e.target.value)
                        }
                        rows={2}
                        className="sm:col-span-2"
                    />
                    <Textarea
                        label={t.technical_details}
                        value={form.data.technical_details}
                        onChange={(e) =>
                            form.setData('technical_details', e.target.value)
                        }
                        rows={3}
                        className="sm:col-span-2"
                    />
                    <Textarea
                        label={t.pricing_basis_explanation}
                        value={form.data.pricing_basis_explanation}
                        onChange={(e) =>
                            form.setData(
                                'pricing_basis_explanation',
                                e.target.value,
                            )
                        }
                        rows={2}
                        className="sm:col-span-2"
                    />
                    <Textarea
                        label={t.pricing_algorithm}
                        value={form.data.algorithm_notes}
                        onChange={(e) =>
                            form.setData('algorithm_notes', e.target.value)
                        }
                        rows={2}
                        className="sm:col-span-2"
                    />
                </div>
            </AccordionSection>
        </div>
    );
}

export default function SchemesTab({
    schemes,
    t,
    common,
}: {
    schemes: SchemeRow[];
    t: Record<string, string>;
    common: Record<string, string>;
}) {
    const isMobile = useIsMobile();
    const [viewScheme, setViewScheme] = useState<SchemeRow | null>(null);
    const [editScheme, setEditScheme] = useState<SchemeRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleteScheme, setDeleteScheme] = useState<SchemeRow | null>(null);
    const [viewSections, setViewSections] = useState(defaultViewSections);
    const [formSections, setFormSections] = useState(defaultFormSections);

    const createForm = useForm<SchemeFormData>(emptySchemeForm());
    const editForm = useForm<SchemeFormData>(emptySchemeForm());

    const { sortedRows, sortableHeader } = useClientTableSort(schemes, {
        defaultSort: 'name',
        defaultDirection: 'asc',
        sortAscLabel: common.sort_asc,
        sortDescLabel: common.sort_desc,
        accessors: {
            name: (row) => row.name,
            lead_type: (row) => labelLeadType(t, row.lead_type),
            pricing: (row) => pricingCell(row, t, common),
            required_inputs: (row) => requiredInputsCell(row, t, common),
            status: (row) => (row.active ? 1 : 0),
        },
    });

    const toggleViewSection = (id: ViewSectionId) => {
        setViewSections((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const toggleFormSection = (id: FormSectionId) => {
        setFormSections((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const resetViewSections = () => setViewSections(defaultViewSections);
    const resetFormSections = () => setFormSections(defaultFormSections);

    const openCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        createForm.setData(emptySchemeForm());
        resetFormSections();
        setCreating(true);
    };

    const openEdit = (scheme: SchemeRow) => {
        editForm.clearErrors();
        editForm.setData(schemeToForm(scheme));
        resetFormSections();
        setEditScheme(scheme);
        setViewScheme(null);
    };

    const openView = (scheme: SchemeRow) => {
        resetViewSections();
        setViewScheme(scheme);
    };

    const guardActivation = (
        form: ReturnType<typeof useForm<SchemeFormData>>,
    ): boolean => {
        if (!form.data.active) {
            return true;
        }
        if (isSchemeFormReadyForActivation(form.data)) {
            return true;
        }
        form.setError(
            'active',
            t.scheme_activation_incomplete ??
                'Complete all required scheme information before activating this scheme.',
        );
        return false;
    };

    const submitCreate = () => {
        if (!guardActivation(createForm)) {
            return;
        }
        createForm.transform((data) => buildSchemeSubmitData(data));
        createForm.post(route('admin.settings.schemes.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const submitEdit = () => {
        if (!editScheme) {
            return;
        }
        if (!guardActivation(editForm)) {
            return;
        }
        editForm.transform((data) => buildSchemeSubmitData(data));
        editForm.put(route('admin.settings.schemes.update', editScheme.id), {
            onSuccess: () => setEditScheme(null),
        });
    };

    const confirmDelete = () => {
        if (!deleteScheme) {
            return;
        }
        router.delete(route('admin.settings.schemes.destroy', deleteScheme.id), {
            onSuccess: () => setDeleteScheme(null),
        });
    };

    const actionButtons = (scheme: SchemeRow) => (
        <TableActions>
            <TableActionButton
                label={common.view}
                icon={tableActionIcons.view}
                onClick={() => openView(scheme)}
            />
            <TableActionButton
                label={common.edit}
                icon={tableActionIcons.edit}
                onClick={() => openEdit(scheme)}
            />
            <TableActionButton
                label={common.delete}
                icon={tableActionIcons.delete}
                tone="danger"
                onClick={() => setDeleteScheme(scheme)}
            />
        </TableActions>
    );

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.schemes_title}
                    </h2>
                    <p className="text-xs text-rml-muted">{t.schemes_subtitle}</p>
                </div>
                <Button size="sm" onClick={openCreate}>
                    {t.add_scheme}
                </Button>
            </div>

            {schemes.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={schemes.map((scheme) => ({
                        id: String(scheme.id),
                        title: scheme.name,
                        subtitle: labelLeadType(t, scheme.lead_type),
                        meta: (
                            <StatusBadge
                                label={scheme.active ? t.active : t.inactive}
                                tone={scheme.active ? 'success' : 'neutral'}
                            />
                        ),
                        body: (
                            <p className="text-sm">
                                <span className="text-rml-muted">
                                    {t.pricing}:{' '}
                                </span>
                                {pricingCell(scheme, t, common)}
                            </p>
                        ),
                        actions: actionButtons(scheme),
                    }))}
                    emptyMessage={common.empty}
                />
            ) : (
                <DataTable
                    data={sortedRows}
                    getRowId={(row) => String(row.id)}
                    emptyMessage={common.empty}
                    columns={[
                        {
                            id: 'name',
                            header: sortableHeader(t.scheme, 'name'),
                            cell: (row) => row.name,
                        },
                        {
                            id: 'lead_type',
                            header: sortableHeader(t.lead_type, 'lead_type'),
                            cell: (row) => labelLeadType(t, row.lead_type),
                        },
                        {
                            id: 'pricing',
                            header: sortableHeader(t.pricing, 'pricing'),
                            cell: (row) => pricingCell(row, t, common),
                        },
                        {
                            id: 'required_inputs',
                            header: sortableHeader(
                                t.required_inputs,
                                'required_inputs',
                            ),
                            cell: (row) =>
                                requiredInputsCell(row, t, common),
                        },
                        {
                            id: 'status',
                            header: sortableHeader(common.status, 'status'),
                            cell: (row) => (
                                <StatusBadge
                                    label={row.active ? t.active : t.inactive}
                                    tone={row.active ? 'success' : 'neutral'}
                                />
                            ),
                        },
                        {
                            id: 'actions',
                            header: common.actions,
                            cell: (row) => actionButtons(row),
                        },
                    ]}
                />
            )}

            <Modal
                open={viewScheme != null}
                onClose={() => setViewScheme(null)}
                title={viewScheme?.name}
                size="2xl"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setViewScheme(null)}
                        >
                            {common.close}
                        </Button>
                        {viewScheme && (
                            <Button size="sm" onClick={() => openEdit(viewScheme)}>
                                {common.edit}
                            </Button>
                        )}
                    </>
                }
            >
                {viewScheme && (
                    <SchemeViewSections
                        scheme={viewScheme}
                        t={t}
                        common={common}
                        openSections={viewSections}
                        onToggleSection={toggleViewSection}
                    />
                )}
            </Modal>

            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title={t.add_scheme}
                size="2xl"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setCreating(false)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            size="sm"
                            onClick={submitCreate}
                            disabled={createForm.processing}
                        >
                            {common.save}
                        </Button>
                    </>
                }
            >
                <SchemeFormFields
                    form={createForm}
                    t={t}
                    common={common}
                    openSections={formSections}
                    onToggleSection={toggleFormSection}
                />
            </Modal>

            <Modal
                open={editScheme != null}
                onClose={() => setEditScheme(null)}
                title={editScheme?.name ?? t.scheme_details}
                size="2xl"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditScheme(null)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            size="sm"
                            onClick={submitEdit}
                            disabled={editForm.processing}
                        >
                            {common.save}
                        </Button>
                    </>
                }
            >
                <SchemeFormFields
                    form={editForm}
                    t={t}
                    common={common}
                    openSections={formSections}
                    onToggleSection={toggleFormSection}
                />
            </Modal>

            <Modal
                open={deleteScheme != null}
                onClose={() => setDeleteScheme(null)}
                title={
                    deleteScheme && deleteScheme.leads_count > 0
                        ? t.confirm_deactivate_scheme
                        : t.confirm_delete_scheme
                }
                size="sm"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeleteScheme(null)}
                        >
                            {common.cancel}
                        </Button>
                        {deleteScheme && deleteScheme.leads_count > 0 ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={confirmDelete}
                            >
                                {common.deactivate}
                            </Button>
                        ) : (
                            <Button
                                variant="danger"
                                size="sm"
                                onClick={confirmDelete}
                            >
                                {common.delete}
                            </Button>
                        )}
                    </>
                }
            >
                {deleteScheme && deleteScheme.leads_count > 0 ? (
                    <p className="text-sm text-rml-text">
                        {t.scheme_delete_warning}
                    </p>
                ) : (
                    <p className="text-sm text-rml-text">
                        {t.confirm_delete_scheme}
                    </p>
                )}
            </Modal>
        </div>
    );
}
