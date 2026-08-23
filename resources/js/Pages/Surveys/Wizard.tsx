import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Alert,
    BackLink,
    Breadcrumbs,
    Button,
    Checkbox,
    FormInput,
    Select,
    StatusBadge,
    Stepper,
    Textarea,
} from '@/Components/ui';
import { useScrollToFirstError } from '@/hooks/use-scroll-to-first-error';
import {
    boolToYesNo,
    formatSurveyError,
    isMissingRequiredValue,
    isRequiredField,
    readNestedFormValue,
    scrollToSurveyError,
    surveyFieldLabel,
    toSelectOptions,
    yesNoOptions,
    yesNoToBool,
    type SurveyStepId,
} from '@/lib/survey-fields';
import type { PageProps } from '@/types';

type StepId = SurveyStepId;

const ACCESS_BOOL_KEYS = [
    'internal_access',
    'external_access',
    'loft_access',
    'roof_access',
    'wall_access',
    'plant_room_access',
    'access_safe',
    'keys_unavailable',
    'return_visit_required',
    'specialist_equipment_required',
    'scaffolding_likely',
] as const;

interface MeasurementSection {
    id?: number;
    name: string;
    section_type: string;
    length_m: string;
    width_m: string;
    height_m: string;
    calculated_area_m2: string;
    manual_area_m2: string;
    measurement_method: string;
    confidence: string;
    is_estimate: boolean;
    area_not_accessed: boolean;
    notes: string;
}

interface AccessForm {
    internal_access: boolean | null;
    external_access: boolean | null;
    loft_access: boolean | null;
    roof_access: boolean | null;
    wall_access: boolean | null;
    plant_room_access: boolean | null;
    access_safe: boolean | null;
    access_restrictions: string;
    keys_unavailable: boolean | null;
    return_visit_required: boolean | null;
    specialist_equipment_required: boolean | null;
    scaffolding_likely: boolean | null;
}

interface NoAccessForm {
    area: string;
    reason: string;
    who_prevented: string;
    refused_or_impossible: string;
    alternative_evidence: string;
    partial_inspection: boolean;
    explanation: string;
    next_action: string;
    return_visit_required: boolean;
}

interface LoftHatchForm {
    existing_hatch: string;
    hatch_dimensions: string;
    hatch_condition: string;
    suitable_for_access: boolean | null;
    enlargement_required: boolean | null;
    new_hatch_required: boolean | null;
    proposed_location: string;
    additional_labour: boolean;
    homeowner_permission_required: boolean;
    estimated_cost_eur: string;
    notes: string;
}

interface SurveyFormData {
    intent?: 'draft' | 'continue';
    advance_to?: StepId | null;
    current_step: StepId;
    survey_date: string;
    confirmed_address: string;
    property_type: string;
    occupancy_type: string;
    number_of_floors: string;
    approx_construction_year: string;
    occupied: boolean | null;
    homeowner_present: boolean | null;
    general_condition: string;
    location_confirmed: boolean;
    discrepancy_notes: string;
    access: AccessForm;
    no_access: NoAccessForm;
    loft_hatch: LoftHatchForm;
    surveyed_floor_area_m2: string;
    surveyed_installation_area_m2: string;
    measurement_method: string;
    measurement_confidence: string;
    measurement_date: string;
    measurement_notes: string;
    measurement_sections: MeasurementSection[];
    scheme_inspection: Record<string, string | boolean | null>;
    homeowner_confirmation: {
        name: string;
        relationship: string;
        permission_to_inspect: boolean;
        permission_evidence: boolean;
        permission_access: boolean;
        confirmation_date: string;
        return_visit_permission: boolean;
    };
    risks: {
        codes: string[];
        severity: string;
        affected_section: string;
        follow_up_required: boolean;
        specialist_required: boolean;
        notes: string;
    };
    surveyor_recommendation: string;
    seller_visible_notes: string;
    buyer_visible_notes: string;
    internal_audit_notes: string;
}

interface StepProgress {
    completed: string[];
    current: string;
    earliest_incomplete: string;
    max_reachable_index: number;
    reachable: string[];
}

interface SurveyPayload {
    id: number;
    lead_id: number;
    status: string;
    version: number;
    current_step: string;
    step_progress?: StepProgress;
    required_fields?: Record<string, string[]>;
    survey_date: string | null;
    last_saved_at: string | null;
    submitted_lead: Record<string, unknown>;
    catastro: Record<string, unknown>;
    confirmed: Record<string, unknown>;
    access: Record<string, unknown>;
    no_access: Record<string, unknown>;
    loft_hatch: Record<string, unknown>;
    measurements: {
        surveyed_floor_area_m2: number | null;
        surveyed_installation_area_m2: number | null;
        measurement_method: string | null;
        measurement_confidence: string | null;
        measurement_date: string | null;
        measurement_notes: string | null;
        sections: Array<Record<string, unknown>>;
    };
    comparison: {
        submitted_area_m2: number | null;
        cadastral_constructed_area_m2: number | null;
        surveyed_floor_area_m2: number | null;
        surveyed_installation_area_m2: number | null;
        auditor_approved_area_m2: number | null;
        ai_estimated_area_m2: number | null;
        flags: string[];
    };
    scheme_inspection: Record<string, unknown>;
    homeowner_confirmation: Record<string, unknown>;
    risks: Record<string, unknown>;
    surveyor_recommendation: string | null;
    seller_visible_notes: string | null;
    buyer_visible_notes: string | null;
    internal_audit_notes: string | null;
    correction_request: string | null;
    correction_sections: string[];
    evidence: Array<{
        id: number;
        category: string;
        original_name: string | null;
        caption: string | null;
        review_status: string | null;
        for_ai_measurement: boolean;
        url: string | null;
    }>;
    versions: Array<{
        id: number;
        version: number;
        status: string;
        event: string;
        notes: string | null;
        created_at: string | null;
    }>;
}

interface Options {
    steps: StepId[];
    measurement_methods: string[];
    scheme_slug: string | null;
    evidence_categories: string[];
    risk_options: string[];
    field_options?: Record<string, string[]>;
    required_fields?: Record<string, string[]>;
}

interface RoutesMap {
    draft: string;
    submit: string;
    evidence: string;
    correction: string;
    approve: string;
    reject: string;
}

interface BreadcrumbItemProp {
    label: string;
    href?: string;
}

function asBool(value: unknown): boolean {
    return value === true || value === 1 || value === '1' || value === 'true' || value === 'yes';
}

function asBoolOrNull(value: unknown): boolean | null {
    if (
        value === true ||
        value === 1 ||
        value === '1' ||
        value === 'true' ||
        value === 'yes'
    ) {
        return true;
    }
    if (
        value === false ||
        value === 0 ||
        value === '0' ||
        value === 'false' ||
        value === 'no'
    ) {
        return false;
    }
    return null;
}

function asStr(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }
    return String(value);
}

function sectionFromServer(row: Record<string, unknown>): MeasurementSection {
    return {
        id: typeof row.id === 'number' ? row.id : undefined,
        name: asStr(row.name),
        section_type: asStr(row.section_type),
        length_m: asStr(row.length_m),
        width_m: asStr(row.width_m),
        height_m: asStr(row.height_m),
        calculated_area_m2: asStr(row.calculated_area_m2),
        manual_area_m2: asStr(row.manual_area_m2),
        measurement_method: asStr(row.measurement_method),
        confidence: asStr(row.confidence),
        is_estimate: asBool(row.is_estimate),
        area_not_accessed: asBool(row.area_not_accessed),
        notes: asStr(row.notes),
    };
}

function ReadOnlyGrid({
    rows,
}: {
    rows: Array<{ label: string; value: string }>;
}) {
    return (
        <dl className="grid gap-3 sm:grid-cols-2">
            {rows.map((row) => (
                <div key={row.label} className="space-y-1">
                    <dt className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                        {row.label}
                    </dt>
                    <dd className="text-sm text-rml-text">{row.value || '—'}</dd>
                </div>
            ))}
        </dl>
    );
}

function ToggleField({
    label,
    checked,
    onChange,
    disabled,
    required,
}: {
    label: string;
    checked: boolean;
    onChange: (value: boolean) => void;
    disabled?: boolean;
    required?: boolean;
}) {
    return (
        <label className="flex items-center gap-2 text-sm text-rml-text">
            <Checkbox
                checked={checked}
                disabled={disabled}
                onChange={(event) => onChange(event.target.checked)}
            />
            <span>
                {label}
                {required && <span className="ml-0.5 text-rml-red">*</span>}
            </span>
        </label>
    );
}

export default function SurveyWizard({
    survey,
    options,
    portal,
    can_review = false,
    can_edit = true,
    routes,
    return_href,
    breadcrumbs = [],
}: {
    survey: SurveyPayload;
    options: Options;
    portal: string;
    can_review?: boolean;
    can_edit?: boolean;
    routes: RoutesMap;
    return_href: string;
    breadcrumbs?: BreadcrumbItemProp[];
}) {
    const { translations, app, flash } = usePage<PageProps>().props;
    const t = translations.survey;
    const catastroT = (
        translations as PageProps['translations'] & {
            catastro?: {
                seller_statuses?: Record<string, string>;
            };
        }
    ).catastro;
    const steps = options.steps;
    const stepProgress = survey.step_progress;
    const completed = stepProgress?.completed ?? [];
    const reachable =
        stepProgress?.reachable ??
        (steps.length > 0 ? [steps[0]] : []);
    const requiredFields =
        options.required_fields ?? survey.required_fields ?? {};
    const fieldOptions = options.field_options ?? {};
    const optionLabels = (t.option_labels ??
        t.methods ??
        {}) as Record<string, string>;

    const resolvedInitial = (stepProgress?.current ??
        survey.current_step) as StepId;
    const initialStep = (steps.includes(resolvedInitial)
        ? resolvedInitial
        : 'property') as StepId;
    const [step, setStep] = useState<StepId>(initialStep);
    const [evidenceCategory, setEvidenceCategory] = useState(
        options.evidence_categories[0] ?? 'front_exterior',
    );
    const [evidenceFile, setEvidenceFile] = useState<File | null>(null);
    const [forAi, setForAi] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [correctionText, setCorrectionText] = useState('');
    const [approvedArea, setApprovedArea] = useState(
        asStr(survey.comparison.auditor_approved_area_m2),
    );
    const [approvedInstallArea, setApprovedInstallArea] = useState('');
    const [decisionNotes, setDecisionNotes] = useState('');
    const [localErrors, setLocalErrors] = useState<Record<string, string>>({});

    const accessDefaults = survey.access ?? {};
    const homeownerDefaults = survey.homeowner_confirmation ?? {};
    const risksDefaults = survey.risks ?? {};
    const schemeDefaults = survey.scheme_inspection ?? {};
    const noAccessDefaults = survey.no_access ?? {};
    const loftDefaults = survey.loft_hatch ?? {};

    const { data, setData, post, processing, errors, isDirty, transform, setError, clearErrors } =
        useForm<SurveyFormData>({
            current_step: initialStep,
            survey_date: asStr(survey.survey_date),
            confirmed_address: asStr(survey.confirmed.confirmed_address),
            property_type: asStr(survey.confirmed.property_type),
            occupancy_type: asStr(survey.confirmed.occupancy_type),
            number_of_floors: asStr(survey.confirmed.number_of_floors),
            approx_construction_year: asStr(
                survey.confirmed.approx_construction_year,
            ),
            occupied: asBoolOrNull(survey.confirmed.occupied),
            homeowner_present: asBoolOrNull(survey.confirmed.homeowner_present),
            general_condition: asStr(survey.confirmed.general_condition),
            location_confirmed: asBool(survey.confirmed.location_confirmed),
            discrepancy_notes: asStr(survey.confirmed.discrepancy_notes),
            access: {
                internal_access: asBoolOrNull(accessDefaults.internal_access),
                external_access: asBoolOrNull(accessDefaults.external_access),
                loft_access: asBoolOrNull(accessDefaults.loft_access),
                roof_access: asBoolOrNull(accessDefaults.roof_access),
                wall_access: asBoolOrNull(accessDefaults.wall_access),
                plant_room_access: asBoolOrNull(
                    accessDefaults.plant_room_access,
                ),
                access_safe: asBoolOrNull(accessDefaults.access_safe),
                access_restrictions: asStr(accessDefaults.access_restrictions),
                keys_unavailable: asBoolOrNull(accessDefaults.keys_unavailable),
                return_visit_required: asBoolOrNull(
                    accessDefaults.return_visit_required,
                ),
                specialist_equipment_required: asBoolOrNull(
                    accessDefaults.specialist_equipment_required,
                ),
                scaffolding_likely: asBoolOrNull(
                    accessDefaults.scaffolding_likely,
                ),
            } satisfies AccessForm,
            no_access: {
                area: asStr(noAccessDefaults.area),
                reason: asStr(noAccessDefaults.reason),
                who_prevented: asStr(noAccessDefaults.who_prevented),
                refused_or_impossible: asStr(
                    noAccessDefaults.refused_or_impossible,
                ),
                alternative_evidence: asStr(
                    noAccessDefaults.alternative_evidence,
                ),
                partial_inspection: asBool(noAccessDefaults.partial_inspection),
                explanation: asStr(noAccessDefaults.explanation),
                next_action: asStr(noAccessDefaults.next_action),
                return_visit_required: asBool(
                    noAccessDefaults.return_visit_required,
                ),
            },
            loft_hatch: {
                existing_hatch: asStr(loftDefaults.existing_hatch),
                hatch_dimensions: asStr(loftDefaults.hatch_dimensions),
                hatch_condition: asStr(loftDefaults.hatch_condition),
                suitable_for_access: asBoolOrNull(
                    loftDefaults.suitable_for_access,
                ),
                enlargement_required: asBoolOrNull(
                    loftDefaults.enlargement_required,
                ),
                new_hatch_required: asBoolOrNull(
                    loftDefaults.new_hatch_required,
                ),
                proposed_location: asStr(loftDefaults.proposed_location),
                additional_labour: asBool(loftDefaults.additional_labour),
                homeowner_permission_required: asBool(
                    loftDefaults.homeowner_permission_required,
                ),
                estimated_cost_eur: asStr(loftDefaults.estimated_cost_eur),
                notes: asStr(loftDefaults.notes),
            },
            surveyed_floor_area_m2: asStr(
                survey.measurements.surveyed_floor_area_m2,
            ),
            surveyed_installation_area_m2: asStr(
                survey.measurements.surveyed_installation_area_m2,
            ),
            measurement_method: asStr(survey.measurements.measurement_method),
            measurement_confidence: asStr(
                survey.measurements.measurement_confidence,
            ),
            measurement_date: asStr(survey.measurements.measurement_date),
            measurement_notes: asStr(survey.measurements.measurement_notes),
            measurement_sections: (survey.measurements.sections ?? []).map(
                (row) => sectionFromServer(row),
            ),
            scheme_inspection: {
                ...schemeDefaults,
            } as Record<string, string | boolean | null>,
            homeowner_confirmation: {
                name: asStr(homeownerDefaults.name),
                relationship: asStr(homeownerDefaults.relationship),
                permission_to_inspect: asBool(
                    homeownerDefaults.permission_to_inspect,
                ),
                permission_evidence: asBool(
                    homeownerDefaults.permission_evidence,
                ),
                permission_access: asBool(homeownerDefaults.permission_access),
                confirmation_date: asStr(homeownerDefaults.confirmation_date),
                return_visit_permission: asBool(
                    homeownerDefaults.return_visit_permission,
                ),
            },
            risks: {
                codes: Array.isArray(risksDefaults.codes)
                    ? (risksDefaults.codes as string[])
                    : [],
                severity: asStr(risksDefaults.severity),
                affected_section: asStr(
                    risksDefaults.affected_section ??
                        risksDefaults.installation_impact,
                ),
                follow_up_required: asBool(risksDefaults.follow_up_required),
                specialist_required: asBool(risksDefaults.specialist_required),
                notes: asStr(risksDefaults.notes),
            },
            surveyor_recommendation: asStr(survey.surveyor_recommendation),
            seller_visible_notes: asStr(survey.seller_visible_notes),
            buyer_visible_notes: asStr(survey.buyer_visible_notes),
            internal_audit_notes: asStr(survey.internal_audit_notes),
        });

    useEffect(() => {
        const serverStep = (stepProgress?.current ?? '') as StepId;
        if (serverStep && steps.includes(serverStep) && serverStep !== step) {
            setStep(serverStep);
            setData('current_step', serverStep);
        }
        // Sync only when server step_progress.current changes after continue.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [stepProgress?.current]);

    useScrollToFirstError({ ...errors, ...localErrors });

    const stepIndex = steps.indexOf(step);
    const schemeSlug = options.scheme_slug ?? '';
    const fieldLabels = (t.fields as Record<string, string> | undefined) ?? {};
    const formErrors = useMemo(() => {
        const merged: Record<string, string | undefined> = {
            ...(errors as Record<string, string | undefined>),
            ...localErrors,
        };
        const resolved: Record<string, string | undefined> = {};
        for (const [key, value] of Object.entries(merged)) {
            if (!value) {
                resolved[key] = value;
                continue;
            }
            if (value.includes(':field') || value.includes(':category')) {
                resolved[key] = formatSurveyError(
                    value,
                    {
                        field: surveyFieldLabel(key, fieldLabels),
                        category: key.split('.').pop() ?? key,
                    },
                    value,
                );
            } else {
                resolved[key] = value;
            }
        }
        return resolved;
    }, [errors, localErrors, fieldLabels]);
    const showNoAccess =
        data.access.access_safe === false ||
        data.access.keys_unavailable === true ||
        (data.access.internal_access === false &&
            data.access.external_access === false) ||
        data.access.loft_access === false;
    const statusLabel =
        t.statuses?.[survey.status as keyof typeof t.statuses] ?? survey.status;
    const ynOptions = yesNoOptions(t.yes, t.no);
    const fieldSelect = (key: string, includeBlank = true) =>
        toSelectOptions(fieldOptions[key] ?? [], optionLabels, includeBlank);
    const req = (fieldKey: string) =>
        isRequiredField(requiredFields, step, fieldKey);
    const allPriorComplete = steps
        .filter((id) => id !== 'review')
        .every((id) => completed.includes(id));

    const homeHref =
        portal === 'admin'
            ? route('admin.dashboard')
            : portal === 'seller'
              ? route('seller.dashboard')
              : route('auditor.dashboard');

    const submitted = survey.submitted_lead;
    const catastro = survey.catastro;
    const showCatastroDetails = can_review;
    const sellerCatastroStatus =
        catastro.seller_status &&
        typeof catastro.seller_status === 'object' &&
        'key' in (catastro.seller_status as object)
            ? String((catastro.seller_status as { key: string }).key)
            : 'not_checked';
    const catastroStatus = showCatastroDetails
        ? asStr(catastro.verification_status) || 'not_checked'
        : sellerCatastroStatus;

    const comparisonRows = useMemo(
        () => {
            const rows = [
                {
                    label: t.fields.submitted_area,
                    value: asStr(survey.comparison.submitted_area_m2),
                },
                {
                    label: t.fields.cadastral_constructed_area,
                    value: asStr(survey.comparison.cadastral_constructed_area_m2),
                },
                {
                    label: t.fields.surveyed_floor_area,
                    value: asStr(survey.comparison.surveyed_floor_area_m2),
                },
                {
                    label: t.fields.surveyed_installation_area,
                    value: asStr(survey.comparison.surveyed_installation_area_m2),
                },
                {
                    label: t.fields.auditor_approved_area,
                    value: asStr(survey.comparison.auditor_approved_area_m2),
                },
                {
                    label: t.fields.ai_estimated_area,
                    value: asStr(survey.comparison.ai_estimated_area_m2),
                },
            ];

            if (!showCatastroDetails) {
                return rows.filter(
                    (row) => row.label !== t.fields.cadastral_constructed_area,
                );
            }

            return rows;
        },
        [survey.comparison, t.fields, showCatastroDetails],
    );

    const applyLocalRequiredErrors = (): boolean => {
        const keys = [...(requiredFields[step] ?? [])];
        const next: Record<string, string> = {};
        const errorMessages =
            (t.errors as Record<string, string> | undefined) ?? {};
        const fieldLabels = (t.fields as Record<string, string> | undefined) ?? {};
        const requiredFor = (key: string) =>
            formatSurveyError(
                errorMessages.field_required,
                { field: surveyFieldLabel(key, fieldLabels) },
                `${surveyFieldLabel(key, fieldLabels)} is required.`,
            );

        if (step === 'property' && schemeSlug === 'insulation') {
            if (!keys.includes('loft_hatch.existing_hatch')) {
                keys.push('loft_hatch.existing_hatch');
            }
        }

        if (step === 'property' && showNoAccess) {
            keys.push(
                'no_access.area',
                'no_access.reason',
                'no_access.refused_or_impossible',
            );
        }

        if (
            step === 'property' &&
            (data.loft_hatch.new_hatch_required === true ||
                asStr(data.loft_hatch.new_hatch_required) === 'yes')
        ) {
            keys.push('loft_hatch.proposed_location');
        }

        const evidenceCategoryKeys =
            step === 'evidence'
                ? keys.filter((key) => !key.includes('.'))
                : [];
        const formKeys = keys.filter((key) => !evidenceCategoryKeys.includes(key));

        for (const key of formKeys) {
            const value =
                key === 'measurement_sections'
                    ? data.measurement_sections
                    : readNestedFormValue(
                          data as unknown as Record<string, unknown>,
                          key,
                      );

            if (isMissingRequiredValue(key, value)) {
                if (key === 'measurement_sections') {
                    next[key] =
                        errorMessages.measurement_section_required ??
                        'Add at least one measured section before continuing.';
                } else if (key === 'surveyed_installation_area_m2') {
                    next[key] =
                        errorMessages.measurement_required ??
                        'Enter a surveyed installation area greater than zero.';
                } else {
                    next[key] = requiredFor(key);
                }
            }
        }

        for (const category of evidenceCategoryKeys) {
            const present = (survey.evidence ?? []).some(
                (item) => item.category === category,
            );
            if (!present) {
                next[`evidence.${category}`] = formatSurveyError(
                    errorMessages.evidence_required,
                    { category },
                    `Required evidence missing: ${category}`,
                );
            }
        }

        if (step === 'measurements') {
            if (data.measurement_sections.length === 0) {
                next.measurement_sections =
                    errorMessages.measurement_section_required ??
                    'Add at least one measured section before continuing.';
            }

            data.measurement_sections.forEach((section, index) => {
                if (!section.section_type) {
                    next[`measurement_sections.${index}.section_type`] =
                        requiredFor('section_type');
                }

                const calculated = parseFloat(section.calculated_area_m2);
                const manual = parseFloat(section.manual_area_m2);
                const hasArea =
                    (!Number.isNaN(manual) && manual > 0) ||
                    (!Number.isNaN(calculated) && calculated > 0);

                if (section.area_not_accessed && !section.is_estimate) {
                    next[`measurement_sections.${index}.is_estimate`] =
                        errorMessages.estimate_required_when_not_accessed ??
                        'Mark the section as an estimate when the area was not accessed.';
                }

                if (!section.area_not_accessed && !hasArea) {
                    next[`measurement_sections.${index}.length_m`] =
                        errorMessages.measurement_required ??
                        'Enter length and width, or a manual area greater than zero.';
                    next[`measurement_sections.${index}.calculated_area_m2`] =
                        errorMessages.measurement_required ??
                        'Enter length and width, or a manual area greater than zero.';
                }

                if (
                    section.manual_area_m2.trim() !== '' &&
                    !Number.isNaN(manual) &&
                    manual > 0 &&
                    section.notes.trim() === ''
                ) {
                    next[`measurement_sections.${index}.notes`] =
                        errorMessages.manual_area_reason_required ??
                        'Explain why a manual area override was used.';
                }
            });
        }

        clearErrors();
        setLocalErrors(next);
        Object.entries(next).forEach(([key, message]) => {
            setError(key as keyof SurveyFormData, message);
        });

        const firstKey = Object.keys(next)[0];
        if (firstKey) {
            scrollToSurveyError(firstKey);
        }

        return Object.keys(next).length > 0;
    };

    const saveDraft = () => {
        if (!can_edit) {
            return;
        }
        setLocalErrors({});
        transform((form) => ({
            ...form,
            intent: 'draft' as const,
            current_step: step,
            advance_to: null,
        }));
        post(routes.draft, {
            preserveScroll: true,
        });
    };

    const goNext = () => {
        const next = steps[Math.min(stepIndex + 1, steps.length - 1)] as StepId;
        if (!can_edit) {
            if (reachable.includes(next) || completed.includes(next)) {
                setStep(next);
                setData('current_step', next);
            }
            return;
        }

        if (applyLocalRequiredErrors()) {
            return;
        }

        transform((form) => ({
            ...form,
            intent: 'continue' as const,
            current_step: step,
            advance_to: next,
        }));
        post(routes.draft, {
            preserveScroll: false,
            onSuccess: () => {
                setLocalErrors({});
            },
            onError: (pageErrors) => {
                const first = Object.keys(pageErrors)[0];
                scrollToSurveyError(first);
            },
        });
    };

    const goBack = () => {
        for (let i = stepIndex - 1; i >= 0; i -= 1) {
            const prev = steps[i];
            if (reachable.includes(prev) || completed.includes(prev)) {
                setStep(prev);
                setData('current_step', prev);
                return;
            }
        }
    };

    const submitSurvey = (event: FormEvent) => {
        event.preventDefault();
        if (!can_edit || uploading || !allPriorComplete) {
            return;
        }
        transform((form) => ({ ...form, current_step: 'review' }));
        post(routes.submit);
    };

    const canSelectStep = (id: StepId) =>
        reachable.includes(id) || completed.includes(id) || id === step;

    const uploadEvidence = () => {
        if (!evidenceFile || !can_edit) {
            return;
        }
        setUploading(true);
        router.post(
            routes.evidence,
            {
                file: evidenceFile,
                category: evidenceCategory,
                survey_section: step,
                for_ai_measurement: forAi,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setUploading(false);
                    setEvidenceFile(null);
                },
            },
        );
    };

    const updateAccess = (key: string, value: boolean | string | null) => {
        setData('access', { ...data.access, [key]: value });
    };

    const updateHomeowner = (key: string, value: boolean | string) => {
        setData('homeowner_confirmation', {
            ...data.homeowner_confirmation,
            [key]: value,
        });
    };

    const updateScheme = (key: string, value: string | boolean | null) => {
        setData('scheme_inspection', {
            ...data.scheme_inspection,
            [key]: value,
        });
    };

    const updateSection = (
        index: number,
        key: keyof MeasurementSection,
        value: string | boolean,
    ) => {
        const sections = [...data.measurement_sections];
        const current = { ...sections[index], [key]: value };
        if (key === 'length_m' || key === 'width_m') {
            const length = parseFloat(
                key === 'length_m' ? String(value) : current.length_m,
            );
            const width = parseFloat(
                key === 'width_m' ? String(value) : current.width_m,
            );
            if (!Number.isNaN(length) && !Number.isNaN(width)) {
                current.calculated_area_m2 = (length * width).toFixed(2);
            }
        }
        sections[index] = current;
        setData('measurement_sections', sections);
    };

    const addSection = () => {
        setData('measurement_sections', [
            ...data.measurement_sections,
            {
                name: '',
                section_type: '',
                length_m: '',
                width_m: '',
                height_m: '',
                calculated_area_m2: '',
                manual_area_m2: '',
                measurement_method: data.measurement_method,
                confidence: '',
                is_estimate: false,
                area_not_accessed: false,
                notes: '',
            },
        ]);
    };

    const toggleRisk = (code: string) => {
        const codes = new Set(data.risks.codes);
        if (codes.has(code)) {
            codes.delete(code);
        } else {
            codes.add(code);
        }
        setData('risks', { ...data.risks, codes: Array.from(codes) });
    };

    const stepperItems = steps.map((id) => ({
        id,
        label: t.steps[id],
        complete: completed.includes(id) && id !== step,
        disabled: !reachable.includes(id) && id !== step,
    }));

    return (
        <AppLayout
            title={t.title}
            subtitle={t.subtitle}
            breadcrumbs={
                <Breadcrumbs
                    homeHref={homeHref}
                    homeLabel={translations.common?.home ?? 'Home'}
                    items={breadcrumbs}
                />
            }
            headerActions={
                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge label={statusLabel} tone="neutral" />
                    {survey.last_saved_at && (
                        <span className="text-xs text-rml-muted">
                            {t.last_saved}:{' '}
                            {new Date(survey.last_saved_at).toLocaleString(
                                app.locale,
                            )}
                        </span>
                    )}
                </div>
            }
        >
            <Head title={t.title} />

            <div className="flex flex-wrap items-center gap-3">
                <BackLink
                    href={return_href}
                    label={t.back}
                    useHistory={false}
                />
                <p className="text-sm text-rml-muted">
                    {asStr(submitted.lead_reference)} · v{survey.version} ·{' '}
                    {portal}
                </p>
            </div>

            {flash?.success && (
                <Alert variant="success">{String(flash.success)}</Alert>
            )}
            {survey.correction_request && (
                <Alert variant="warning" title={t.fields.correction_request}>
                    {survey.correction_request}
                </Alert>
            )}
            {isDirty && can_edit && (
                <Alert variant="warning">{t.unsaved_warning}</Alert>
            )}
            <Alert variant="info">
                {t.required_info ?? 'Required information'}
            </Alert>
            {Object.keys(formErrors).length > 0 && (
                <div data-validation-summary>
                    <Alert
                        variant="error"
                        title={
                            (t.errors as Record<string, string> | undefined)
                                ?.step_incomplete ??
                            'Complete the required fields before continuing.'
                        }
                    >
                        <ul className="mt-1 list-disc space-y-1 pl-4 text-sm">
                            {Array.from(
                                new Set(
                                    Object.values(formErrors).filter(
                                        (message): message is string =>
                                            Boolean(message),
                                    ),
                                ),
                            ).map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    </Alert>
                </div>
            )}

            <Stepper
                steps={stepperItems}
                current={step}
                onChange={(id) => {
                    const next = id as StepId;
                    if (canSelectStep(next)) {
                        setStep(next);
                        setData('current_step', next);
                    }
                }}
            />

            <form className="space-y-4" onSubmit={submitSurvey}>
                {step === 'property' && (
                    <div className="space-y-4">
                        <section className="rml-card space-y-3 p-4 sm:p-5">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="text-base font-semibold text-rml-text">
                                    {t.sections.submitted_lead}
                                </h2>
                                <span className="text-xs text-rml-muted">
                                    {t.read_only}
                                </span>
                            </div>
                            <ReadOnlyGrid
                                rows={[
                                    {
                                        label: t.fields.lead_reference,
                                        value: asStr(submitted.lead_reference),
                                    },
                                    {
                                        label: t.fields.submitted_address,
                                        value: [
                                            submitted.address_line_1,
                                            submitted.city,
                                            submitted.postcode,
                                        ]
                                            .filter(Boolean)
                                            .join(', '),
                                    },
                                    {
                                        label: t.fields.property_type,
                                        value: asStr(submitted.property_type),
                                    },
                                    {
                                        label: t.fields.submitted_area,
                                        value: asStr(
                                            submitted.submitted_property_area_m2,
                                        ),
                                    },
                                    {
                                        label: 'Scheme',
                                        value: asStr(submitted.scheme),
                                    },
                                    {
                                        label: 'Zone',
                                        value: asStr(submitted.zone),
                                    },
                                    {
                                        label: 'Cadastral reference',
                                        value: asStr(
                                            submitted.cadastral_reference,
                                        ),
                                    },
                                    {
                                        label: 'Seller',
                                        value: asStr(submitted.seller),
                                    },
                                ]}
                            />
                        </section>

                        <section className="rml-card space-y-3 p-4 sm:p-5">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="text-base font-semibold text-rml-text">
                                    {t.sections.catastro}
                                </h2>
                                <div className="flex flex-col items-end gap-1">
                                    <StatusBadge
                                        label={
                                            showCatastroDetails
                                                ? (t.catastro_statuses?.[
                                                      catastroStatus as keyof typeof t.catastro_statuses
                                                  ] ?? catastroStatus)
                                                : (catastroT?.seller_statuses?.[
                                                      catastroStatus
                                                  ] ?? catastroStatus)
                                        }
                                        tone={
                                            showCatastroDetails
                                                ? catastroStatus === 'matched'
                                                    ? 'success'
                                                    : catastroStatus ===
                                                            'mismatch_detected' ||
                                                        catastroStatus ===
                                                            'lookup_failed'
                                                      ? 'danger'
                                                      : catastroStatus ===
                                                              'service_unavailable'
                                                        ? 'info'
                                                        : 'neutral'
                                                : catastroStatus === 'verified'
                                                  ? 'success'
                                                  : catastroStatus ===
                                                      'needs_review'
                                                    ? 'warning'
                                                    : catastroStatus ===
                                                        'failed'
                                                      ? 'danger'
                                                      : 'neutral'
                                        }
                                    />
                                    {showCatastroDetails && (
                                        <p className="max-w-xs text-right text-xs text-rml-muted">
                                            {(
                                                t.catastro_status_hints as
                                                    | Record<string, string>
                                                    | undefined
                                            )?.[catastroStatus] ??
                                                t.not_ownership}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <Alert variant="info">{t.catastro_disclaimer}</Alert>
                            {showCatastroDetails ? (
                                <ReadOnlyGrid
                                    rows={[
                                        {
                                            label: 'Provider',
                                            value:
                                                t.providers?.[
                                                    asStr(
                                                        catastro.provider,
                                                    ) as keyof typeof t.providers
                                                ] ?? asStr(catastro.provider),
                                        },
                                        {
                                            label: 'Cadastral reference',
                                            value: asStr(
                                                catastro.cadastral_reference,
                                            ),
                                        },
                                        {
                                            label: t.fields
                                                .cadastral_constructed_area,
                                            value: asStr(
                                                catastro.constructed_area_m2,
                                            ),
                                        },
                                        {
                                            label: t.fields
                                                .cadastral_parcel_area,
                                            value: asStr(
                                                catastro.parcel_area_m2,
                                            ),
                                        },
                                        {
                                            label: 'Lookup',
                                            value:
                                                asStr(catastro.lookup_at) ||
                                                '—',
                                        },
                                        {
                                            label: 'Match summary',
                                            value: asStr(
                                                catastro.match_summary,
                                            ),
                                        },
                                    ]}
                                />
                            ) : (
                                <ReadOnlyGrid
                                    rows={[
                                        {
                                            label: 'Cadastral reference',
                                            value:
                                                asStr(
                                                    catastro.cadastral_reference,
                                                ) ||
                                                asStr(
                                                    submitted.cadastral_reference,
                                                ),
                                        },
                                    ]}
                                />
                            )}
                        </section>

                        <section className="rml-card space-y-4 p-4 sm:p-5">
                            <h2 className="text-base font-semibold text-rml-text">
                                {t.sections.confirmed}
                            </h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <FormInput
                                    label={t.fields.survey_date}
                                    type="date"
                                    value={data.survey_date}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData('survey_date', e.target.value)
                                    }
                                    error={formErrors.survey_date}
                                    required={req('survey_date')}
                                />
                                <FormInput
                                    label={t.fields.confirmed_address}
                                    value={data.confirmed_address}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'confirmed_address',
                                            e.target.value,
                                        )
                                    }
                                    error={formErrors.confirmed_address}
                                    required={req('confirmed_address')}
                                />
                                <Select
                                    label={t.fields.property_type}
                                    value={data.property_type}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData('property_type', e.target.value)
                                    }
                                    options={fieldSelect('property_types')}
                                    error={formErrors.property_type}
                                    required={req('property_type')}
                                />
                                <Select
                                    label={t.fields.occupancy_type}
                                    value={data.occupancy_type}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'occupancy_type',
                                            e.target.value,
                                        )
                                    }
                                    options={fieldSelect('occupancy_types')}
                                    error={formErrors.occupancy_type}
                                    required={req('occupancy_type')}
                                />
                                <FormInput
                                    label={t.fields.number_of_floors}
                                    type="number"
                                    value={data.number_of_floors}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'number_of_floors',
                                            e.target.value,
                                        )
                                    }
                                />
                                <FormInput
                                    label={t.fields.approx_construction_year}
                                    type="number"
                                    value={data.approx_construction_year}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'approx_construction_year',
                                            e.target.value,
                                        )
                                    }
                                />
                                <Select
                                    label={t.fields.general_condition}
                                    value={data.general_condition}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'general_condition',
                                            e.target.value,
                                        )
                                    }
                                    options={fieldSelect('general_conditions')}
                                    error={formErrors.general_condition}
                                    required={req('general_condition')}
                                />
                                <Select
                                    label={t.fields.occupied}
                                    value={boolToYesNo(data.occupied)}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'occupied',
                                            yesNoToBool(e.target.value),
                                        )
                                    }
                                    options={ynOptions}
                                    error={formErrors.occupied}
                                    required={req('occupied')}
                                />
                                <Select
                                    label={t.fields.homeowner_present}
                                    value={boolToYesNo(data.homeowner_present)}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'homeowner_present',
                                            yesNoToBool(e.target.value),
                                        )
                                    }
                                    options={ynOptions}
                                    error={formErrors.homeowner_present}
                                    required={req('homeowner_present')}
                                />
                            </div>
                            <div className="flex flex-wrap gap-4">
                                <ToggleField
                                    label={t.fields.location_confirmed}
                                    checked={data.location_confirmed}
                                    disabled={!can_edit}
                                    required={req('location_confirmed')}
                                    onChange={(v) =>
                                        setData('location_confirmed', v)
                                    }
                                />
                            </div>
                            {formErrors.location_confirmed && (
                                <p className="text-sm text-rml-red">
                                    {formErrors.location_confirmed}
                                </p>
                            )}
                            <Textarea
                                label={t.fields.discrepancy_notes}
                                value={data.discrepancy_notes}
                                disabled={!can_edit}
                                onChange={(e) =>
                                    setData('discrepancy_notes', e.target.value)
                                }
                            />
                        </section>

                        <section className="rml-card space-y-4 p-4 sm:p-5">
                            <h2 className="text-base font-semibold text-rml-text">
                                {t.sections.access}
                            </h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {ACCESS_BOOL_KEYS.map((key) => (
                                    <Select
                                        key={key}
                                        label={
                                            t.access_fields[
                                                key as keyof typeof t.access_fields
                                            ]
                                        }
                                        value={boolToYesNo(data.access[key])}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateAccess(
                                                key,
                                                yesNoToBool(e.target.value),
                                            )
                                        }
                                        options={ynOptions}
                                        error={formErrors[`access.${key}`]}
                                        required={req(`access.${key}`)}
                                    />
                                ))}
                            </div>
                            <FormInput
                                label={t.access_fields.access_restrictions}
                                value={asStr(data.access.access_restrictions)}
                                disabled={!can_edit}
                                onChange={(e) =>
                                    updateAccess(
                                        'access_restrictions',
                                        e.target.value,
                                    )
                                }
                            />
                        </section>

                        {showNoAccess && (
                            <section className="rml-card space-y-3 p-4 sm:p-5">
                                <h2 className="text-base font-semibold text-rml-text">
                                    {t.sections.no_access}
                                </h2>
                                {formErrors.no_access && (
                                    <Alert variant="error">
                                        {formErrors.no_access}
                                    </Alert>
                                )}
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <FormInput
                                        label={t.no_access_fields.area}
                                        value={data.no_access.area}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('no_access', {
                                                ...data.no_access,
                                                area: e.target.value,
                                            })
                                        }
                                        error={formErrors['no_access.area']}
                                        required
                                    />
                                    <Select
                                        label={t.no_access_fields.reason}
                                        value={data.no_access.reason}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('no_access', {
                                                ...data.no_access,
                                                reason: e.target.value,
                                            })
                                        }
                                        options={fieldSelect(
                                            'access_unavailable_reasons',
                                        )}
                                        error={formErrors['no_access.reason']}
                                        required
                                    />
                                    <FormInput
                                        label={t.no_access_fields.who_prevented}
                                        value={data.no_access.who_prevented}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('no_access', {
                                                ...data.no_access,
                                                who_prevented: e.target.value,
                                            })
                                        }
                                    />
                                    <Select
                                        label={
                                            t.no_access_fields
                                                .refused_or_impossible
                                        }
                                        value={
                                            data.no_access.refused_or_impossible
                                        }
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('no_access', {
                                                ...data.no_access,
                                                refused_or_impossible:
                                                    e.target.value,
                                            })
                                        }
                                        options={fieldSelect(
                                            'refused_or_impossible',
                                        )}
                                        error={
                                            formErrors[
                                                'no_access.refused_or_impossible'
                                            ]
                                        }
                                        required
                                    />
                                    <FormInput
                                        label={
                                            t.no_access_fields
                                                .alternative_evidence
                                        }
                                        value={
                                            data.no_access.alternative_evidence
                                        }
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('no_access', {
                                                ...data.no_access,
                                                alternative_evidence:
                                                    e.target.value,
                                            })
                                        }
                                    />
                                    <FormInput
                                        label={t.no_access_fields.explanation}
                                        value={data.no_access.explanation}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('no_access', {
                                                ...data.no_access,
                                                explanation: e.target.value,
                                            })
                                        }
                                        error={
                                            formErrors['no_access.explanation']
                                        }
                                    />
                                    <FormInput
                                        label={t.no_access_fields.next_action}
                                        value={data.no_access.next_action}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('no_access', {
                                                ...data.no_access,
                                                next_action: e.target.value,
                                            })
                                        }
                                    />
                                    <ToggleField
                                        label={
                                            t.no_access_fields
                                                .partial_inspection
                                        }
                                        checked={
                                            data.no_access.partial_inspection
                                        }
                                        disabled={!can_edit}
                                        onChange={(v) =>
                                            setData('no_access', {
                                                ...data.no_access,
                                                partial_inspection: v,
                                            })
                                        }
                                    />
                                    <ToggleField
                                        label={
                                            t.access_fields.return_visit_required
                                        }
                                        checked={
                                            data.no_access.return_visit_required
                                        }
                                        disabled={!can_edit}
                                        onChange={(v) =>
                                            setData('no_access', {
                                                ...data.no_access,
                                                return_visit_required: v,
                                            })
                                        }
                                    />
                                </div>
                            </section>
                        )}

                        {schemeSlug === 'insulation' && (
                            <section className="rml-card space-y-3 p-4 sm:p-5">
                                <h2 className="text-base font-semibold text-rml-text">
                                    {t.sections.loft_hatch}
                                </h2>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Select
                                        name="loft_hatch.existing_hatch"
                                        label={
                                            (t.fields as Record<string, string>)
                                                .existing_hatch ??
                                            'Existing loft hatch'
                                        }
                                        value={asStr(
                                            data.loft_hatch.existing_hatch,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('loft_hatch', {
                                                ...data.loft_hatch,
                                                existing_hatch: e.target.value,
                                            })
                                        }
                                        options={ynOptions}
                                        error={
                                            formErrors[
                                                'loft_hatch.existing_hatch'
                                            ]
                                        }
                                        required={
                                            schemeSlug === 'insulation' ||
                                            req('loft_hatch.existing_hatch')
                                        }
                                    />
                                    <FormInput
                                        label={
                                            (t.fields as Record<string, string>)
                                                .hatch_dimensions ??
                                            'Hatch dimensions'
                                        }
                                        value={asStr(
                                            data.loft_hatch.hatch_dimensions,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('loft_hatch', {
                                                ...data.loft_hatch,
                                                hatch_dimensions:
                                                    e.target.value,
                                            })
                                        }
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .hatch_condition ??
                                            'Hatch condition'
                                        }
                                        value={asStr(
                                            data.loft_hatch.hatch_condition,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('loft_hatch', {
                                                ...data.loft_hatch,
                                                hatch_condition: e.target.value,
                                            })
                                        }
                                        options={fieldSelect(
                                            'hatch_conditions',
                                        )}
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .suitable_for_access ??
                                            'Suitable for access'
                                        }
                                        value={boolToYesNo(
                                            data.loft_hatch.suitable_for_access,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('loft_hatch', {
                                                ...data.loft_hatch,
                                                suitable_for_access:
                                                    yesNoToBool(e.target.value),
                                            })
                                        }
                                        options={ynOptions}
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .enlargement_required ??
                                            'Enlargement required'
                                        }
                                        value={boolToYesNo(
                                            data.loft_hatch.enlargement_required,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('loft_hatch', {
                                                ...data.loft_hatch,
                                                enlargement_required:
                                                    yesNoToBool(e.target.value),
                                            })
                                        }
                                        options={ynOptions}
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .new_hatch_required ??
                                            'New hatch required'
                                        }
                                        value={boolToYesNo(
                                            data.loft_hatch.new_hatch_required,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('loft_hatch', {
                                                ...data.loft_hatch,
                                                new_hatch_required:
                                                    yesNoToBool(e.target.value),
                                            })
                                        }
                                        options={ynOptions}
                                    />
                                    <FormInput
                                        label={
                                            (t.fields as Record<string, string>)
                                                .proposed_location ??
                                            'Proposed hatch location'
                                        }
                                        value={asStr(
                                            data.loft_hatch.proposed_location,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('loft_hatch', {
                                                ...data.loft_hatch,
                                                proposed_location:
                                                    e.target.value,
                                            })
                                        }
                                        error={
                                            formErrors[
                                                'loft_hatch.proposed_location'
                                            ]
                                        }
                                        required={
                                            data.loft_hatch
                                                .new_hatch_required === true
                                        }
                                    />
                                    <FormInput
                                        label={
                                            (t.fields as Record<string, string>)
                                                .estimated_cost_eur ??
                                            'Estimated additional cost (EUR)'
                                        }
                                        value={asStr(
                                            data.loft_hatch.estimated_cost_eur,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            setData('loft_hatch', {
                                                ...data.loft_hatch,
                                                estimated_cost_eur:
                                                    e.target.value,
                                            })
                                        }
                                    />
                                </div>
                            </section>
                        )}
                    </div>
                )}

                {step === 'measurements' && (
                    <div className="space-y-4">
                        <section className="rml-card space-y-3 p-4 sm:p-5">
                            <h2 className="text-base font-semibold text-rml-text">
                                {t.sections.general_measurements}
                            </h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <FormInput
                                    label={t.fields.surveyed_floor_area}
                                    type="number"
                                    step="0.01"
                                    value={data.surveyed_floor_area_m2}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'surveyed_floor_area_m2',
                                            e.target.value,
                                        )
                                    }
                                    error={formErrors.surveyed_floor_area_m2}
                                />
                                <FormInput
                                    name="surveyed_installation_area_m2"
                                    label={t.fields.surveyed_installation_area}
                                    type="number"
                                    step="0.01"
                                    value={data.surveyed_installation_area_m2}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'surveyed_installation_area_m2',
                                            e.target.value,
                                        )
                                    }
                                    error={
                                        formErrors.surveyed_installation_area_m2
                                    }
                                    required={req(
                                        'surveyed_installation_area_m2',
                                    )}
                                />
                                <Select
                                    name="measurement_method"
                                    label={t.fields.measurement_method}
                                    value={data.measurement_method}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'measurement_method',
                                            e.target.value,
                                        )
                                    }
                                    options={fieldSelect('measurement_methods')}
                                    error={formErrors.measurement_method}
                                    required={req('measurement_method')}
                                />
                                <Select
                                    name="measurement_confidence"
                                    label={t.fields.measurement_confidence}
                                    value={data.measurement_confidence}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'measurement_confidence',
                                            e.target.value,
                                        )
                                    }
                                    options={fieldSelect(
                                        'measurement_confidence',
                                    )}
                                    error={formErrors.measurement_confidence}
                                    required={req('measurement_confidence')}
                                />
                                <FormInput
                                    name="measurement_date"
                                    label={t.fields.measurement_date}
                                    type="date"
                                    value={data.measurement_date}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        setData(
                                            'measurement_date',
                                            e.target.value,
                                        )
                                    }
                                    error={formErrors.measurement_date}
                                    required={req('measurement_date')}
                                />
                            </div>
                            <Textarea
                                label={t.fields.measurement_notes}
                                value={data.measurement_notes}
                                disabled={!can_edit}
                                onChange={(e) =>
                                    setData('measurement_notes', e.target.value)
                                }
                            />
                        </section>

                        <section className="rml-card space-y-3 p-4 sm:p-5">
                            <h2 className="text-base font-semibold text-rml-text">
                                {t.comparison_title}
                            </h2>
                            <p className="text-sm text-rml-muted">
                                {t.comparison_hint}
                            </p>
                            <ReadOnlyGrid rows={comparisonRows} />
                            {survey.comparison.flags.length > 0 && (
                                <Alert variant="warning">
                                    {survey.comparison.flags.join(', ')}
                                </Alert>
                            )}
                        </section>

                        <section className="rml-card space-y-4 p-4 sm:p-5">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="text-base font-semibold text-rml-text">
                                    {t.sections.measurement_sections}
                                    <span className="ml-0.5 text-rml-red">*</span>
                                </h2>
                                {can_edit && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={addSection}
                                    >
                                        {t.add_section}
                                    </Button>
                                )}
                            </div>
                            <p className="text-sm text-rml-muted">
                                Add at least one section. Enter length and width
                                so area calculates automatically, or enter a
                                manual area and explain why.
                            </p>
                            {formErrors.measurement_sections && (
                                <Alert variant="error">
                                    {formErrors.measurement_sections}
                                </Alert>
                            )}
                            {data.measurement_sections.length === 0 && (
                                <p className="text-sm text-rml-muted">
                                    {(t.errors as Record<string, string> | undefined)
                                        ?.measurement_section_required ??
                                        'Add at least one measured section before continuing.'}
                                </p>
                            )}
                            {data.measurement_sections.map((section, index) => {
                                const sectionHasError = Object.keys(
                                    formErrors,
                                ).some((key) =>
                                    key.startsWith(
                                        `measurement_sections.${index}.`,
                                    ),
                                );
                                const needsArea =
                                    !section.area_not_accessed &&
                                    !(
                                        parseFloat(section.manual_area_m2) > 0 ||
                                        parseFloat(section.calculated_area_m2) >
                                            0
                                    );

                                return (
                                <div
                                    key={section.id ?? `new-${index}`}
                                    data-measurement-section={index}
                                    className={`space-y-3 rounded-xl border p-3 ${
                                        sectionHasError
                                            ? 'border-rml-red ring-2 ring-rml-red/20'
                                            : 'border-rml-border'
                                    }`}
                                >
                                    {sectionHasError && (
                                        <Alert variant="error">
                                            Complete the highlighted fields in
                                            this measured section before
                                            continuing.
                                        </Alert>
                                    )}
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <FormInput
                                            name={`measurement_sections.${index}.name`}
                                            label={t.fields.section_name}
                                            value={section.name}
                                            disabled={!can_edit}
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <Select
                                            name={`measurement_sections.${index}.section_type`}
                                            label={t.fields.section_type}
                                            value={section.section_type}
                                            disabled={!can_edit}
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    'section_type',
                                                    e.target.value,
                                                )
                                            }
                                            options={fieldSelect(
                                                'section_types',
                                            )}
                                            error={
                                                formErrors[
                                                    `measurement_sections.${index}.section_type`
                                                ]
                                            }
                                            required
                                        />
                                        <FormInput
                                            name={`measurement_sections.${index}.length_m`}
                                            label={t.fields.length_m}
                                            type="number"
                                            step="0.01"
                                            value={section.length_m}
                                            disabled={!can_edit}
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    'length_m',
                                                    e.target.value,
                                                )
                                            }
                                            error={
                                                formErrors[
                                                    `measurement_sections.${index}.length_m`
                                                ]
                                            }
                                            required={needsArea}
                                            hint="Used with width to calculate area"
                                        />
                                        <FormInput
                                            name={`measurement_sections.${index}.width_m`}
                                            label={t.fields.width_m}
                                            type="number"
                                            step="0.01"
                                            value={section.width_m}
                                            disabled={!can_edit}
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    'width_m',
                                                    e.target.value,
                                                )
                                            }
                                            required={needsArea}
                                        />
                                        <FormInput
                                            label={t.fields.height_m}
                                            type="number"
                                            step="0.01"
                                            value={section.height_m}
                                            disabled={!can_edit}
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    'height_m',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <FormInput
                                            name={`measurement_sections.${index}.calculated_area_m2`}
                                            label={t.fields.calculated_area}
                                            value={section.calculated_area_m2}
                                            disabled
                                            error={
                                                formErrors[
                                                    `measurement_sections.${index}.calculated_area_m2`
                                                ]
                                            }
                                            hint="Auto-calculated from length × width"
                                        />
                                        <FormInput
                                            name={`measurement_sections.${index}.manual_area_m2`}
                                            label={t.fields.manual_area}
                                            type="number"
                                            step="0.01"
                                            value={section.manual_area_m2}
                                            disabled={!can_edit}
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    'manual_area_m2',
                                                    e.target.value,
                                                )
                                            }
                                            hint="Optional override — reason required if used"
                                        />
                                        <Select
                                            label={t.fields.measurement_method}
                                            value={section.measurement_method}
                                            disabled={!can_edit}
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    'measurement_method',
                                                    e.target.value,
                                                )
                                            }
                                            options={fieldSelect(
                                                'measurement_methods',
                                            )}
                                        />
                                        <Select
                                            label={
                                                t.fields.measurement_confidence
                                            }
                                            value={section.confidence}
                                            disabled={!can_edit}
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    'confidence',
                                                    e.target.value,
                                                )
                                            }
                                            options={fieldSelect(
                                                'measurement_confidence',
                                            )}
                                        />
                                    </div>
                                    <Textarea
                                        name={`measurement_sections.${index}.notes`}
                                        label={
                                            section.manual_area_m2.trim() !== ''
                                                ? ((t.fields as Record<string, string>)
                                                      .manual_area_reason ??
                                                  'Reason for manual area override')
                                                : ((t.fields as Record<string, string>)
                                                      .section_notes ??
                                                  t.fields.measurement_notes)
                                        }
                                        value={section.notes}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateSection(
                                                index,
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                        error={
                                            formErrors[
                                                `measurement_sections.${index}.notes`
                                            ]
                                        }
                                        required={
                                            section.manual_area_m2.trim() !== ''
                                        }
                                    />
                                    <div className="flex flex-wrap gap-4">
                                        <ToggleField
                                            label={t.fields.is_estimate}
                                            checked={section.is_estimate}
                                            disabled={!can_edit}
                                            required={section.area_not_accessed}
                                            onChange={(v) =>
                                                updateSection(
                                                    index,
                                                    'is_estimate',
                                                    v,
                                                )
                                            }
                                        />
                                        {formErrors[
                                            `measurement_sections.${index}.is_estimate`
                                        ] && (
                                            <p className="w-full text-sm text-rml-red">
                                                {
                                                    formErrors[
                                                        `measurement_sections.${index}.is_estimate`
                                                    ]
                                                }
                                            </p>
                                        )}
                                        <ToggleField
                                            label={t.fields.area_not_accessed}
                                            checked={section.area_not_accessed}
                                            disabled={!can_edit}
                                            onChange={(v) =>
                                                updateSection(
                                                    index,
                                                    'area_not_accessed',
                                                    v,
                                                )
                                            }
                                        />
                                    </div>
                                    {can_edit && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                setData(
                                                    'measurement_sections',
                                                    data.measurement_sections.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            {t.remove_section}
                                        </Button>
                                    )}
                                </div>
                                );
                            })}
                        </section>
                    </div>
                )}

                {step === 'scheme' && (
                    <section className="rml-card space-y-4 p-4 sm:p-5">
                        {schemeSlug === 'insulation' && (
                            <>
                                <h2 className="text-base font-semibold text-rml-text">
                                    {t.sections.insulation}
                                </h2>
                                <Select
                                    label={
                                        (t.fields as Record<string, string>)
                                            .proposed_insulation_type ??
                                        'Proposed insulation type'
                                    }
                                    value={asStr(
                                        data.scheme_inspection
                                            .proposed_insulation_type,
                                    )}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        updateScheme(
                                            'proposed_insulation_type',
                                            e.target.value,
                                        )
                                    }
                                    error={
                                        formErrors[
                                            'scheme_inspection.proposed_insulation_type'
                                        ]
                                    }
                                    options={fieldSelect('insulation_types')}
                                    required={req(
                                        'scheme_inspection.proposed_insulation_type',
                                    )}
                                />
                                <Select
                                    label={
                                        (t.fields as Record<string, string>)
                                            .existing_insulation_present ??
                                        'Existing insulation present'
                                    }
                                    value={asStr(
                                        data.scheme_inspection
                                            .existing_insulation_present,
                                    )}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        updateScheme(
                                            'existing_insulation_present',
                                            e.target.value,
                                        )
                                    }
                                    error={
                                        formErrors[
                                            'scheme_inspection.existing_insulation_present'
                                        ]
                                    }
                                    options={ynOptions}
                                    required={req(
                                        'scheme_inspection.existing_insulation_present',
                                    )}
                                />
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .existing_material ??
                                            'Existing insulation material'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .existing_material,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'existing_material',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect(
                                            'insulation_materials',
                                        )}
                                    />
                                    <FormInput
                                        label={
                                            (t.fields as Record<string, string>)
                                                .existing_depth ??
                                            'Existing depth'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .existing_depth,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'existing_depth',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FormInput
                                        label={
                                            (t.fields as Record<string, string>)
                                                .proposed_depth ??
                                            'Proposed depth'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .proposed_depth,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'proposed_depth',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FormInput
                                        label={
                                            (t.fields as Record<string, string>)
                                                .installation_area_m2 ??
                                            'Installation area m²'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .installation_area_m2,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'installation_area_m2',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .condition_rating ??
                                            'Condition rating'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .condition_rating,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'condition_rating',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect(
                                            'condition_ratings',
                                        )}
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .installation_suitability ??
                                            'Installation suitability'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .suitable_for_installation,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'suitable_for_installation',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect('suitability')}
                                        error={
                                            formErrors[
                                                'scheme_inspection.suitable_for_installation'
                                            ]
                                        }
                                        required={req(
                                            'scheme_inspection.suitable_for_installation',
                                        )}
                                    />
                                </div>
                                <div className="flex flex-wrap gap-4">
                                    {[
                                        'water_damage',
                                        'damp',
                                        'mould',
                                        'asbestos_concern',
                                    ].map((key) => (
                                        <ToggleField
                                            key={key}
                                            label={key.replaceAll('_', ' ')}
                                            checked={asBool(
                                                data.scheme_inspection[key],
                                            )}
                                            disabled={!can_edit}
                                            onChange={(v) =>
                                                updateScheme(key, v)
                                            }
                                        />
                                    ))}
                                </div>
                            </>
                        )}

                        {schemeSlug === 'double-glazing' && (
                            <>
                                <h2 className="text-base font-semibold text-rml-text">
                                    {t.sections.glazing}
                                </h2>
                                <Select
                                    label={
                                        (t.fields as Record<string, string>)
                                            .current_glazing_type ??
                                        'Current glazing type'
                                    }
                                    value={asStr(
                                        data.scheme_inspection
                                            .current_glazing_type,
                                    )}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        updateScheme(
                                            'current_glazing_type',
                                            e.target.value,
                                        )
                                    }
                                    error={
                                        formErrors[
                                            'scheme_inspection.current_glazing_type'
                                        ]
                                    }
                                    options={fieldSelect('glazing_types')}
                                    required={req(
                                        'scheme_inspection.current_glazing_type',
                                    )}
                                />
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <FormInput
                                        label={
                                            (t.fields as Record<string, string>)
                                                .number_of_windows ??
                                            'Number of windows'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .number_of_windows,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'number_of_windows',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FormInput
                                        label={
                                            (t.fields as Record<string, string>)
                                                .total_glazing_area_m2 ??
                                            'Total glazing area m²'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .total_glazing_area_m2,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'total_glazing_area_m2',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .frame_material ??
                                            'Frame material'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .frame_material,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'frame_material',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect('frame_materials')}
                                        error={
                                            formErrors[
                                                'scheme_inspection.frame_material'
                                            ]
                                        }
                                        required={req(
                                            'scheme_inspection.frame_material',
                                        )}
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .opening_type ?? 'Opening type'
                                        }
                                        value={asStr(
                                            data.scheme_inspection.opening_type,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'opening_type',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect('opening_types')}
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .window_location ??
                                            'Window location'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .window_location,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'window_location',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect(
                                            'window_locations',
                                        )}
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .replacement_suitability ??
                                            'Replacement suitability'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .replacement_suitability,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'replacement_suitability',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect('suitability')}
                                        error={
                                            formErrors[
                                                'scheme_inspection.replacement_suitability'
                                            ]
                                        }
                                        required={req(
                                            'scheme_inspection.replacement_suitability',
                                        )}
                                    />
                                </div>
                                <div className="flex flex-wrap gap-4">
                                    {[
                                        'scaffolding_requirement',
                                        'heritage_restrictions',
                                    ].map((key) => (
                                        <ToggleField
                                            key={key}
                                            label={key.replaceAll('_', ' ')}
                                            checked={asBool(
                                                data.scheme_inspection[key],
                                            )}
                                            disabled={!can_edit}
                                            onChange={(v) =>
                                                updateScheme(key, v)
                                            }
                                        />
                                    ))}
                                </div>
                            </>
                        )}

                        {schemeSlug === 'heat-pumps' && (
                            <>
                                <h2 className="text-base font-semibold text-rml-text">
                                    {t.sections.heat_pump}
                                </h2>
                                <Select
                                    label={
                                        (t.fields as Record<string, string>)
                                            .current_heating_system ??
                                        'Current heating system'
                                    }
                                    value={asStr(
                                        data.scheme_inspection
                                            .current_heating_system,
                                    )}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        updateScheme(
                                            'current_heating_system',
                                            e.target.value,
                                        )
                                    }
                                    error={
                                        formErrors[
                                            'scheme_inspection.current_heating_system'
                                        ]
                                    }
                                    options={fieldSelect('heating_systems')}
                                    required={req(
                                        'scheme_inspection.current_heating_system',
                                    )}
                                />
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <FormInput
                                        label={
                                            (t.fields as Record<string, string>)
                                                .estimated_kw ??
                                            'Estimated required kW'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .estimated_kw,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'estimated_kw',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FormInput
                                        label={
                                            (t.fields as Record<string, string>)
                                                .outdoor_unit_location ??
                                            'Proposed outdoor-unit location'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .outdoor_unit_location,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'outdoor_unit_location',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .proposed_heat_pump_type ??
                                            'Proposed heat-pump type'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .proposed_heat_pump_type,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'proposed_heat_pump_type',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect('heat_pump_types')}
                                        error={
                                            formErrors[
                                                'scheme_inspection.proposed_heat_pump_type'
                                            ]
                                        }
                                        required={req(
                                            'scheme_inspection.proposed_heat_pump_type',
                                        )}
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .radiator_suitability ??
                                            'Radiator suitability'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .radiator_suitability,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'radiator_suitability',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect(
                                            'radiator_suitability',
                                        )}
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .electrical_supply ??
                                            'Electrical supply'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .electrical_supply,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'electrical_supply',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect(
                                            'electrical_supply',
                                        )}
                                    />
                                    <Select
                                        label={
                                            (t.fields as Record<string, string>)
                                                .installation_suitability ??
                                            'Installation suitability'
                                        }
                                        value={asStr(
                                            data.scheme_inspection
                                                .suitable_for_installation,
                                        )}
                                        disabled={!can_edit}
                                        onChange={(e) =>
                                            updateScheme(
                                                'suitable_for_installation',
                                                e.target.value,
                                            )
                                        }
                                        options={fieldSelect('suitability')}
                                        error={
                                            formErrors[
                                                'scheme_inspection.suitable_for_installation'
                                            ]
                                        }
                                        required={req(
                                            'scheme_inspection.suitable_for_installation',
                                        )}
                                    />
                                </div>
                                <div className="flex flex-wrap gap-4">
                                    {[
                                        'underfloor_heating',
                                        'electrical_upgrade_required',
                                    ].map((key) => (
                                        <ToggleField
                                            key={key}
                                            label={key.replaceAll('_', ' ')}
                                            checked={asBool(
                                                data.scheme_inspection[key],
                                            )}
                                            disabled={!can_edit}
                                            onChange={(v) =>
                                                updateScheme(key, v)
                                            }
                                        />
                                    ))}
                                </div>
                            </>
                        )}

                        {!['insulation', 'double-glazing', 'heat-pumps'].includes(
                            schemeSlug,
                        ) && (
                            <div className="space-y-3">
                                <Alert variant="info">
                                    Scheme-specific fields will appear when the
                                    lead scheme is insulation, double-glazing or
                                    heat-pumps. Confirm suitability to complete
                                    this step.
                                </Alert>
                                <Select
                                    label={
                                        t.fields.installation_suitability ??
                                        'Installation suitability'
                                    }
                                    required={req(
                                        'scheme_inspection.suitable_for_installation',
                                    )}
                                    value={asStr(
                                        data.scheme_inspection
                                            .suitable_for_installation,
                                    )}
                                    disabled={!can_edit}
                                    onChange={(e) =>
                                        updateScheme(
                                            'suitable_for_installation',
                                            e.target.value,
                                        )
                                    }
                                    error={
                                        formErrors[
                                            'scheme_inspection.suitable_for_installation'
                                        ]
                                    }
                                    options={fieldSelect('suitability')}
                                />
                            </div>
                        )}
                    </section>
                )}

                {step === 'evidence' && (
                    <div className="space-y-4">
                        <section className="rml-card space-y-3 p-4 sm:p-5">
                            <h2 className="text-base font-semibold text-rml-text">
                                {t.steps.evidence}
                            </h2>
                            <Alert variant="info" title={t.ai_guidance_title}>
                                <ul className="list-disc space-y-1 pl-5 text-sm">
                                    {Object.values(t.ai_guidance).map(
                                        (line) => (
                                            <li key={line}>{line}</li>
                                        ),
                                    )}
                                </ul>
                            </Alert>
                            {can_edit && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Select
                                        label={t.fields.evidence_category}
                                        value={evidenceCategory}
                                        onChange={(e) =>
                                            setEvidenceCategory(e.target.value)
                                        }
                                        options={toSelectOptions(
                                            options.evidence_categories,
                                            optionLabels,
                                            false,
                                        )}
                                    />
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-rml-text">
                                            {t.capture_photo}
                                        </label>
                                        <input
                                            type="file"
                                            accept="image/*,application/pdf"
                                            capture="environment"
                                            onChange={(e) =>
                                                setEvidenceFile(
                                                    e.target.files?.[0] ?? null,
                                                )
                                            }
                                            className="block w-full text-sm"
                                        />
                                    </div>
                                    <ToggleField
                                        label={t.fields.for_ai_measurement}
                                        checked={forAi}
                                        onChange={setForAi}
                                    />
                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            onClick={uploadEvidence}
                                            disabled={!evidenceFile || uploading}
                                        >
                                            {uploading
                                                ? '…'
                                                : t.upload_evidence}
                                        </Button>
                                    </div>
                                </div>
                            )}
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {survey.evidence.map((item) => (
                                    <div
                                        key={item.id}
                                        className="rounded-xl border border-rml-border p-3 text-sm"
                                    >
                                        <p className="font-medium text-rml-text">
                                            {item.category}
                                        </p>
                                        <p className="text-rml-muted">
                                            {item.original_name}
                                        </p>
                                        {item.url && item.url.match(
                                            /\.(jpg|jpeg|png|webp)/i,
                                        ) && (
                                            <img
                                                src={item.url}
                                                alt={item.caption ?? ''}
                                                className="mt-2 max-h-40 w-full rounded object-cover"
                                            />
                                        )}
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                )}

                {step === 'homeowner' && (
                    <section className="rml-card space-y-4 p-4 sm:p-5">
                        <h2 className="text-base font-semibold text-rml-text">
                            {t.steps.homeowner}
                        </h2>
                        {formErrors.homeowner_confirmation && (
                            <Alert variant="error">
                                {formErrors.homeowner_confirmation}
                            </Alert>
                        )}
                        <div className="grid gap-3 sm:grid-cols-2">
                            <FormInput
                                label={t.fields.homeowner_name}
                                value={data.homeowner_confirmation.name}
                                disabled={!can_edit}
                                onChange={(e) =>
                                    updateHomeowner('name', e.target.value)
                                }
                                error={
                                    formErrors['homeowner_confirmation.name']
                                }
                                required={req('homeowner_confirmation.name')}
                            />
                            <Select
                                label={t.fields.relationship}
                                value={data.homeowner_confirmation.relationship}
                                disabled={!can_edit}
                                onChange={(e) =>
                                    updateHomeowner(
                                        'relationship',
                                        e.target.value,
                                    )
                                }
                                options={fieldSelect(
                                    'homeowner_relationships',
                                )}
                                error={
                                    formErrors[
                                        'homeowner_confirmation.relationship'
                                    ]
                                }
                                required={req(
                                    'homeowner_confirmation.relationship',
                                )}
                            />
                            <FormInput
                                label={t.fields.confirmation_date}
                                type="date"
                                value={
                                    data.homeowner_confirmation.confirmation_date
                                }
                                disabled={!can_edit}
                                onChange={(e) =>
                                    updateHomeowner(
                                        'confirmation_date',
                                        e.target.value,
                                    )
                                }
                                error={
                                    formErrors[
                                        'homeowner_confirmation.confirmation_date'
                                    ]
                                }
                                required={req(
                                    'homeowner_confirmation.confirmation_date',
                                )}
                            />
                        </div>
                        <div className="flex flex-wrap gap-4">
                            <ToggleField
                                label={t.fields.permission_to_inspect}
                                checked={
                                    data.homeowner_confirmation
                                        .permission_to_inspect
                                }
                                disabled={!can_edit}
                                required={req(
                                    'homeowner_confirmation.permission_to_inspect',
                                )}
                                onChange={(v) =>
                                    updateHomeowner('permission_to_inspect', v)
                                }
                            />
                            <ToggleField
                                label={t.fields.permission_evidence}
                                checked={
                                    data.homeowner_confirmation
                                        .permission_evidence
                                }
                                disabled={!can_edit}
                                required={req(
                                    'homeowner_confirmation.permission_evidence',
                                )}
                                onChange={(v) =>
                                    updateHomeowner('permission_evidence', v)
                                }
                            />
                            <ToggleField
                                label={
                                    (t.fields as Record<string, string>)
                                        .permission_access ??
                                    'Permission to access relevant areas'
                                }
                                checked={
                                    data.homeowner_confirmation.permission_access
                                }
                                disabled={!can_edit}
                                onChange={(v) =>
                                    updateHomeowner('permission_access', v)
                                }
                            />
                            <ToggleField
                                label={
                                    (t.fields as Record<string, string>)
                                        .return_visit_permission ??
                                    'Permission for return visit'
                                }
                                checked={
                                    data.homeowner_confirmation
                                        .return_visit_permission
                                }
                                disabled={!can_edit}
                                onChange={(v) =>
                                    updateHomeowner(
                                        'return_visit_permission',
                                        v,
                                    )
                                }
                            />
                        </div>
                    </section>
                )}

                {step === 'risks' && (
                    <section className="rml-card space-y-4 p-4 sm:p-5">
                        <h2 className="text-base font-semibold text-rml-text">
                            {t.steps.risks}
                        </h2>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {options.risk_options.map((code) => (
                                <ToggleField
                                    key={code}
                                    label={code.replaceAll('_', ' ')}
                                    checked={data.risks.codes.includes(code)}
                                    disabled={!can_edit}
                                    onChange={() => toggleRisk(code)}
                                />
                            ))}
                        </div>
                        <Select
                            label={
                                (t.fields as Record<string, string>)
                                    .risk_severity ?? 'Risk severity'
                            }
                            value={data.risks.severity}
                            disabled={!can_edit}
                            onChange={(e) =>
                                setData('risks', {
                                    ...data.risks,
                                    severity: e.target.value,
                                })
                            }
                            options={fieldSelect('risk_severity')}
                        />
                        <Select
                            label={
                                (t.fields as Record<string, string>)
                                    .affected_section ??
                                'Affected survey section'
                            }
                            value={data.risks.affected_section}
                            disabled={!can_edit}
                            onChange={(e) =>
                                setData('risks', {
                                    ...data.risks,
                                    affected_section: e.target.value,
                                })
                            }
                            options={fieldSelect('affected_section')}
                        />
                        <Select
                            label={t.fields.surveyor_recommendation}
                            value={data.surveyor_recommendation}
                            disabled={!can_edit}
                            onChange={(e) =>
                                setData(
                                    'surveyor_recommendation',
                                    e.target.value,
                                )
                            }
                            options={fieldSelect('recommendation_outcomes')}
                            error={formErrors.surveyor_recommendation}
                            required={req('surveyor_recommendation')}
                        />
                        <Textarea
                            label={t.fields.seller_visible_notes}
                            value={data.seller_visible_notes}
                            disabled={!can_edit}
                            onChange={(e) =>
                                setData('seller_visible_notes', e.target.value)
                            }
                        />
                        <Textarea
                            label={t.fields.buyer_visible_notes}
                            value={data.buyer_visible_notes}
                            disabled={!can_edit}
                            onChange={(e) =>
                                setData('buyer_visible_notes', e.target.value)
                            }
                        />
                        {can_review && (
                            <Textarea
                                label={t.fields.internal_audit_notes}
                                value={data.internal_audit_notes}
                                onChange={(e) =>
                                    setData(
                                        'internal_audit_notes',
                                        e.target.value,
                                    )
                                }
                            />
                        )}
                    </section>
                )}

                {step === 'review' && (
                    <div className="space-y-4">
                        <section className="rml-card space-y-3 p-4 sm:p-5">
                            <h2 className="text-base font-semibold text-rml-text">
                                {t.steps.review}
                            </h2>
                            <ReadOnlyGrid rows={comparisonRows} />
                            <ReadOnlyGrid
                                rows={[
                                    {
                                        label: t.fields.confirmed_address,
                                        value: data.confirmed_address,
                                    },
                                    {
                                        label: t.fields.surveyed_installation_area,
                                        value: data.surveyed_installation_area_m2,
                                    },
                                    {
                                        label: 'Evidence count',
                                        value: String(survey.evidence.length),
                                    },
                                    {
                                        label: t.fields.surveyor_recommendation,
                                        value: data.surveyor_recommendation,
                                    },
                                ]}
                            />
                            {Object.keys(formErrors).length > 0 && (
                                <Alert variant="error">
                                    {Object.values(formErrors)
                                        .filter(Boolean)
                                        .join(' · ')}
                                </Alert>
                            )}
                        </section>

                        {can_review &&
                            ['submitted', 'under_review', 'resubmitted'].includes(
                                survey.status,
                            ) && (
                                <section className="rml-card space-y-3 p-4 sm:p-5">
                                    <h2 className="text-base font-semibold text-rml-text">
                                        {t.sections.auditor_review}
                                    </h2>
                                    <FormInput
                                        label={t.fields.auditor_approved_area}
                                        type="number"
                                        step="0.01"
                                        value={approvedArea}
                                        onChange={(e) =>
                                            setApprovedArea(e.target.value)
                                        }
                                    />
                                    <FormInput
                                        label="Auditor-approved installation area (m²)"
                                        type="number"
                                        step="0.01"
                                        value={approvedInstallArea}
                                        onChange={(e) =>
                                            setApprovedInstallArea(
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <Textarea
                                        label="Decision notes"
                                        value={decisionNotes}
                                        onChange={(e) =>
                                            setDecisionNotes(e.target.value)
                                        }
                                    />
                                    <Textarea
                                        label={t.fields.correction_request}
                                        value={correctionText}
                                        onChange={(e) =>
                                            setCorrectionText(e.target.value)
                                        }
                                    />
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                router.post(routes.approve, {
                                                    auditor_approved_area_m2:
                                                        approvedArea || null,
                                                    auditor_approved_installation_area_m2:
                                                        approvedInstallArea ||
                                                        null,
                                                    auditor_decision_notes:
                                                        decisionNotes || null,
                                                })
                                            }
                                        >
                                            {t.approve}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(routes.correction, {
                                                    correction_request:
                                                        correctionText,
                                                    correction_sections: [
                                                        'measurements',
                                                        'evidence',
                                                    ],
                                                })
                                            }
                                        >
                                            {t.request_correction}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="danger"
                                            onClick={() =>
                                                router.post(routes.reject, {
                                                    auditor_decision_notes:
                                                        decisionNotes,
                                                })
                                            }
                                        >
                                            {t.reject}
                                        </Button>
                                    </div>
                                    {survey.versions.length > 0 && (
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-semibold">
                                                Version history
                                            </h3>
                                            <ul className="space-y-1 text-sm text-rml-muted">
                                                {survey.versions.map((version) => (
                                                    <li key={version.id}>
                                                        v{version.version} ·{' '}
                                                        {version.event} ·{' '}
                                                        {version.status}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </section>
                            )}
                    </div>
                )}

                <div className="sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-rml-border bg-white/95 p-3 shadow-sm backdrop-blur">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={goBack}
                        disabled={stepIndex <= 0}
                    >
                        {t.back}
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        {can_edit && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={saveDraft}
                                disabled={processing}
                            >
                                {t.save_draft}
                            </Button>
                        )}
                        {step !== 'review' ? (
                            <Button
                                type="button"
                                onClick={goNext}
                                disabled={processing}
                            >
                                {t.continue}
                            </Button>
                        ) : (
                            can_edit && (
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        uploading ||
                                        !allPriorComplete
                                    }
                                >
                                    {t.submit}
                                </Button>
                            )
                        )}
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
