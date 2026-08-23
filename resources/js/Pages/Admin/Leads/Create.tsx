import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Alert,
    BackLink,
    Button,
    Checkbox,
    CountrySelect,
    FileUpload,
    FormInput,
    Select,
    Stepper,
    Textarea,
} from '@/Components/ui';
import type { UploadedFileMeta } from '@/Components/ui/FileUpload';
import { useScrollToFirstError } from '@/hooks/use-scroll-to-first-error';
import { DEFAULT_COUNTRY } from '@/lib/countries';
import {
    isValidPhone,
    phoneErrorMessage,
    phoneMessagesFromTranslations,
    sanitizePhoneInput,
} from '@/lib/phone';
import type { PageProps } from '@/types';

function isValidEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

interface SchemeField {
    id: number;
    key: string;
    label: string;
    type: string | null;
    options: string[] | Record<string, string> | null;
    required: boolean;
}

interface SchemeZone {
    id: number;
    code: string;
    name: string;
}

interface SchemeOption {
    id: number;
    name: string;
    slug: string;
    fields: SchemeField[];
    zones: SchemeZone[];
}

interface SellerCompany {
    id: number;
    name: string;
}

interface SellerAgent {
    id: number;
    name: string;
    email: string;
    company_id: number | null;
}

type MetricValue = string | number | boolean | null;
type StepId =
    | 'source'
    | 'customer'
    | 'property'
    | 'scheme'
    | 'evidence'
    | 'review';

interface LeadFormData {
    as_draft: boolean;
    lead_source: string;
    seller_company_id: string;
    seller_user_id: string;
    buying_price: string;
    scheme_id: string;
    zone_id: string;
    zone_code: string;
    customer_first_name: string;
    customer_last_name: string;
    customer_phone: string;
    customer_email: string;
    notes: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    postcode: string;
    country: string;
    cadastral_reference: string;
    property_type: string;
    occupancy_type: string;
    size_m2: string;
    epc_rating: string;
    metrics: Record<string, MetricValue>;
    evidence_photos: File[];
    evidence_video: File | null;
    evidence_agreement: File | null;
    evidence_eligibility: File[];
}

const STEPS: StepId[] = [
    'source',
    'customer',
    'property',
    'scheme',
    'evidence',
    'review',
];

function fileMetas(files: File[]): UploadedFileMeta[] {
    return files.map((file, index) => ({
        id: `${file.name}-${file.size}-${index}`,
        name: file.name,
        sizeLabel: `${Math.round(file.size / 1024)} KB`,
    }));
}

function selectOptionsFromField(
    field: SchemeField,
): Array<{ label: string; value: string }> {
    if (!field.options) {
        return [];
    }

    if (Array.isArray(field.options)) {
        return field.options.map((option) => ({
            label: option,
            value: option,
        }));
    }

    return Object.entries(field.options).map(([value, label]) => ({
        value,
        label,
    }));
}

function isZoneField(field: SchemeField): boolean {
    return (
        field.key === 'zone' ||
        field.key === 'zone_context' ||
        field.type === 'zone'
    );
}

function hasText(value: string | null | undefined): boolean {
    return Boolean(value && value.trim() !== '');
}

function metricFilled(value: MetricValue): boolean {
    return value !== null && value !== undefined && value !== '' && value !== false;
}

function sizeFromMetric(value: MetricValue): string {
    if (value === null || value === undefined || value === '' || value === false) {
        return '';
    }
    return String(value);
}

export default function AdminLeadCreate({
    schemes,
    seller_companies,
    seller_agents,
    sources,
}: {
    schemes: SchemeOption[];
    seller_companies: SellerCompany[];
    seller_agents: SellerAgent[];
    sources: string[];
}) {
    const { translations } = usePage<PageProps>().props;
    const createT = ((translations.admin as any)?.leads?.create ??
        {}) as Record<string, string>;
    const common = (translations.admin?.common ?? {}) as Record<string, string>;
    const v = translations.validation;
    // Admin portal may not share seller.* keys — never crash on missing nest.
    const sellerT = ((translations as any).seller?.leads ??
        {}) as Record<string, string>;
    const sellerCommon = ((translations as any).seller?.common ??
        {}) as Record<string, string>;

    const requiredMsg = (field: string) =>
        (
            v?.field_required ??
            sellerT.field_required ??
            ':field is required.'
        ).replace(':field', field);

    const phoneMessages = phoneMessagesFromTranslations(
        v,
        requiredMsg,
        createT.customer_phone ?? createT.phone ?? sellerT.phone ?? 'Phone',
    );

    const [step, setStep] = useState<StepId>('source');
    const [stepAlert, setStepAlert] = useState<string | null>(null);
    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );

    const { data, setData, post, processing, errors, transform } =
        useForm<LeadFormData>({
            as_draft: false,
            lead_source: sources[0] ?? 'rml_internal',
            seller_company_id: '',
            seller_user_id: '',
            buying_price: '',
            scheme_id: schemes[0] ? String(schemes[0].id) : '',
            zone_id: '',
            zone_code: '',
            customer_first_name: '',
            customer_last_name: '',
            customer_phone: '',
            customer_email: '',
            notes: '',
            address_line_1: '',
            address_line_2: '',
            city: '',
            postcode: '',
            country: DEFAULT_COUNTRY,
            cadastral_reference: '',
            property_type: '',
            occupancy_type: '',
            size_m2: '',
            epc_rating: '',
            metrics: {},
            evidence_photos: [],
            evidence_video: null,
            evidence_agreement: null,
            evidence_eligibility: [],
        });

    const selectedScheme = useMemo(
        () =>
            schemes.find((scheme) => String(scheme.id) === data.scheme_id) ??
            null,
        [schemes, data.scheme_id],
    );

    const agentOptions = useMemo(() => {
        if (data.seller_company_id) {
            return seller_agents.filter(
                (agent) => String(agent.company_id) === data.seller_company_id,
            );
        }
        return seller_agents;
    }, [seller_agents, data.seller_company_id]);

    const selectedCompany = useMemo(
        () =>
            seller_companies.find(
                (company) => String(company.id) === data.seller_company_id,
            ) ?? null,
        [seller_companies, data.seller_company_id],
    );

    const selectedAgent = useMemo(
        () =>
            seller_agents.find(
                (agent) => String(agent.id) === data.seller_user_id,
            ) ?? null,
        [seller_agents, data.seller_user_id],
    );

    const sourceLabel = (source: string) =>
        createT[`source_${source}`] ?? source.replaceAll('_', ' ');

    const label = {
        firstName:
            createT.customer_first_name ??
            createT.first_name ??
            sellerT.first_name ??
            'First name',
        lastName:
            createT.customer_last_name ??
            createT.last_name ??
            sellerT.last_name ??
            'Last name',
        phone:
            createT.customer_phone ??
            createT.phone ??
            sellerT.phone ??
            'Phone',
        email:
            createT.customer_email ??
            createT.email ??
            sellerT.email ??
            'Email',
        notes: createT.notes ?? sellerT.notes ?? 'Notes',
        address1:
            createT.address_line_1 ??
            createT.address_1 ??
            sellerT.address_1 ??
            'Address line 1',
        address2:
            createT.address_line_2 ??
            createT.address_2 ??
            sellerT.address_2 ??
            'Address line 2',
        city: createT.city ?? sellerT.city ?? 'City',
        postcode: createT.postcode ?? sellerT.postcode ?? 'Postcode',
        country: createT.country ?? sellerT.country ?? 'Country',
        cadastral:
            createT.cadastral_reference ??
            createT.cadastral ??
            translations.location?.cadastral_reference ??
            sellerT.input_cadastral_reference ??
            'Cadastral reference',
        propertyType:
            createT.property_type ?? sellerT.property_type ?? 'Property type',
        occupancyType: createT.occupancy_type ?? 'Occupancy type',
        sizeM2: createT.size_m2 ?? sellerT.size ?? 'Size (m²)',
        scheme: createT.scheme ?? common.scheme ?? sellerCommon.scheme ?? 'Scheme',
        zone: createT.zone ?? common.zone ?? sellerCommon.zone ?? 'Zone',
        leadSource: createT.source ?? createT.lead_source ?? 'Lead source',
        sellerCompany: createT.seller_company ?? 'Seller company',
        sellerAgent: createT.seller_agent ?? 'Seller agent',
        buyingPrice: createT.buying_price ?? 'Seller payout (optional)',
        evidencePhotos:
            createT.evidence_photos ?? sellerT.evidence_photos ?? 'Property photos',
        evidenceAgreement:
            createT.evidence_agreement ??
            sellerT.evidence_agreement ??
            'Signed homeowner agreement',
        evidenceEligibility:
            createT.evidence_eligibility_optional ??
            createT.evidence_eligibility ??
            sellerT.evidence_eligibility_optional ??
            sellerT.evidence_eligibility ??
            'Eligibility documents (optional)',
        evidenceVideo:
            createT.evidence_video_optional ??
            createT.evidence_video ??
            sellerT.evidence_video_optional ??
            sellerT.evidence_video ??
            'Video (optional)',
        evidenceHint:
            createT.evidence_hint ??
            sellerT.evidence_hint ??
            'Upload clear photos and the signed homeowner agreement.',
        saveDraft: createT.save_draft ?? sellerT.save_draft ?? 'Save draft',
        submit: createT.submit ?? sellerT.submit ?? 'Submit for review',
        next: createT.next ?? sellerT.next ?? common.next ?? 'Next',
        previous:
            createT.previous ?? sellerT.previous ?? common.previous ?? 'Previous',
        edit: createT.edit ?? common.edit ?? 'Edit',
        goToSection:
            createT.go_to_section ?? sellerT.go_to_section ?? 'Go to section',
        complete: createT.complete ?? sellerT.complete ?? common.complete ?? 'Complete',
        incomplete:
            createT.incomplete ??
            sellerT.incomplete ??
            common.incomplete ??
            'Incomplete',
        missingRequired:
            createT.missing_required ??
            sellerT.missing_required ??
            'Missing required items',
        submitIncomplete:
            createT.submit_incomplete ??
            sellerT.submit_incomplete ??
            'Complete all required sections before submitting this lead.',
        stepIncomplete:
            createT.step_incomplete ??
            sellerT.step_incomplete ??
            'Complete the required fields on this step before continuing.',
    };

    const occupancyOptions = [
        {
            value: 'owner_occupied',
            label: createT.occupancy_owner_occupied ?? 'Owner occupied',
        },
        {
            value: 'tenanted',
            label: createT.occupancy_tenanted ?? 'Tenanted',
        },
        {
            value: 'holiday',
            label: createT.occupancy_holiday ?? 'Holiday',
        },
        {
            value: 'vacant',
            label: createT.occupancy_vacant ?? 'Vacant',
        },
    ];

    const propertyTypeOptions = [
        {
            value: 'detached',
            label:
                createT.property_type_detached ??
                sellerT.property_type_detached ??
                'Detached',
        },
        {
            value: 'semi_detached',
            label:
                createT.property_type_semi_detached ??
                sellerT.property_type_semi_detached ??
                'Semi-detached',
        },
        {
            value: 'terrace',
            label:
                createT.property_type_terrace ??
                sellerT.property_type_terrace ??
                'Terrace',
        },
        {
            value: 'flat',
            label:
                createT.property_type_flat ??
                sellerT.property_type_flat ??
                'Flat',
        },
    ];

    const sourceComplete = useMemo(() => {
        if (!hasText(data.lead_source)) {
            return false;
        }
        if (data.lead_source === 'seller_company') {
            return hasText(data.seller_company_id);
        }
        if (data.lead_source === 'seller_agent') {
            return hasText(data.seller_user_id);
        }
        return true;
    }, [data.lead_source, data.seller_company_id, data.seller_user_id]);

    const customerComplete =
        hasText(data.customer_first_name) &&
        hasText(data.customer_last_name) &&
        isValidPhone(data.customer_phone) &&
        hasText(data.customer_email) &&
        isValidEmail(data.customer_email);

    const propertyComplete =
        hasText(data.address_line_1) &&
        hasText(data.city) &&
        hasText(data.postcode) &&
        hasText(data.country) &&
        hasText(data.property_type);

    const schemeFields = useMemo(
        () =>
            (selectedScheme?.fields ?? []).filter(
                (field) =>
                    field.key !== 'property_type' &&
                    field.key !== 'notes' &&
                    field.key !== 'occupancy_type',
            ),
        [selectedScheme],
    );

    const schemeComplete = useMemo(() => {
        if (!selectedScheme || !data.scheme_id) {
            return false;
        }

        return schemeFields
            .filter((field) => field.required)
            .every((field) => {
                if (isZoneField(field)) {
                    return Boolean(data.zone_id);
                }
                if (field.key === 'epc_rating') {
                    return metricFilled(
                        data.metrics.epc_rating ?? data.epc_rating ?? null,
                    );
                }
                return metricFilled(data.metrics[field.key] ?? null);
            });
    }, [
        selectedScheme,
        data.scheme_id,
        data.zone_id,
        data.metrics,
        data.epc_rating,
        schemeFields,
    ]);

    const hasPhoto = data.evidence_photos.length > 0;
    const hasAgreement = data.evidence_agreement != null;
    const evidenceComplete = hasPhoto && hasAgreement;

    const canSubmit =
        sourceComplete &&
        customerComplete &&
        propertyComplete &&
        schemeComplete &&
        evidenceComplete;

    const missingItems = useMemo(() => {
        const items: Array<{ step: StepId; label: string }> = [];
        if (!sourceComplete) {
            items.push({
                step: 'source',
                label: createT.step_source ?? label.leadSource,
            });
        }
        if (!customerComplete) {
            items.push({
                step: 'customer',
                label: createT.step_customer ?? createT.customer ?? sellerT.step_customer ?? 'Customer',
            });
        }
        if (!propertyComplete) {
            items.push({
                step: 'property',
                label: createT.step_property ?? createT.address ?? sellerT.step_property ?? 'Property',
            });
        }
        if (!schemeComplete) {
            items.push({
                step: 'scheme',
                label: createT.step_scheme ?? label.scheme,
            });
        }
        if (!evidenceComplete) {
            items.push({
                step: 'evidence',
                label: createT.step_evidence ?? sellerT.step_evidence ?? 'Evidence',
            });
        }
        return items;
    }, [
        sourceComplete,
        customerComplete,
        propertyComplete,
        schemeComplete,
        evidenceComplete,
        createT,
        label.leadSource,
        label.scheme,
        sellerT,
    ]);

    const stepLabels: Record<StepId, string> = {
        source: createT.step_source ?? label.leadSource,
        customer:
            createT.step_customer ??
            createT.customer ??
            sellerT.step_customer ??
            'Customer details',
        property:
            createT.step_property ??
            createT.address ??
            sellerT.step_property ??
            'Property & address',
        scheme: createT.step_scheme ?? sellerT.step_scheme ?? label.scheme,
        evidence:
            createT.step_evidence ?? sellerT.step_evidence ?? 'Evidence',
        review: createT.step_review ?? sellerT.step_review ?? 'Review & submit',
    };

    const stepComplete: Record<StepId, boolean> = {
        source: sourceComplete,
        customer: customerComplete,
        property: propertyComplete,
        scheme: schemeComplete,
        evidence: evidenceComplete,
        review: canSubmit,
    };

    const submit = (asDraft: boolean) => {
        transform((formData) => ({
            ...formData,
            as_draft: asDraft,
            seller_company_id: formData.seller_company_id || null,
            seller_user_id: formData.seller_user_id || null,
            zone_id: formData.zone_id || null,
            buying_price:
                formData.lead_source === 'rml_internal'
                    ? formData.buying_price || null
                    : null,
            size_m2: formData.size_m2 || null,
            occupancy_type: formData.occupancy_type || null,
            cadastral_reference: formData.cadastral_reference || null,
            address_line_2: formData.address_line_2 || null,
            notes: formData.notes || null,
            epc_rating: formData.epc_rating || null,
        }));

        post(route('admin.leads.store'), {
            forceFormData: true,
            onFinish: () => {
                transform((formData) => formData);
            },
        });
    };

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        const nextErrors = validateStep('review');
        if (Object.keys(nextErrors).length > 0) {
            setClientErrors(nextErrors);
            const firstKey = Object.keys(nextErrors)[0];
            const target = errorStep([firstKey, ...Object.keys(nextErrors)]);
            if (target) {
                setStep(target);
            }
            setStepAlert(Object.values(nextErrors)[0]);
            return;
        }
        if (!canSubmit) {
            return;
        }
        submit(false);
    };

    const setMetric = (key: string, value: MetricValue) => {
        const nextMetrics = { ...data.metrics, [key]: value };
        const patch: Partial<LeadFormData> = { metrics: nextMetrics };

        if (key === 'size_m2' || key === 'area_m2') {
            const synced = sizeFromMetric(value);
            if (synced !== '') {
                patch.size_m2 = synced;
            }
        }

        if (key === 'epc_rating') {
            patch.epc_rating = sizeFromMetric(value);
        }

        setData({
            ...data,
            ...patch,
        });
    };

    const handleZoneChange = (zoneId: string) => {
        const zone = selectedScheme?.zones.find(
            (item) => String(item.id) === zoneId,
        );
        setData({
            ...data,
            zone_id: zoneId,
            zone_code: zone?.code ?? '',
            metrics: {
                ...data.metrics,
                zone: zone?.code ?? '',
            },
        });
    };

    const errorStep = (errorKeys: string[]): StepId | null => {
        const sourceFields = [
            'lead_source',
            'seller_company_id',
            'seller_user_id',
            'buying_price',
        ];
        const customerFields = [
            'customer_first_name',
            'customer_last_name',
            'customer_phone',
            'customer_email',
            'notes',
        ];
        const propertyFields = [
            'address_line_1',
            'address_line_2',
            'city',
            'postcode',
            'country',
            'property_type',
            'occupancy_type',
            'size_m2',
            'cadastral_reference',
            'latitude',
            'longitude',
        ];
        const schemeFieldsKeys = [
            'scheme_id',
            'zone_id',
            'zone_code',
            'epc_rating',
            'metrics',
        ];
        const evidenceFields = [
            'evidence_photos',
            'evidence_video',
            'evidence_agreement',
            'evidence_eligibility',
        ];

        if (errorKeys.some((key) => sourceFields.includes(key))) {
            return 'source';
        }
        if (errorKeys.some((key) => customerFields.includes(key))) {
            return 'customer';
        }
        if (errorKeys.some((key) => propertyFields.includes(key))) {
            return 'property';
        }
        if (
            errorKeys.some(
                (key) =>
                    schemeFieldsKeys.includes(key) ||
                    key.startsWith('metrics.'),
            )
        ) {
            return 'scheme';
        }
        if (errorKeys.some((key) => evidenceFields.includes(key))) {
            return 'evidence';
        }

        return null;
    };

    useEffect(() => {
        const keys = Object.keys(errors);
        if (keys.length === 0) {
            return;
        }

        const target = errorStep(keys);
        if (target) {
            setStep(target);
        }

        const firstKey = keys[0];
        const firstMessage = (errors as Record<string, string>)[firstKey];
        if (firstMessage) {
            setStepAlert(firstMessage);
            setClientErrors((prev) => ({
                ...prev,
                ...Object.fromEntries(
                    keys.map((key) => [
                        key,
                        (errors as Record<string, string>)[key],
                    ]),
                ),
            }));
        }
    }, [errors]);

    useScrollToFirstError({
        ...clientErrors,
        ...(errors as Record<string, string | undefined>),
    });

    const fieldError = (key: string): string | undefined =>
        clientErrors[key] ??
        (errors as Record<string, string | undefined>)[key];

    const clearClientError = (key: string) => {
        setClientErrors((prev) => {
            if (!prev[key]) {
                return prev;
            }
            const next = { ...prev };
            delete next[key];
            return next;
        });
    };

    const validateSourceFields = (): Record<string, string> => {
        const next: Record<string, string> = {};

        if (!hasText(data.lead_source)) {
            next.lead_source = requiredMsg(label.leadSource);
        }

        if (data.lead_source === 'seller_company' && !hasText(data.seller_company_id)) {
            next.seller_company_id = requiredMsg(label.sellerCompany);
        }

        if (data.lead_source === 'seller_agent' && !hasText(data.seller_user_id)) {
            next.seller_user_id = requiredMsg(label.sellerAgent);
        }

        return next;
    };

    const validateCustomerFields = (): Record<string, string> => {
        const next: Record<string, string> = {};

        if (!hasText(data.customer_first_name)) {
            next.customer_first_name = requiredMsg(label.firstName);
        }
        if (!hasText(data.customer_last_name)) {
            next.customer_last_name = requiredMsg(label.lastName);
        }

        const phoneErr = phoneErrorMessage(data.customer_phone, phoneMessages, {
            required: true,
        });
        if (phoneErr) {
            next.customer_phone = phoneErr;
        }

        if (!hasText(data.customer_email)) {
            next.customer_email = requiredMsg(label.email);
        } else if (!isValidEmail(data.customer_email)) {
            next.customer_email =
                v?.email_format ??
                'Enter a valid email address (example: name@company.com).';
        }

        return next;
    };

    const validatePropertyFields = (): Record<string, string> => {
        const next: Record<string, string> = {};

        if (!hasText(data.address_line_1)) {
            next.address_line_1 = requiredMsg(label.address1);
        }
        if (!hasText(data.city)) {
            next.city = requiredMsg(label.city);
        }
        if (!hasText(data.postcode)) {
            next.postcode = requiredMsg(label.postcode);
        }
        if (!hasText(data.country)) {
            next.country = requiredMsg(label.country);
        }
        if (!hasText(data.property_type)) {
            next.property_type = requiredMsg(label.propertyType);
        }

        return next;
    };

    const validateSchemeFields = (): Record<string, string> => {
        const next: Record<string, string> = {};

        if (!hasText(data.scheme_id)) {
            next.scheme_id = requiredMsg(label.scheme);
        }

        schemeFields
            .filter((field) => field.required)
            .forEach((field) => {
                if (isZoneField(field)) {
                    if (!data.zone_id) {
                        next.zone_id = requiredMsg(field.label);
                    }
                    return;
                }
                if (field.key === 'epc_rating') {
                    if (
                        !metricFilled(
                            data.metrics.epc_rating ?? data.epc_rating ?? null,
                        )
                    ) {
                        next.epc_rating = requiredMsg(field.label);
                    }
                    return;
                }
                if (!metricFilled(data.metrics[field.key] ?? null)) {
                    next[`metrics.${field.key}`] = requiredMsg(field.label);
                }
            });

        if (
            !selectedScheme?.fields.some(isZoneField) &&
            (selectedScheme?.zones.length ?? 0) > 0 &&
            !data.zone_id
        ) {
            next.zone_id = requiredMsg(label.zone);
        }

        return next;
    };

    const validateEvidenceFields = (): Record<string, string> => {
        const next: Record<string, string> = {};
        if (!hasPhoto) {
            next.evidence_photos = requiredMsg(label.evidencePhotos);
        }
        if (!hasAgreement) {
            next.evidence_agreement = requiredMsg(label.evidenceAgreement);
        }
        return next;
    };

    const validateStep = (id: StepId): Record<string, string> => {
        switch (id) {
            case 'source':
                return validateSourceFields();
            case 'customer':
                return validateCustomerFields();
            case 'property':
                return validatePropertyFields();
            case 'scheme':
                return validateSchemeFields();
            case 'evidence':
                return validateEvidenceFields();
            case 'review':
                return {
                    ...validateSourceFields(),
                    ...validateCustomerFields(),
                    ...validatePropertyFields(),
                    ...validateSchemeFields(),
                    ...validateEvidenceFields(),
                };
            default:
                return {};
        }
    };

    const goNext = () => {
        const nextErrors = validateStep(step);
        if (Object.keys(nextErrors).length > 0) {
            setClientErrors(nextErrors);
            const firstMessage = Object.values(nextErrors)[0];
            setStepAlert(firstMessage ?? label.stepIncomplete);
            window.setTimeout(() => {
                const firstKey = Object.keys(nextErrors)[0];
                const el = document.querySelector(
                    `[name="${firstKey}"]`,
                ) as HTMLElement | null;
                el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el?.focus?.({ preventScroll: true });
            }, 50);
            return;
        }

        setClientErrors({});
        setStepAlert(null);
        const index = STEPS.indexOf(step);
        if (index < STEPS.length - 1) {
            setStep(STEPS[index + 1]);
        }
    };

    const goPrevious = () => {
        setStepAlert(null);
        setClientErrors({});
        const index = STEPS.indexOf(step);
        if (index > 0) {
            setStep(STEPS[index - 1]);
        }
    };

    const renderField = (field: SchemeField) => {
        const errorKey = `metrics.${field.key}`;
        const error =
            clientErrors[errorKey] ??
            fieldError(errorKey) ??
            (errors as Record<string, string>)[errorKey];

        if (isZoneField(field)) {
            return (
                <Select
                    key={field.id}
                    label={field.label}
                    name={`metrics.${field.key}`}
                    required={field.required}
                    value={data.zone_id}
                    placeholder={sellerCommon.all_zones ?? common.all}
                    error={error ?? fieldError('zone_id')}
                    options={(selectedScheme?.zones ?? []).map((zone) => ({
                        value: String(zone.id),
                        label: `${zone.code} — ${zone.name}`,
                    }))}
                    onChange={(e) => handleZoneChange(e.target.value)}
                />
            );
        }

        if (field.key === 'epc_rating') {
            return (
                <Select
                    key={field.id}
                    label={field.label}
                    name="epc_rating"
                    required={field.required}
                    value={data.epc_rating}
                    placeholder={common.all}
                    error={error ?? fieldError('epc_rating')}
                    options={selectOptionsFromField(field)}
                    onChange={(e) => {
                        setData('epc_rating', e.target.value);
                        setMetric('epc_rating', e.target.value);
                    }}
                />
            );
        }

        const value = data.metrics[field.key] ?? '';

        if (field.type === 'textarea') {
            return (
                <Textarea
                    key={field.id}
                    label={field.label}
                    name={`metrics.${field.key}`}
                    required={field.required}
                    value={String(value)}
                    error={error}
                    onChange={(e) => setMetric(field.key, e.target.value)}
                />
            );
        }

        if (field.type === 'checkbox') {
            return (
                <Checkbox
                    key={field.id}
                    label={field.label}
                    name={`metrics.${field.key}`}
                    checked={Boolean(value)}
                    error={error}
                    onChange={(e) => setMetric(field.key, e.target.checked)}
                />
            );
        }

        if (field.type === 'select') {
            return (
                <Select
                    key={field.id}
                    label={field.label}
                    name={`metrics.${field.key}`}
                    required={field.required}
                    value={String(value)}
                    placeholder={common.all}
                    error={error}
                    options={selectOptionsFromField(field)}
                    onChange={(e) => setMetric(field.key, e.target.value)}
                />
            );
        }

        return (
            <FormInput
                key={field.id}
                label={field.label}
                name={`metrics.${field.key}`}
                type={field.type === 'number' ? 'number' : 'text'}
                required={field.required}
                value={String(value)}
                error={error}
                onChange={(e) =>
                    setMetric(
                        field.key,
                        field.type === 'number'
                            ? e.target.value === ''
                                ? null
                                : Number(e.target.value)
                            : e.target.value,
                    )
                }
            />
        );
    };

    const statusLabel = (complete: boolean) =>
        complete ? label.complete : label.incomplete;

    const summaryValue = (value: string | null | undefined) =>
        hasText(value) ? value : '—';

    return (
        <AppLayout
            title={createT.title ?? 'Create lead'}
            subtitle={
                createT.subtitle ??
                'Create a lead for a seller or as an RML internal lead'
            }
        >
            <Head title={createT.title ?? 'Create lead'} />

            <div className="mb-1">
                <BackLink
                    href={route('admin.leads.index', { tab: 'registered' })}
                    label={common.back ?? 'Back'}
                    showLabel
                    useHistory={false}
                />
            </div>

            <form onSubmit={onSubmit} className="rml-page-stack">
                {(errors as Record<string, string>).as_draft && (
                    <Alert variant="error">
                        {(errors as Record<string, string>).as_draft}
                    </Alert>
                )}
                {stepAlert && (
                    <Alert variant="error" title={label.missingRequired}>
                        {stepAlert}
                    </Alert>
                )}

                <Stepper
                    current={step}
                    onChange={(id) => setStep(id as StepId)}
                    steps={STEPS.map((id) => ({
                        id,
                        label: stepLabels[id],
                        complete: stepComplete[id],
                    }))}
                />

                {step === 'source' && (
                    <section className="rml-card-compact space-y-3">
                        <h2 className="text-sm font-semibold text-rml-text">
                            {stepLabels.source}
                        </h2>
                        <div className="rml-form-grid">
                            <Select
                                label={label.leadSource}
                                name="lead_source"
                                required
                                value={data.lead_source}
                                error={fieldError('lead_source')}
                                options={sources.map((source) => ({
                                    label: sourceLabel(source),
                                    value: source,
                                }))}
                                onChange={(e) => {
                                    clearClientError('lead_source');
                                    clearClientError('seller_company_id');
                                    clearClientError('seller_user_id');
                                    setData({
                                        ...data,
                                        lead_source: e.target.value,
                                        seller_company_id: '',
                                        seller_user_id: '',
                                        buying_price:
                                            e.target.value === 'rml_internal'
                                                ? data.buying_price
                                                : '',
                                    });
                                }}
                            />

                            {(data.lead_source === 'seller_company' ||
                                data.lead_source === 'seller_agent') && (
                                <Select
                                    label={label.sellerCompany}
                                    name="seller_company_id"
                                    required={data.lead_source === 'seller_company'}
                                    value={data.seller_company_id}
                                    error={fieldError('seller_company_id')}
                                    placeholder="—"
                                    options={seller_companies.map((company) => ({
                                        label: company.name,
                                        value: String(company.id),
                                    }))}
                                    onChange={(e) => {
                                        clearClientError('seller_company_id');
                                        const companyId = e.target.value;
                                        const agentStillValid = seller_agents.some(
                                            (agent) =>
                                                String(agent.id) ===
                                                    data.seller_user_id &&
                                                (!companyId ||
                                                    String(agent.company_id) ===
                                                        companyId),
                                        );
                                        setData({
                                            ...data,
                                            seller_company_id: companyId,
                                            seller_user_id: agentStillValid
                                                ? data.seller_user_id
                                                : '',
                                        });
                                    }}
                                />
                            )}

                            {(data.lead_source === 'seller_agent' ||
                                data.lead_source === 'seller_company') && (
                                <Select
                                    label={label.sellerAgent}
                                    name="seller_user_id"
                                    required={data.lead_source === 'seller_agent'}
                                    value={data.seller_user_id}
                                    error={fieldError('seller_user_id')}
                                    placeholder="—"
                                    options={agentOptions.map((agent) => ({
                                        label: `${agent.name} (${agent.email})`,
                                        value: String(agent.id),
                                    }))}
                                    onChange={(e) => {
                                        clearClientError('seller_user_id');
                                        const agentId = e.target.value;
                                        const agent = seller_agents.find(
                                            (item) => String(item.id) === agentId,
                                        );
                                        setData({
                                            ...data,
                                            seller_user_id: agentId,
                                            seller_company_id:
                                                data.lead_source ===
                                                    'seller_agent' &&
                                                agent?.company_id &&
                                                !data.seller_company_id
                                                    ? String(agent.company_id)
                                                    : data.seller_company_id,
                                        });
                                    }}
                                />
                            )}

                            {data.lead_source === 'rml_internal' && (
                                <FormInput
                                    label={label.buyingPrice}
                                    name="buying_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.buying_price}
                                    error={fieldError('buying_price')}
                                    onChange={(e) =>
                                        setData('buying_price', e.target.value)
                                    }
                                />
                            )}
                        </div>
                    </section>
                )}

                {step === 'customer' && (
                    <section className="rml-card-compact space-y-3">
                        <h2 className="text-sm font-semibold text-rml-text">
                            {stepLabels.customer}
                        </h2>
                        <div className="rml-form-grid">
                            <FormInput
                                label={label.firstName}
                                name="customer_first_name"
                                required
                                value={data.customer_first_name}
                                error={fieldError('customer_first_name')}
                                onChange={(e) => {
                                    clearClientError('customer_first_name');
                                    setData(
                                        'customer_first_name',
                                        e.target.value,
                                    );
                                }}
                            />
                            <FormInput
                                label={label.lastName}
                                name="customer_last_name"
                                required
                                value={data.customer_last_name}
                                error={fieldError('customer_last_name')}
                                onChange={(e) => {
                                    clearClientError('customer_last_name');
                                    setData('customer_last_name', e.target.value);
                                }}
                            />
                            <FormInput
                                label={label.phone}
                                name="customer_phone"
                                type="tel"
                                inputMode="tel"
                                autoComplete="tel"
                                required
                                value={data.customer_phone}
                                error={fieldError('customer_phone')}
                                hint={
                                    fieldError('customer_phone')
                                        ? undefined
                                        : v?.phone_length
                                }
                                onChange={(e) => {
                                    const value = sanitizePhoneInput(
                                        e.target.value,
                                    );
                                    setData('customer_phone', value);
                                    const msg = phoneErrorMessage(
                                        value,
                                        phoneMessages,
                                        { required: true },
                                    );
                                    setClientErrors((prev) => {
                                        const next = { ...prev };
                                        if (msg) {
                                            next.customer_phone = msg;
                                        } else {
                                            delete next.customer_phone;
                                        }
                                        return next;
                                    });
                                }}
                            />
                            <FormInput
                                label={label.email}
                                name="customer_email"
                                type="email"
                                required
                                value={data.customer_email}
                                error={fieldError('customer_email')}
                                onChange={(e) => {
                                    clearClientError('customer_email');
                                    setData('customer_email', e.target.value);
                                }}
                            />
                            <Textarea
                                label={label.notes}
                                name="notes"
                                className="sm:col-span-2"
                                value={data.notes}
                                error={fieldError('notes')}
                                onChange={(e) => setData('notes', e.target.value)}
                            />
                        </div>
                    </section>
                )}

                {step === 'property' && (
                    <section className="rml-card-compact space-y-3">
                        <h2 className="text-sm font-semibold text-rml-text">
                            {stepLabels.property}
                        </h2>
                        <div className="rml-form-grid">
                            <FormInput
                                label={label.address1}
                                name="address_line_1"
                                className="sm:col-span-2"
                                required
                                value={data.address_line_1}
                                error={fieldError('address_line_1')}
                                onChange={(e) => {
                                    clearClientError('address_line_1');
                                    setData('address_line_1', e.target.value);
                                }}
                            />
                            <FormInput
                                label={label.address2}
                                name="address_line_2"
                                className="sm:col-span-2"
                                value={data.address_line_2}
                                error={fieldError('address_line_2')}
                                onChange={(e) =>
                                    setData('address_line_2', e.target.value)
                                }
                            />
                            <FormInput
                                label={label.city}
                                name="city"
                                required
                                value={data.city}
                                error={fieldError('city')}
                                onChange={(e) => {
                                    clearClientError('city');
                                    setData('city', e.target.value);
                                }}
                            />
                            <FormInput
                                label={label.postcode}
                                name="postcode"
                                required
                                value={data.postcode}
                                error={fieldError('postcode')}
                                onChange={(e) => {
                                    clearClientError('postcode');
                                    setData('postcode', e.target.value);
                                }}
                            />
                            <CountrySelect
                                label={label.country}
                                name="country"
                                required
                                value={data.country}
                                error={fieldError('country')}
                                onChange={(e) => {
                                    clearClientError('country');
                                    setData('country', e.target.value);
                                }}
                            />
                            <Select
                                label={label.propertyType}
                                name="property_type"
                                required
                                value={data.property_type}
                                error={fieldError('property_type')}
                                placeholder={common.all}
                                options={propertyTypeOptions}
                                onChange={(e) => {
                                    clearClientError('property_type');
                                    setData('property_type', e.target.value);
                                }}
                            />
                            <Select
                                label={label.occupancyType}
                                name="occupancy_type"
                                value={data.occupancy_type}
                                error={fieldError('occupancy_type')}
                                placeholder={common.all}
                                options={occupancyOptions}
                                onChange={(e) =>
                                    setData('occupancy_type', e.target.value)
                                }
                            />
                            <FormInput
                                label={label.sizeM2}
                                name="size_m2"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.size_m2}
                                error={fieldError('size_m2')}
                                onChange={(e) => {
                                    const value = e.target.value;
                                    setData({
                                        ...data,
                                        size_m2: value,
                                        metrics: {
                                            ...data.metrics,
                                            ...(value !== ''
                                                ? { size_m2: Number(value) }
                                                : {}),
                                        },
                                    });
                                }}
                            />
                            <FormInput
                                label={label.cadastral}
                                name="cadastral_reference"
                                className="sm:col-span-2"
                                value={data.cadastral_reference}
                                error={fieldError('cadastral_reference')}
                                onChange={(e) =>
                                    setData(
                                        'cadastral_reference',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </section>
                )}

                {step === 'scheme' && (
                    <section className="rml-card-compact space-y-3">
                        <h2 className="text-sm font-semibold text-rml-text">
                            {stepLabels.scheme}
                        </h2>
                        <Select
                            label={label.scheme}
                            name="scheme_id"
                            required
                            value={data.scheme_id}
                            error={fieldError('scheme_id')}
                            options={schemes.map((scheme) => ({
                                value: String(scheme.id),
                                label: scheme.name,
                            }))}
                            onChange={(e) => {
                                setData({
                                    ...data,
                                    scheme_id: e.target.value,
                                    zone_id: '',
                                    zone_code: '',
                                    metrics: {},
                                    epc_rating: '',
                                });
                            }}
                        />

                        {!selectedScheme?.fields.some(isZoneField) &&
                            (selectedScheme?.zones.length ?? 0) > 0 && (
                                <Select
                                    label={label.zone}
                                    name="zone_id"
                                    required
                                    value={data.zone_id}
                                    error={fieldError('zone_id')}
                                    placeholder={
                                        sellerCommon.all_zones ?? common.all
                                    }
                                    options={(selectedScheme?.zones ?? []).map(
                                        (zone) => ({
                                            value: String(zone.id),
                                            label: `${zone.code} — ${zone.name}`,
                                        }),
                                    )}
                                    onChange={(e) =>
                                        handleZoneChange(e.target.value)
                                    }
                                />
                            )}

                        <div className="rml-form-grid">
                            {schemeFields.map((field) => renderField(field))}
                        </div>
                    </section>
                )}

                {step === 'evidence' && (
                    <section className="rml-card-compact space-y-3">
                        <div>
                            <h2 className="text-sm font-semibold text-rml-text">
                                {stepLabels.evidence}
                            </h2>
                            <p className="mt-1 text-xs text-rml-muted">
                                {label.evidenceHint}
                            </p>
                        </div>

                        <div className="space-y-3">
                            <FileUpload
                                label={label.evidencePhotos}
                                required
                                multiple
                                accept="image/jpeg,image/png,image/webp,image/heic,.jpg,.jpeg,.png,.webp,.heic"
                                files={fileMetas(data.evidence_photos)}
                                error={fieldError('evidence_photos')}
                                onFilesSelected={(list) => {
                                    clearClientError('evidence_photos');
                                    setData('evidence_photos', [
                                        ...data.evidence_photos,
                                        ...Array.from(list),
                                    ]);
                                }}
                                onRemove={(id) =>
                                    setData(
                                        'evidence_photos',
                                        data.evidence_photos.filter(
                                            (file, index) =>
                                                `${file.name}-${file.size}-${index}` !==
                                                id,
                                        ),
                                    )
                                }
                            />
                            <FileUpload
                                label={label.evidenceAgreement}
                                required
                                multiple={false}
                                accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png"
                                files={
                                    data.evidence_agreement
                                        ? fileMetas([data.evidence_agreement])
                                        : []
                                }
                                error={fieldError('evidence_agreement')}
                                onFilesSelected={(list) => {
                                    clearClientError('evidence_agreement');
                                    setData(
                                        'evidence_agreement',
                                        list.item(0),
                                    );
                                }}
                                onRemove={() =>
                                    setData('evidence_agreement', null)
                                }
                            />
                            <FileUpload
                                label={label.evidenceEligibility}
                                multiple
                                accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png"
                                files={fileMetas(data.evidence_eligibility)}
                                error={fieldError('evidence_eligibility')}
                                onFilesSelected={(list) =>
                                    setData('evidence_eligibility', [
                                        ...data.evidence_eligibility,
                                        ...Array.from(list),
                                    ])
                                }
                                onRemove={(id) =>
                                    setData(
                                        'evidence_eligibility',
                                        data.evidence_eligibility.filter(
                                            (file, index) =>
                                                `${file.name}-${file.size}-${index}` !==
                                                id,
                                        ),
                                    )
                                }
                            />
                            <FileUpload
                                label={label.evidenceVideo}
                                multiple={false}
                                accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm"
                                files={
                                    data.evidence_video
                                        ? fileMetas([data.evidence_video])
                                        : []
                                }
                                error={fieldError('evidence_video')}
                                onFilesSelected={(list) =>
                                    setData('evidence_video', list.item(0))
                                }
                                onRemove={() =>
                                    setData('evidence_video', null)
                                }
                            />
                        </div>
                    </section>
                )}

                {step === 'review' && (
                    <div className="rml-page-stack">
                        <section className="rml-card-compact space-y-2">
                            <h2 className="text-sm font-semibold text-rml-text">
                                {stepLabels.review}
                            </h2>
                            <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                {(
                                    [
                                        ['source', sourceComplete],
                                        ['customer', customerComplete],
                                        ['property', propertyComplete],
                                        ['scheme', schemeComplete],
                                        ['evidence', evidenceComplete],
                                    ] as Array<[StepId, boolean]>
                                ).map(([id, complete]) => (
                                    <div
                                        key={id}
                                        className="flex items-center justify-between gap-2 rounded-lg border border-rml-border px-3 py-2"
                                    >
                                        <div>
                                            <dt className="font-medium text-rml-text">
                                                {stepLabels[id]}
                                            </dt>
                                            <dd className="text-xs text-rml-muted">
                                                {statusLabel(complete)}
                                            </dd>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => setStep(id)}
                                        >
                                            {label.edit}
                                        </Button>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        {missingItems.length > 0 && (
                            <Alert variant="warning">
                                <p className="font-medium">
                                    {label.submitIncomplete}
                                </p>
                                <p className="mt-1 text-sm">
                                    {label.missingRequired}
                                </p>
                                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">
                                    {missingItems.map((item) => (
                                        <li key={item.step}>
                                            <button
                                                type="button"
                                                className="font-semibold text-rml-primary hover:underline"
                                                onClick={() =>
                                                    setStep(item.step)
                                                }
                                            >
                                                {item.label}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </Alert>
                        )}

                        <section className="rml-card-compact space-y-2 text-sm">
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="text-sm font-semibold text-rml-text">
                                    {stepLabels.source}
                                </h3>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setStep('source')}
                                >
                                    {label.goToSection}
                                </Button>
                            </div>
                            <p>{sourceLabel(data.lead_source)}</p>
                            {(data.lead_source === 'seller_company' ||
                                data.lead_source === 'seller_agent') && (
                                <p className="text-rml-muted">
                                    {[
                                        selectedCompany?.name,
                                        selectedAgent
                                            ? `${selectedAgent.name} (${selectedAgent.email})`
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ') || '—'}
                                </p>
                            )}
                            {data.lead_source === 'rml_internal' &&
                                hasText(data.buying_price) && (
                                    <p className="text-rml-muted">
                                        {label.buyingPrice}: {data.buying_price}
                                    </p>
                                )}

                            <div className="flex items-center justify-between gap-2 pt-2">
                                <h3 className="text-sm font-semibold text-rml-text">
                                    {stepLabels.customer}
                                </h3>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setStep('customer')}
                                >
                                    {label.goToSection}
                                </Button>
                            </div>
                            <p>
                                {summaryValue(
                                    `${data.customer_first_name} ${data.customer_last_name}`.trim(),
                                )}
                            </p>
                            <p className="text-rml-muted">
                                {[data.customer_phone, data.customer_email]
                                    .filter(Boolean)
                                    .join(' · ') || '—'}
                            </p>
                            {hasText(data.notes) && (
                                <p className="text-rml-muted">{data.notes}</p>
                            )}

                            <div className="flex items-center justify-between gap-2 pt-2">
                                <h3 className="text-sm font-semibold text-rml-text">
                                    {stepLabels.property}
                                </h3>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setStep('property')}
                                >
                                    {label.goToSection}
                                </Button>
                            </div>
                            <p>
                                {[
                                    data.address_line_1,
                                    data.address_line_2,
                                    data.city,
                                    data.postcode,
                                    data.country,
                                ]
                                    .filter(Boolean)
                                    .join(', ') || '—'}
                            </p>
                            <p className="text-rml-muted">
                                {[
                                    data.property_type
                                        ? propertyTypeOptions.find(
                                              (option) =>
                                                  option.value ===
                                                  data.property_type,
                                          )?.label ?? data.property_type
                                        : null,
                                    data.occupancy_type
                                        ? occupancyOptions.find(
                                              (option) =>
                                                  option.value ===
                                                  data.occupancy_type,
                                          )?.label ?? data.occupancy_type
                                        : null,
                                    data.size_m2
                                        ? `${data.size_m2} m²`
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || '—'}
                            </p>

                            <div className="flex items-center justify-between gap-2 pt-2">
                                <h3 className="text-sm font-semibold text-rml-text">
                                    {stepLabels.scheme}
                                </h3>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setStep('scheme')}
                                >
                                    {label.goToSection}
                                </Button>
                            </div>
                            <p>{selectedScheme?.name ?? '—'}</p>
                            <p className="text-rml-muted">
                                {[
                                    data.zone_code ||
                                        selectedScheme?.zones.find(
                                            (zone) =>
                                                String(zone.id) === data.zone_id,
                                        )?.code,
                                    data.epc_rating
                                        ? `EPC ${data.epc_rating}`
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || '—'}
                            </p>

                            <div className="flex items-center justify-between gap-2 pt-2">
                                <h3 className="text-sm font-semibold text-rml-text">
                                    {stepLabels.evidence}
                                </h3>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setStep('evidence')}
                                >
                                    {label.goToSection}
                                </Button>
                            </div>
                            <p className="text-rml-muted">
                                {[
                                    hasPhoto
                                        ? `${data.evidence_photos.length} photo(s)`
                                        : null,
                                    hasAgreement ? 'Agreement' : null,
                                    data.evidence_eligibility.length > 0
                                        ? `${data.evidence_eligibility.length} eligibility file(s)`
                                        : null,
                                    data.evidence_video ? 'Video' : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || '—'}
                            </p>
                        </section>
                    </div>
                )}

                <div className="sticky bottom-0 z-10 -mx-4 border-t border-rml-border bg-rml-background/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border sm:bg-white">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() => submit(true)}
                            >
                                {label.saveDraft}
                            </Button>
                        </div>
                        <div className="flex flex-wrap gap-2 sm:justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={processing || step === 'source'}
                                onClick={goPrevious}
                            >
                                {label.previous}
                            </Button>
                            {step !== 'review' ? (
                                <Button
                                    type="button"
                                    disabled={
                                        processing || !stepComplete[step]
                                    }
                                    title={
                                        !stepComplete[step]
                                            ? label.stepIncomplete
                                            : undefined
                                    }
                                    onClick={goNext}
                                >
                                    {label.next}
                                </Button>
                            ) : (
                                <Button
                                    type="submit"
                                    disabled={processing || !canSubmit}
                                    title={
                                        !canSubmit
                                            ? label.submitIncomplete
                                            : undefined
                                    }
                                >
                                    {label.submit}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
