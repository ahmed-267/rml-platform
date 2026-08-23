import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
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
    isEmptyOrValidPhone,
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

interface ExistingLead {
    id: number;
    lead_reference: string;
    status: string | null;
    customer_first_name: string;
    customer_last_name: string;
    customer_phone: string;
    customer_whatsapp: string | null;
    customer_email: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    postcode: string | null;
    country: string | null;
    cadastral_reference?: string | null;
    property_type: string | null;
    epc_rating: string | null;
    notes: string | null;
    scheme: { id: number; name: string; slug: string } | null;
    zone: { id: number; code: string; name: string } | null;
    metrics: Record<string, string | number | boolean | null>;
    evidence: Array<{
        id: number;
        file_type: string | null;
        original_name: string | null;
        view_url?: string;
        download_url?: string;
        is_image?: boolean;
    }>;
}

type MetricValue = string | number | boolean | null;
type StepId = 'customer' | 'property' | 'scheme' | 'evidence' | 'review';

interface LeadFormData {
    lead_id: number | '';
    as_draft: boolean;
    scheme_id: string;
    zone_id: string;
    zone_code: string;
    customer_first_name: string;
    customer_last_name: string;
    customer_phone: string;
    customer_whatsapp: string;
    customer_email: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    postcode: string;
    country: string;
    cadastral_reference: string;
    property_type: string;
    epc_rating: string;
    notes: string;
    metrics: Record<string, MetricValue>;
    evidence_photos: File[];
    evidence_video: File | null;
    evidence_agreement: File | null;
    evidence_eligibility: File[];
    consent: boolean;
    evidence_genuine: boolean;
    info_accurate: boolean;
}

const STEPS: StepId[] = [
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

export default function SellerLeadsCreate({
    schemes,
    lead = null,
}: {
    schemes: SchemeOption[];
    lead?: ExistingLead | null;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.seller.leads;
    const common = translations.seller.common;
    const v = translations.validation;

    const requiredMsg = (field: string) =>
        (v?.field_required ?? t.field_required ?? ':field is required.').replace(
            ':field',
            field,
        );

    const phoneMessages = phoneMessagesFromTranslations(
        v,
        requiredMsg,
        t.phone,
    );

    const [step, setStep] = useState<StepId>('customer');
    const [stepAlert, setStepAlert] = useState<string | null>(null);
    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );

    const initialMetrics = useMemo(() => {
        if (!lead?.metrics) {
            return {};
        }
        const next: Record<string, MetricValue> = {};
        Object.entries(lead.metrics).forEach(([key, value]) => {
            next[key] = value;
        });
        return next;
    }, [lead]);

    const { data, setData, post, processing, errors, transform } =
        useForm<LeadFormData>({
            lead_id: lead?.id ?? '',
            as_draft: false,
            scheme_id: lead?.scheme
                ? String(lead.scheme.id)
                : schemes[0]
                  ? String(schemes[0].id)
                  : '',
            zone_id: lead?.zone ? String(lead.zone.id) : '',
            zone_code: lead?.zone?.code ?? '',
            customer_first_name: lead?.customer_first_name ?? '',
            customer_last_name: lead?.customer_last_name ?? '',
            customer_phone: lead?.customer_phone ?? '',
            customer_whatsapp: lead?.customer_whatsapp ?? '',
            customer_email: lead?.customer_email ?? '',
            address_line_1: lead?.address_line_1 ?? '',
            address_line_2: lead?.address_line_2 ?? '',
            city: lead?.city ?? '',
            postcode: lead?.postcode ?? '',
            country: lead?.country ?? DEFAULT_COUNTRY,
            cadastral_reference: lead?.cadastral_reference ?? '',
            property_type: lead?.property_type ?? '',
            epc_rating: lead?.epc_rating ?? '',
            notes: lead?.notes ?? '',
            metrics: initialMetrics,
            evidence_photos: [],
            evidence_video: null,
            evidence_agreement: null,
            evidence_eligibility: [],
            consent: false,
            evidence_genuine: false,
            info_accurate: false,
        });

    const selectedScheme = useMemo(
        () =>
            schemes.find((scheme) => String(scheme.id) === data.scheme_id) ??
            null,
        [schemes, data.scheme_id],
    );

    const existingEvidenceTypes = useMemo(() => {
        const types = new Set(
            (lead?.evidence ?? [])
                .map((file) => file.file_type)
                .filter(Boolean) as string[],
        );
        return types;
    }, [lead]);

    const customerComplete =
        hasText(data.customer_first_name) &&
        hasText(data.customer_last_name) &&
        isValidPhone(data.customer_phone) &&
        hasText(data.customer_email) &&
        isValidEmail(data.customer_email) &&
        isEmptyOrValidPhone(data.customer_whatsapp);

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
                    field.key !== 'notes',
            ),
        [selectedScheme],
    );

    const notesRequired = selectedScheme?.slug === 'heat-pumps';

    const schemeComplete = useMemo(() => {
        if (!selectedScheme || !data.scheme_id) {
            return false;
        }

        const fieldsOk = schemeFields
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

        if (notesRequired && !hasText(data.notes)) {
            return false;
        }

        return fieldsOk;
    }, [
        selectedScheme,
        data.scheme_id,
        data.zone_id,
        data.metrics,
        data.epc_rating,
        data.notes,
        schemeFields,
        notesRequired,
    ]);

    const hasPhoto =
        data.evidence_photos.length > 0 ||
        existingEvidenceTypes.has('photo');
    const hasAgreement =
        data.evidence_agreement != null ||
        existingEvidenceTypes.has('signed_homeowner_agreement');
    const hasVideo =
        data.evidence_video != null || existingEvidenceTypes.has('video');
    const hasEligibility =
        data.evidence_eligibility.length > 0 ||
        existingEvidenceTypes.has('eligibility_document');

    // Required evidence: photos + homeowner agreement for every scheme.
    const evidenceComplete = hasPhoto && hasAgreement;

    const declarationsComplete =
        data.consent && data.evidence_genuine && data.info_accurate;

    const canSubmit =
        customerComplete &&
        propertyComplete &&
        schemeComplete &&
        evidenceComplete &&
        declarationsComplete;

    const missingItems = useMemo(() => {
        const items: Array<{ step: StepId; label: string }> = [];
        if (!customerComplete) {
            items.push({
                step: 'customer',
                label: t.section_customer ?? t.step_customer,
            });
        }
        if (!propertyComplete) {
            items.push({
                step: 'property',
                label: t.section_property ?? t.step_property,
            });
        }
        if (!schemeComplete) {
            items.push({
                step: 'scheme',
                label: t.section_scheme ?? t.step_scheme,
            });
        }
        if (!evidenceComplete) {
            items.push({
                step: 'evidence',
                label: t.section_evidence ?? t.step_evidence,
            });
        }
        if (!declarationsComplete) {
            items.push({
                step: 'review',
                label: t.section_declarations,
            });
        }
        return items;
    }, [
        customerComplete,
        propertyComplete,
        schemeComplete,
        evidenceComplete,
        declarationsComplete,
        t,
    ]);

    const stepLabels: Record<StepId, string> = {
        customer: t.step_customer ?? t.section_customer,
        property: t.step_property ?? t.section_property,
        scheme: t.step_scheme ?? t.section_scheme,
        evidence: t.step_evidence ?? t.section_evidence,
        review: t.step_review ?? t.section_review,
    };

    const stepComplete: Record<StepId, boolean> = {
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
            lead_id: formData.lead_id || undefined,
            zone_id: formData.zone_id || '',
        }));

        post(route('seller.leads.store'), {
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
        setData('metrics', { ...data.metrics, [key]: value });
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

    const stepIsComplete = (id: StepId): boolean => {
        switch (id) {
            case 'customer':
                return customerComplete;
            case 'property':
                return propertyComplete;
            case 'scheme':
                return schemeComplete;
            case 'evidence':
                return evidenceComplete;
            case 'review':
                return canSubmit;
            default:
                return false;
        }
    };

    const errorStep = (errorKeys: string[]): StepId | null => {
        const customerFields = [
            'customer_first_name',
            'customer_last_name',
            'customer_phone',
            'customer_email',
            'customer_whatsapp',
        ];
        const propertyFields = [
            'address_line_1',
            'address_line_2',
            'city',
            'postcode',
            'country',
            'property_type',
        ];
        const schemeFieldsKeys = [
            'scheme_id',
            'zone_id',
            'zone_code',
            'size_m2',
            'epc_rating',
            'notes',
            'metrics',
        ];
        const evidenceFields = [
            'evidence_photos',
            'evidence_video',
            'evidence_agreement',
            'evidence_eligibility',
        ];
        const reviewFields = ['consent', 'evidence_genuine', 'info_accurate'];

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
        if (errorKeys.some((key) => reviewFields.includes(key))) {
            return 'review';
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

    const validateCustomerFields = (): Record<string, string> => {
        const next: Record<string, string> = {};

        if (!hasText(data.customer_first_name)) {
            next.customer_first_name = requiredMsg(t.first_name);
        }
        if (!hasText(data.customer_last_name)) {
            next.customer_last_name = requiredMsg(t.last_name);
        }

        const phoneErr = phoneErrorMessage(data.customer_phone, phoneMessages, {
            required: true,
        });
        if (phoneErr) {
            next.customer_phone = phoneErr;
        }

        const whatsappErr = phoneErrorMessage(
            data.customer_whatsapp,
            phoneMessages,
            { required: false },
        );
        if (whatsappErr) {
            next.customer_whatsapp = whatsappErr;
        }

        if (!hasText(data.customer_email)) {
            next.customer_email = requiredMsg(t.email);
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
            next.address_line_1 = requiredMsg(t.address_1);
        }
        if (!hasText(data.city)) {
            next.city = requiredMsg(t.city);
        }
        if (!hasText(data.postcode)) {
            next.postcode = requiredMsg(t.postcode);
        }
        if (!hasText(data.country)) {
            next.country = requiredMsg(t.country);
        }
        if (!hasText(data.property_type)) {
            next.property_type = requiredMsg(t.property_type);
        }

        return next;
    };

    const validateSchemeFields = (): Record<string, string> => {
        const next: Record<string, string> = {};

        if (!hasText(data.scheme_id)) {
            next.scheme_id = requiredMsg(t.scheme);
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
            next.zone_id = requiredMsg(common.zone);
        }

        if (notesRequired && !hasText(data.notes)) {
            next.notes = requiredMsg(t.technical_notes ?? t.notes);
        }

        return next;
    };

    const validateEvidenceFields = (): Record<string, string> => {
        const next: Record<string, string> = {};
        if (!hasPhoto) {
            next.evidence_photos = requiredMsg(t.evidence_photos);
        }
        if (!hasAgreement) {
            next.evidence_agreement = requiredMsg(t.evidence_agreement);
        }
        return next;
    };

    const validateReviewFields = (): Record<string, string> => {
        const next: Record<string, string> = {};
        if (!data.consent) {
            next.consent = requiredMsg(t.decl_consent);
        }
        if (!data.evidence_genuine) {
            next.evidence_genuine = requiredMsg(t.decl_genuine);
        }
        if (!data.info_accurate) {
            next.info_accurate = requiredMsg(t.decl_accurate);
        }
        return next;
    };

    const validateStep = (id: StepId): Record<string, string> => {
        switch (id) {
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
                    ...validateCustomerFields(),
                    ...validatePropertyFields(),
                    ...validateSchemeFields(),
                    ...validateEvidenceFields(),
                    ...validateReviewFields(),
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
            setStepAlert(firstMessage);
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
                    placeholder={common.all_zones ?? common.all}
                    error={error ?? fieldError('zone_id')}
                    options={(selectedScheme?.zones ?? []).map((zone) => ({
                        value: String(zone.id),
                        label: zone.code,
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
        complete
            ? (t.complete ?? common.complete ?? 'Complete')
            : (t.incomplete ?? common.incomplete ?? 'Incomplete');

    return (
        <AppLayout
            title={
                lead
                    ? (t.continue_draft ?? t.create_title)
                    : t.create_title
            }
            subtitle={
                lead
                    ? lead.lead_reference
                    : t.create_subtitle
            }
        >
            <Head
                title={
                    lead
                        ? (t.continue_draft ?? t.create_title)
                        : t.create_title
                }
            />

            <div className="mb-1">
                <BackLink
                    href={route('seller.leads.index')}
                    label={common.back}
                />
            </div>

            <form onSubmit={onSubmit} className="rml-page-stack">
                {(errors as Record<string, string>).as_draft && (
                    <Alert variant="error">
                        {(errors as Record<string, string>).as_draft}
                    </Alert>
                )}
                {stepAlert && (
                    <Alert variant="error" title={t.missing_required}>
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

                {step === 'customer' && (
                    <section className="rml-card-compact space-y-3">
                        <h2 className="text-sm font-semibold text-rml-text">
                            {stepLabels.customer}
                        </h2>
                        <div className="rml-form-grid">
                            <FormInput
                                label={t.first_name}
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
                                label={t.last_name}
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
                                label={t.phone}
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
                                label={t.whatsapp_optional ?? t.whatsapp}
                                name="customer_whatsapp"
                                type="tel"
                                inputMode="tel"
                                autoComplete="tel"
                                value={data.customer_whatsapp}
                                error={fieldError('customer_whatsapp')}
                                onChange={(e) => {
                                    const value = sanitizePhoneInput(
                                        e.target.value,
                                    );
                                    setData('customer_whatsapp', value);
                                    const msg = phoneErrorMessage(
                                        value,
                                        phoneMessages,
                                        { required: false },
                                    );
                                    setClientErrors((prev) => {
                                        const next = { ...prev };
                                        if (msg) {
                                            next.customer_whatsapp = msg;
                                        } else {
                                            delete next.customer_whatsapp;
                                        }
                                        return next;
                                    });
                                }}
                            />
                            <FormInput
                                label={t.email}
                                name="customer_email"
                                type="email"
                                required
                                className="sm:col-span-2"
                                value={data.customer_email}
                                error={fieldError('customer_email')}
                                onChange={(e) => {
                                    clearClientError('customer_email');
                                    setData('customer_email', e.target.value);
                                }}
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
                                label={t.address_1}
                                name="address_line_1"
                                className="sm:col-span-2"
                                required
                                value={data.address_line_1}
                                error={fieldError('address_line_1')}
                                onChange={(e) =>
                                    setData('address_line_1', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.address_2}
                                name="address_line_2"
                                className="sm:col-span-2"
                                value={data.address_line_2}
                                error={fieldError('address_line_2')}
                                onChange={(e) =>
                                    setData('address_line_2', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.city}
                                name="city"
                                required
                                value={data.city}
                                error={fieldError('city')}
                                onChange={(e) =>
                                    setData('city', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.postcode}
                                name="postcode"
                                required
                                value={data.postcode}
                                error={fieldError('postcode')}
                                onChange={(e) =>
                                    setData('postcode', e.target.value)
                                }
                            />
                            <CountrySelect
                                label={t.country}
                                name="country"
                                required
                                value={data.country}
                                error={fieldError('country')}
                                onChange={(e) =>
                                    setData('country', e.target.value)
                                }
                            />
                            <FormInput
                                label={
                                    translations.location
                                        ?.cadastral_reference ??
                                    t.input_cadastral_reference
                                }
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
                            <Select
                                label={t.property_type}
                                name="property_type"
                                required
                                value={data.property_type}
                                error={fieldError('property_type')}
                                placeholder={common.all}
                                options={[
                                    {
                                        value: 'detached',
                                        label:
                                            t.property_type_detached ??
                                            'detached',
                                    },
                                    {
                                        value: 'semi_detached',
                                        label:
                                            t.property_type_semi_detached ??
                                            'semi_detached',
                                    },
                                    {
                                        value: 'terrace',
                                        label:
                                            t.property_type_terrace ??
                                            'terrace',
                                    },
                                    {
                                        value: 'flat',
                                        label: t.property_type_flat ?? 'flat',
                                    },
                                ]}
                                onChange={(e) =>
                                    setData('property_type', e.target.value)
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
                            label={t.scheme}
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
                                });
                            }}
                        />

                        {!selectedScheme?.fields.some(isZoneField) &&
                            (selectedScheme?.zones.length ?? 0) > 0 && (
                                <Select
                                    label={common.zone}
                                    name="zone_id"
                                    required
                                    value={data.zone_id}
                                    error={fieldError('zone_id')}
                                    placeholder={
                                        common.all_zones ?? common.all
                                    }
                                    options={(selectedScheme?.zones ?? []).map(
                                        (zone) => ({
                                            value: String(zone.id),
                                            label: zone.code,
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

                        <Textarea
                            label={
                                notesRequired
                                    ? (t.technical_notes ?? t.notes)
                                    : t.notes
                            }
                            name="notes"
                            required={notesRequired}
                            value={data.notes}
                            error={fieldError('notes')}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </section>
                )}

                {step === 'evidence' && (
                    <section className="rml-card-compact space-y-3">
                        <div>
                            <h2 className="text-sm font-semibold text-rml-text">
                                {stepLabels.evidence}
                            </h2>
                            <p className="mt-1 text-xs text-rml-muted">
                                {t.evidence_hint}
                            </p>
                        </div>

                        {(lead?.evidence?.length ?? 0) > 0 && (
                            <ul className="divide-y divide-rml-border rounded-lg border border-rml-border text-sm">
                                {lead?.evidence.map((file) => (
                                    <li
                                        key={file.id}
                                        className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                                    >
                                        <span className="min-w-0 truncate">
                                            {file.original_name ??
                                                file.file_type}
                                        </span>
                                        <div className="flex shrink-0 items-center gap-2">
                                            {file.view_url && (
                                                <a
                                                    href={file.view_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-sm font-medium text-rml-primary hover:underline"
                                                >
                                                    {t.view_evidence ??
                                                        common.view}
                                                </a>
                                            )}
                                            {file.download_url && (
                                                <a
                                                    href={file.download_url}
                                                    className="text-sm font-medium text-rml-primary hover:underline"
                                                >
                                                    {t.download_evidence ??
                                                        common.download}
                                                </a>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <div className="space-y-3">
                            <FileUpload
                                label={t.evidence_photos}
                                required
                                multiple
                                accept="image/jpeg,image/png,image/webp,image/heic,.jpg,.jpeg,.png,.webp,.heic"
                                files={fileMetas(data.evidence_photos)}
                                error={fieldError('evidence_photos')}
                                onFilesSelected={(list) =>
                                    setData('evidence_photos', [
                                        ...data.evidence_photos,
                                        ...Array.from(list),
                                    ])
                                }
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
                                label={t.evidence_agreement}
                                required
                                multiple={false}
                                accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png"
                                files={
                                    data.evidence_agreement
                                        ? fileMetas([data.evidence_agreement])
                                        : []
                                }
                                error={fieldError('evidence_agreement')}
                                onFilesSelected={(list) =>
                                    setData(
                                        'evidence_agreement',
                                        list.item(0),
                                    )
                                }
                                onRemove={() =>
                                    setData('evidence_agreement', null)
                                }
                            />
                            <FileUpload
                                label={
                                    t.evidence_eligibility_optional ??
                                    t.evidence_eligibility
                                }
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
                                label={
                                    t.evidence_video_optional ??
                                    t.evidence_video
                                }
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
                                            {t.go_to_section}
                                        </Button>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        {missingItems.length > 0 && (
                            <Alert variant="warning">
                                <p className="font-medium">
                                    {t.submit_incomplete}
                                </p>
                                <p className="mt-1 text-sm">
                                    {t.missing_required}
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

                        <section className="rml-card-compact space-y-3">
                            <h3 className="text-sm font-semibold text-rml-text">
                                {t.section_declarations}
                            </h3>
                            <Checkbox
                                name="consent"
                                label={t.decl_consent}
                                checked={data.consent}
                                error={fieldError('consent')}
                                onChange={(e) =>
                                    setData('consent', e.target.checked)
                                }
                            />
                            <Checkbox
                                name="evidence_genuine"
                                label={t.decl_genuine}
                                checked={data.evidence_genuine}
                                error={fieldError('evidence_genuine')}
                                onChange={(e) =>
                                    setData(
                                        'evidence_genuine',
                                        e.target.checked,
                                    )
                                }
                            />
                            <Checkbox
                                name="info_accurate"
                                label={t.decl_accurate}
                                checked={data.info_accurate}
                                error={fieldError('info_accurate')}
                                onChange={(e) =>
                                    setData('info_accurate', e.target.checked)
                                }
                            />
                        </section>

                        <section className="rml-card-compact space-y-2 text-sm">
                            <h3 className="text-sm font-semibold text-rml-text">
                                {t.section_customer}
                            </h3>
                            <p>
                                {`${data.customer_first_name} ${data.customer_last_name}`.trim() ||
                                    '—'}
                            </p>
                            <p className="text-rml-muted">
                                {[data.customer_phone, data.customer_email]
                                    .filter(Boolean)
                                    .join(' · ') || '—'}
                            </p>
                            <h3 className="pt-2 text-sm font-semibold text-rml-text">
                                {t.section_property}
                            </h3>
                            <p>
                                {[
                                    data.address_line_1,
                                    data.city,
                                    data.postcode,
                                ]
                                    .filter(Boolean)
                                    .join(', ') || '—'}
                            </p>
                            <h3 className="pt-2 text-sm font-semibold text-rml-text">
                                {t.scheme}
                            </h3>
                            <p>{selectedScheme?.name ?? '—'}</p>
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
                                {t.save_draft}
                            </Button>
                            {lead && (
                                <Link
                                    href={route('seller.leads.show', lead.id)}
                                    className="inline-flex"
                                >
                                    <Button type="button" variant="ghost">
                                        {common.view}
                                    </Button>
                                </Link>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-2 sm:justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={processing || step === 'customer'}
                                onClick={goPrevious}
                            >
                                {t.previous ?? common.previous ?? 'Previous'}
                            </Button>
                            {step !== 'review' ? (
                                <Button
                                    type="button"
                                    disabled={processing}
                                    onClick={goNext}
                                >
                                    {t.next ?? common.next ?? 'Next'}
                                </Button>
                            ) : (
                                <Button
                                    type="submit"
                                    disabled={processing || !canSubmit}
                                    title={
                                        !canSubmit
                                            ? t.submit_incomplete
                                            : undefined
                                    }
                                >
                                    {t.submit}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
