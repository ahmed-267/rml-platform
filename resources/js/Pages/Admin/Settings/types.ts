export const LEAD_TYPES = [
    'building_envelope',
    'windows_envelope',
    'heating_system',
    'other',
] as const;

export const PRICING_BASES = [
    'zone_m2',
    'window_area_count',
    'per_lead_kw',
    'fixed_price',
    'custom',
] as const;

export const INPUT_KEYS = [
    'zone',
    'area_m2',
    'insulation_type',
    'window_count',
    'glazing_area',
    'current_glazing_type',
    'frame_type',
    'current_heating_system',
    'proposed_heat_pump_type',
    'estimated_kw',
    'property_type',
    'property_size',
    'outdoor_unit_feasibility',
    'electrical_supply_notes',
    'photos',
    'homeowner_agreement',
    'energy_certificate_status',
    'cadastral_reference',
    'technical_memory_status',
    'distance',
] as const;

export type LeadType = (typeof LEAD_TYPES)[number];
export type PricingBasis = (typeof PRICING_BASES)[number];
export type InputKey = (typeof INPUT_KEYS)[number];

export interface SchemeMetadata {
    lead_type?: LeadType | null;
    pricing_basis?: PricingBasis | null;
    pricing_basis_explanation?: string | null;
    required_inputs?: string[];
    evidence?: string[];
    pricing_factors?: {
        price_per_window?: number | null;
        window_count_factor?: number | null;
        glazing_area_factor?: number | null;
        quality_factor?: number | null;
        kw_factor?: number | null;
        price_per_kw?: number | null;
        feasibility_factor?: number | null;
        system_type_factor?: number | null;
    };
    eligibility?: {
        conditions?: string | null;
        income?: string | null;
        property_notes?: string | null;
        required_documents?: string | null;
    };
    requirements?: {
        zone_required?: boolean;
        area_required?: boolean;
        distance_required?: boolean;
        constraints?: string | null;
        other?: string | null;
    };
    pricing_notes?: string | null;
    algorithm?: {
        formula?: string | null;
        notes?: string | null;
    };
    technical_details?: string | null;
}

export interface SchemeZone {
    id: number;
    code: string;
    name: string;
    active: boolean;
}

export interface SchemeRow {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    metadata: SchemeMetadata;
    active: boolean;
    sort_order: number;
    zones_count: number;
    leads_count: number;
    zones: SchemeZone[];
    zone_prices: Record<string, number | null>;
    base_price: number | null;
    price_per_m2: number | null;
    zone_factor: number | null;
    size_factor: number | null;
    distance_factor: number | null;
    lead_type: string;
    pricing_basis: string;
    pricing_basis_explanation: string | null;
    required_inputs: string[];
    required_inputs_summary: string | null;
    pricing_factors: Record<string, number | null | undefined>;
    eligibility_summary: string | null;
    requirements_summary: string | null;
    pricing_summary: string | null;
}

export interface CommissionRuleRow {
    id: number;
    name: string;
    applies_to: string | null;
    percentage: number | null;
    rate_per_m2: number | null;
    active: boolean;
    notes: string | null;
}

export interface TemplateVersionRow {
    id: number;
    version: number | string;
    content: string | null;
    active: boolean;
    effective_from: string | null;
    created_at: string | null;
    is_active: boolean;
}

export interface TemplateRow {
    id: number;
    type: string | null;
    name: string;
    description: string | null;
    active_version: string | number | null;
    active_version_id?: number | null;
    content?: string | null;
    versions?: TemplateVersionRow[];
}

export interface AuditLogRow {
    id: number;
    action: string;
    entity_type: string;
    entity_label?: string | null;
    entity_id: number | null;
    user: { id: number; name: string; email: string } | null;
    ip_address: string | null;
    created_at: string | null;
    old_values?: Record<string, unknown> | null;
    new_values?: Record<string, unknown> | null;
    user_agent?: string | null;
}

export type SchemeFormData = ReturnType<typeof emptySchemeForm>;

export function emptySchemeForm() {
    return {
        name: '',
        description: '',
        active: false,
        lead_type: '' as string,
        pricing_basis: '' as string,
        pricing_basis_explanation: '',
        required_inputs: [] as string[],
        evidence: [] as string[],
        eligibility_conditions: '',
        eligibility_income: '',
        eligibility_property_notes: '',
        eligibility_required_documents: '',
        requirements_constraints: '',
        requirements_other: '',
        zone_price_D1: '' as string | number,
        zone_price_D2: '' as string | number,
        zone_price_E1: '' as string | number,
        zone_price_E2: '' as string | number,
        base_price: '' as string | number,
        price_per_m2: '' as string | number,
        price_per_window: '' as string | number,
        window_count_factor: '' as string | number,
        glazing_area_factor: '' as string | number,
        quality_factor: '' as string | number,
        kw_factor: '' as string | number,
        price_per_kw: '' as string | number,
        feasibility_factor: '' as string | number,
        system_type_factor: '' as string | number,
        zone_factor: '' as string | number,
        size_factor: '' as string | number,
        distance_factor: '' as string | number,
        algorithm_formula: '',
        algorithm_notes: '',
        pricing_notes: '',
        technical_details: '',
    };
}

export function schemeToForm(scheme: SchemeRow): SchemeFormData {
    const meta = scheme.metadata ?? {};
    const eligibility = meta.eligibility ?? {};
    const requirements = meta.requirements ?? {};
    const algorithm = meta.algorithm ?? {};
    const pricingFactors = scheme.pricing_factors ?? meta.pricing_factors ?? {};

    return {
        name: scheme.name,
        description: scheme.description ?? '',
        active: scheme.active,
        lead_type: scheme.lead_type ?? meta.lead_type ?? '',
        pricing_basis: scheme.pricing_basis ?? meta.pricing_basis ?? 'zone_m2',
        pricing_basis_explanation:
            scheme.pricing_basis_explanation ??
            meta.pricing_basis_explanation ??
            '',
        required_inputs: [...(scheme.required_inputs ?? meta.required_inputs ?? [])],
        evidence: [...(meta.evidence ?? [])],
        eligibility_conditions: eligibility.conditions ?? '',
        eligibility_income: eligibility.income ?? '',
        eligibility_property_notes: eligibility.property_notes ?? '',
        eligibility_required_documents: eligibility.required_documents ?? '',
        requirements_constraints: requirements.constraints ?? '',
        requirements_other: requirements.other ?? '',
        zone_price_D1: scheme.zone_prices.D1 ?? '',
        zone_price_D2: scheme.zone_prices.D2 ?? '',
        zone_price_E1: scheme.zone_prices.E1 ?? '',
        zone_price_E2: scheme.zone_prices.E2 ?? '',
        base_price: scheme.base_price ?? '',
        price_per_m2: scheme.price_per_m2 ?? '',
        price_per_window: pricingFactors.price_per_window ?? '',
        window_count_factor: pricingFactors.window_count_factor ?? '',
        glazing_area_factor: pricingFactors.glazing_area_factor ?? '',
        quality_factor: pricingFactors.quality_factor ?? '',
        kw_factor: pricingFactors.kw_factor ?? '',
        price_per_kw: pricingFactors.price_per_kw ?? '',
        feasibility_factor: pricingFactors.feasibility_factor ?? '',
        system_type_factor: pricingFactors.system_type_factor ?? '',
        zone_factor: scheme.zone_factor ?? '',
        size_factor: scheme.size_factor ?? '',
        distance_factor: scheme.distance_factor ?? '',
        algorithm_formula: algorithm.formula ?? '',
        algorithm_notes: algorithm.notes ?? '',
        pricing_notes: meta.pricing_notes ?? '',
        technical_details: meta.technical_details ?? '',
    };
}

function hasNumeric(value: string | number | null | undefined): boolean {
    if (value === '' || value == null) {
        return false;
    }
    const parsed = Number(value);
    return Number.isFinite(parsed);
}

export function isSchemeFormReadyForActivation(form: SchemeFormData): boolean {
    if (!form.name.trim() || !form.description.trim()) {
        return false;
    }
    if (!form.lead_type || !form.pricing_basis) {
        return false;
    }
    if (form.required_inputs.length === 0) {
        return false;
    }

    const hasPricing = (() => {
        switch (form.pricing_basis) {
            case 'zone_m2':
                return (
                    hasNumeric(form.zone_price_D1) ||
                    hasNumeric(form.zone_price_D2) ||
                    hasNumeric(form.zone_price_E1) ||
                    hasNumeric(form.zone_price_E2) ||
                    hasNumeric(form.price_per_m2) ||
                    hasNumeric(form.base_price)
                );
            case 'window_area_count':
                return (
                    hasNumeric(form.price_per_m2) ||
                    hasNumeric(form.price_per_window) ||
                    hasNumeric(form.base_price)
                );
            case 'per_lead_kw':
                return (
                    hasNumeric(form.base_price) ||
                    hasNumeric(form.price_per_m2) ||
                    hasNumeric(form.price_per_kw) ||
                    hasNumeric(form.kw_factor)
                );
            case 'fixed_price':
                return hasNumeric(form.base_price);
            case 'custom':
                return (
                    hasNumeric(form.base_price) ||
                    hasNumeric(form.price_per_m2) ||
                    form.pricing_notes.trim() !== '' ||
                    form.algorithm_formula.trim() !== ''
                );
            default:
                return false;
        }
    })();

    if (!hasPricing) {
        return false;
    }

    return (
        form.evidence.length > 0 ||
        form.eligibility_required_documents.trim() !== '' ||
        form.required_inputs.includes('photos') ||
        form.required_inputs.includes('homeowner_agreement')
    );
}

function numericOrNull(value: string | number): number | null {
    if (value === '' || value == null) {
        return null;
    }
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

export function buildSchemeSubmitData(form: SchemeFormData) {
    const requiredInputs = form.required_inputs;

    const payload: {
        name: string;
        description: string | null;
        active: boolean;
        metadata: SchemeMetadata;
        base_price: number | null;
        price_per_m2: number | null;
        zone_factor: number | null;
        size_factor: number | null;
        distance_factor: number | null;
        zone_prices?: Record<string, number | null>;
    } = {
        name: form.name,
        description: form.description || null,
        active: form.active,
        metadata: {
            lead_type: (form.lead_type || null) as LeadType | null,
            pricing_basis: (form.pricing_basis || null) as PricingBasis | null,
            pricing_basis_explanation: form.pricing_basis_explanation || null,
            required_inputs: requiredInputs,
            evidence: form.evidence,
            pricing_factors: {
                price_per_window: numericOrNull(form.price_per_window),
                window_count_factor: numericOrNull(form.window_count_factor),
                glazing_area_factor: numericOrNull(form.glazing_area_factor),
                quality_factor: numericOrNull(form.quality_factor),
                kw_factor: numericOrNull(form.kw_factor),
                price_per_kw: numericOrNull(form.price_per_kw),
                feasibility_factor: numericOrNull(form.feasibility_factor),
                system_type_factor: numericOrNull(form.system_type_factor),
            },
            eligibility: {
                conditions: form.eligibility_conditions || null,
                income: form.eligibility_income || null,
                property_notes: form.eligibility_property_notes || null,
                required_documents: form.eligibility_required_documents || null,
            },
            requirements: {
                zone_required: requiredInputs.includes('zone'),
                area_required: requiredInputs.includes('area_m2'),
                distance_required: requiredInputs.includes('distance'),
                constraints: form.requirements_constraints || null,
                other: form.requirements_other || null,
            },
            pricing_notes: form.pricing_notes || null,
            algorithm: {
                formula: form.algorithm_formula || null,
                notes: form.algorithm_notes || null,
            },
            technical_details: form.technical_details || null,
        },
        base_price: numericOrNull(form.base_price),
        price_per_m2: numericOrNull(form.price_per_m2),
        zone_factor: numericOrNull(form.zone_factor),
        size_factor: numericOrNull(form.size_factor),
        distance_factor: numericOrNull(form.distance_factor),
    };

    if (form.pricing_basis === 'zone_m2') {
        payload.zone_prices = {
            D1: numericOrNull(form.zone_price_D1),
            D2: numericOrNull(form.zone_price_D2),
            E1: numericOrNull(form.zone_price_E1),
            E2: numericOrNull(form.zone_price_E2),
        };
    }

    return payload;
}

export const COMMISSION_APPLIES_TO = [
    'seller_company',
    'individual_agent',
] as const;

export type CommissionAppliesTo = (typeof COMMISSION_APPLIES_TO)[number];

export const AGREEMENT_TYPES = [
    'seller_agreement',
    'buyer_agreement',
    'homeowner_agreement',
    'eligibility_requirements',
    'homeowner_consent',
] as const;

export const TERMS_TYPES = ['seller_terms', 'buyer_terms'] as const;

export const GDPR_TYPES = ['seller_gdpr', 'buyer_gdpr'] as const;

export const ZONE_CODES = ['D1', 'D2', 'E1', 'E2'] as const;
