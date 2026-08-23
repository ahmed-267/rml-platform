import {
    FormEvent,
    ReactNode,
    useEffect,
    useMemo,
    useState,
} from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Alert,
    BackLink,
    Button,
    EmptyState,
    Select,
    Stepper,
} from '@/Components/ui';
import { useScrollToFirstError } from '@/hooks/use-scroll-to-first-error';
import { formatMoney } from '@/lib/admin-helpers';
import { cn } from '@/lib/cn';
import type { PageProps } from '@/types';

type PurchaseType = 'lead' | 'package';
type StepId = 'select' | 'details' | 'buyer' | 'payment' | 'review';

type BuyerOption = {
    id: number;
    name: string;
    city: string | null;
    base_location?: string | null;
    status?: string | null;
    active_purchases?: number | null;
    buyer_user_count?: number | null;
};

type EvidenceStatus = {
    photos: number;
    agreement: boolean;
    label: string;
};

type EligibleLead = {
    id: number;
    lead_reference: string;
    status?: string | null;
    scheme: string | null;
    zone?: string | null;
    size_m2: number | null;
    seller_company: string | null;
    seller_agent?: string | null;
    buying_price: number | null;
    selling_price: number | null;
    expected_margin: number | null;
    customer_name: string | null;
    customer_phone: string | null;
    customer_email: string | null;
    customer_whatsapp: string | null;
    address: string | null;
    city: string | null;
    postcode: string | null;
    country: string | null;
    survey_status: string | null;
    catastro_status: string | null;
    cadastral_reference: string | null;
    evidence_status: EvidenceStatus;
    audit_status: string | null;
};

type PackageLeadSummary = {
    id: number;
    lead_reference: string;
    scheme: string | null;
    zone: string | null;
    size_m2: number | null;
    seller_company: string | null;
    buying_price: number | null;
    selling_price: number | null;
    expected_margin: number | null;
    customer_name: string | null;
    address: string | null;
    city: string | null;
};

type PackageOption = {
    id: number;
    package_reference: string;
    name: string;
    status: string | null;
    lead_count: number;
    estimated_total: number | null;
    total_size_m2: number | null;
    total_selling_price: number | null;
    total_buying_price: number | null;
    expected_margin: number | null;
    schemes: string[];
    zones: string[];
    buyer_company_id: number | null;
    leads: PackageLeadSummary[];
};

const STEPS: StepId[] = ['select', 'details', 'buyer', 'payment', 'review'];

function asList<T>(value: T[] | Record<string, T> | null | undefined): T[] {
    if (Array.isArray(value)) {
        return value;
    }
    if (value && typeof value === 'object') {
        return Object.values(value);
    }
    return [];
}

function requiredMsg(field: string, template?: string): string {
    return (template ?? ':field is required.').replace(':field', field);
}

function humanize(value?: string | null): string | null {
    if (!value) {
        return null;
    }
    return value.replaceAll('_', ' ').replace(/^\w/, (c) => c.toUpperCase());
}

function DetailField({
    label,
    value,
    mono = false,
}: {
    label: string;
    value: ReactNode;
    mono?: boolean;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-rml-muted">{label}</dt>
            <dd
                className={cn(
                    'mt-0.5 text-sm text-rml-text break-words',
                    mono && 'font-mono',
                )}
            >
                {value ?? '—'}
            </dd>
        </div>
    );
}

function SummaryRow({
    label,
    value,
    emphasize = false,
}: {
    label: string;
    value: ReactNode;
    emphasize?: boolean;
}) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-rml-border/70 py-2 last:border-0">
            <dt className="text-sm text-rml-muted">{label}</dt>
            <dd
                className={cn(
                    'text-right text-sm text-rml-text',
                    emphasize && 'font-semibold',
                )}
            >
                {value ?? '—'}
            </dd>
        </div>
    );
}

export default function AdminBuyAsBuyerCreate({
    type: initialType,
    preselected,
    preselect_invalid = false,
    back_href,
    buyers: buyersProp,
    eligible_leads: eligibleLeadsProp,
    packages: packagesProp,
    payment_methods,
    card_configured = false,
}: {
    type: PurchaseType;
    preselected: { lead_id: number | null; package_id: number | null };
    preselect_invalid?: boolean;
    back_href?: string;
    buyers: BuyerOption[];
    eligible_leads: EligibleLead[];
    packages: PackageOption[];
    payment_methods: string[];
    card_configured?: boolean;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = (translations.admin as Record<string, any>)?.sales ?? {};
    const common = translations.admin?.common ?? ({} as Record<string, string>);
    const lb = (translations.admin as Record<string, any>)?.leads_bought ?? {};

    const buyers = useMemo(() => asList(buyersProp), [buyersProp]);
    const eligible_leads = useMemo(
        () => asList(eligibleLeadsProp),
        [eligibleLeadsProp],
    );
    const packages = useMemo(() => asList(packagesProp), [packagesProp]);

    const safeBackHref =
        back_href || route('admin.leads.index', { tab: 'registered' });

    const hasValidPreselect =
        !preselect_invalid &&
        Boolean(preselected.lead_id || preselected.package_id);

    const initialStep: StepId = preselect_invalid
        ? 'select'
        : hasValidPreselect
          ? 'details'
          : 'select';

    const [step, setStep] = useState<StepId>(initialStep);
    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );
    const [stepAlert, setStepAlert] = useState<string | null>(
        preselect_invalid
            ? (t.preselect_invalid ??
                  t.not_eligible_reason ??
                  'The selected lead or package is no longer eligible. Choose another.')
            : null,
    );

    const form = useForm({
        type: initialType,
        buyer_company_id: '',
        payment_method:
            card_configured && payment_methods.includes('card')
                ? 'card'
                : (payment_methods[0] ?? 'manual_bank_transfer'),
        lead_ids: preselected.lead_id ? [preselected.lead_id] : ([] as number[]),
        package_id: preselected.package_id
            ? String(preselected.package_id)
            : '',
    });

    useScrollToFirstError({
        ...clientErrors,
        ...(form.errors as Record<string, string | undefined>),
    });

    useEffect(() => {
        if (!preselect_invalid) {
            return;
        }
        setStep('select');
        setStepAlert(
            t.preselect_invalid ??
                t.not_eligible_reason ??
                'The selected lead or package is no longer eligible. Choose another.',
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [preselect_invalid]);

    const selectedLead = useMemo(
        () =>
            eligible_leads.find((lead) =>
                form.data.lead_ids.includes(lead.id),
            ) ?? null,
        [eligible_leads, form.data.lead_ids],
    );

    const selectedPackage = useMemo(
        () =>
            packages.find((p) => String(p.id) === form.data.package_id) ?? null,
        [packages, form.data.package_id],
    );

    const selectedBuyer = useMemo(
        () =>
            buyers.find(
                (b) => String(b.id) === form.data.buyer_company_id,
            ) ?? null,
        [buyers, form.data.buyer_company_id],
    );

    const purchaseTotal = useMemo(() => {
        if (form.data.type === 'package') {
            return (
                selectedPackage?.estimated_total ??
                selectedPackage?.total_selling_price ??
                0
            );
        }
        return selectedLead?.selling_price ?? 0;
    }, [form.data.type, selectedLead, selectedPackage]);

    const stepLabels: Record<StepId, string> = {
        select:
            form.data.type === 'package'
                ? (t.step_select_package ?? t.step_select ?? 'Select package')
                : (t.step_select_lead ?? t.step_select ?? 'Select lead'),
        details: t.step_details ?? t.purchase_details ?? 'Purchase details',
        buyer: t.step_buyer ?? t.select_buyer_company ?? 'Select buyer company',
        payment: t.step_payment ?? t.payment_method ?? 'Payment method',
        review:
            t.step_review ??
            t.review_confirm ??
            'Review & confirm purchase',
    };

    const methodLabel = (method: string) =>
        t[method] ?? t[`method_${method}`] ?? method.replaceAll('_', ' ');

    const statusLabel = (status?: string | null) => {
        if (!status) {
            return t.status_approved ?? 'Approved';
        }
        return t[`status_${status}`] ?? humanize(status);
    };

    const fieldError = (key: string): string | undefined =>
        clientErrors[key] ??
        (form.errors as Record<string, string | undefined>)[key];

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

    const validateSelect = (): Record<string, string> => {
        const next: Record<string, string> = {};
        if (form.data.type === 'lead') {
            if (form.data.lead_ids.length !== 1) {
                next.lead_ids = requiredMsg(t.lead ?? 'Lead', t.field_required);
            } else if (
                !eligible_leads.some(
                    (lead) => lead.id === form.data.lead_ids[0],
                )
            ) {
                next.lead_ids =
                    t.lead_not_eligible ??
                    t.not_eligible ??
                    'Selected lead is not eligible to buy.';
            }
        } else if (!form.data.package_id) {
            next.package_id = requiredMsg(
                t.package ?? 'Package',
                t.field_required,
            );
        } else if (
            !packages.some(
                (pkg) => String(pkg.id) === String(form.data.package_id),
            )
        ) {
            next.package_id =
                t.package_not_eligible ??
                t.package_unavailable ??
                'Selected package is not available to buy.';
        }
        return next;
    };

    const validateBuyer = (): Record<string, string> => {
        const next: Record<string, string> = {};
        if (!form.data.buyer_company_id) {
            next.buyer_company_id = requiredMsg(
                t.buyer_company ?? t.buyer ?? 'Buyer company',
                t.field_required,
            );
        } else if (
            !buyers.some(
                (buyer) => String(buyer.id) === form.data.buyer_company_id,
            )
        ) {
            next.buyer_company_id =
                t.buyer_invalid ??
                'Selected buyer company is not valid for this purchase.';
        }
        return next;
    };

    const validatePayment = (): Record<string, string> => {
        const next: Record<string, string> = {};
        if (!form.data.payment_method) {
            next.payment_method = requiredMsg(
                t.payment_method ?? 'Payment method',
                t.field_required,
            );
        } else if (!payment_methods.includes(form.data.payment_method)) {
            next.payment_method =
                t.payment_method_invalid ?? 'Select a valid payment method.';
        }
        return next;
    };

    const validateStep = (target: StepId): Record<string, string> => {
        switch (target) {
            case 'select':
            case 'details':
                return validateSelect();
            case 'buyer':
                return validateBuyer();
            case 'payment':
                return validatePayment();
            case 'review':
                return {
                    ...validateSelect(),
                    ...validateBuyer(),
                    ...validatePayment(),
                };
            default:
                return {};
        }
    };

    const stepComplete: Record<StepId, boolean> = {
        select: Object.keys(validateSelect()).length === 0,
        details: Object.keys(validateSelect()).length === 0,
        buyer: Object.keys(validateBuyer()).length === 0,
        payment: Object.keys(validatePayment()).length === 0,
        review: false,
    };

    const activeStepValid = Object.keys(validateStep(step)).length === 0;

    const errorStep = (keys: string[]): StepId | null => {
        if (keys.some((key) => ['lead_ids', 'package_id', 'type'].includes(key))) {
            return 'select';
        }
        if (keys.some((key) => key === 'buyer_company_id')) {
            return 'buyer';
        }
        if (keys.some((key) => key === 'payment_method')) {
            return 'payment';
        }
        return null;
    };

    const scrollToFirstError = (errors: Record<string, string>) => {
        window.setTimeout(() => {
            const firstKey = Object.keys(errors)[0];
            const el = document.querySelector(
                `[name="${firstKey}"], [data-error-field="${firstKey}"]`,
            ) as HTMLElement | null;
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el?.focus?.({ preventScroll: true });
        }, 50);
    };

    const goNext = () => {
        const nextErrors = validateStep(step);
        if (Object.keys(nextErrors).length > 0) {
            setClientErrors(nextErrors);
            setStepAlert(Object.values(nextErrors)[0] ?? null);
            scrollToFirstError(nextErrors);
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
        setClientErrors({});
        setStepAlert(null);
        const index = STEPS.indexOf(step);
        if (index > 0) {
            setStep(STEPS[index - 1]);
        }
    };

    const selectBuyer = (buyerId: number) => {
        clearClientError('buyer_company_id');
        form.setData('buyer_company_id', String(buyerId));
    };

    const selectLead = (leadId: number) => {
        clearClientError('lead_ids');
        form.setData('lead_ids', [leadId]);
    };

    const setPurchaseType = (nextType: PurchaseType) => {
        clearClientError('lead_ids');
        clearClientError('package_id');
        form.setData({
            ...form.data,
            type: nextType,
            lead_ids:
                nextType === 'lead' && preselected.lead_id
                    ? [preselected.lead_id]
                    : nextType === 'lead'
                      ? form.data.lead_ids
                      : [],
            package_id:
                nextType === 'package' && preselected.package_id
                    ? String(preselected.package_id)
                    : nextType === 'package'
                      ? form.data.package_id
                      : '',
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const nextErrors = validateStep('review');
        if (Object.keys(nextErrors).length > 0) {
            setClientErrors(nextErrors);
            setStepAlert(Object.values(nextErrors)[0] ?? null);
            const target = errorStep(Object.keys(nextErrors));
            if (target) {
                setStep(target);
            }
            scrollToFirstError(nextErrors);
            return;
        }

        form.transform((data) => ({
            type: data.type,
            buyer_company_id: Number(data.buyer_company_id),
            payment_method: data.payment_method,
            package_id:
                data.type === 'package' && data.package_id
                    ? Number(data.package_id)
                    : null,
            lead_ids: data.type === 'lead' ? data.lead_ids : [],
        }));
        form.post(route('admin.sales.store'));
    };

    const evidenceLabel = (evidence: EvidenceStatus) => {
        const label =
            t[`evidence_${evidence.label}`] ?? humanize(evidence.label);
        const parts = [
            `${evidence.photos} ${t.photos ?? 'photos'}`,
            evidence.agreement
                ? (t.agreement_present ?? 'Agreement present')
                : (t.agreement_missing ?? 'No agreement'),
            label,
        ];
        return parts.filter(Boolean).join(' · ');
    };

    const LeadDetailsPanel = ({ lead }: { lead: EligibleLead }) => (
        <div className="rounded-xl border border-rml-border bg-rml-background/40 p-4">
            <h3 className="mb-3 font-mono text-sm font-semibold text-rml-text">
                {lead.lead_reference}
            </h3>
            <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <DetailField
                    label={t.status ?? common.status ?? 'Status'}
                    value={humanize(lead.status)}
                />
                <DetailField
                    label={common.scheme ?? lb.scheme ?? 'Scheme'}
                    value={lead.scheme}
                />
                <DetailField
                    label={common.zone ?? 'Zone'}
                    value={lead.zone}
                />
                <DetailField
                    label={common.size ?? 'Size'}
                    value={
                        lead.size_m2 != null ? `${lead.size_m2} m²` : null
                    }
                />
                <DetailField
                    label={t.seller_company ?? lb.seller ?? 'Seller company'}
                    value={lead.seller_company}
                />
                <DetailField
                    label={t.seller_agent ?? 'Seller agent'}
                    value={lead.seller_agent}
                />
                <DetailField
                    label={t.buying_price ?? lb.buying_price ?? 'Buying price'}
                    value={formatMoney(lead.buying_price)}
                />
                <DetailField
                    label={t.selling_price ?? lb.selling_price ?? 'Selling price'}
                    value={formatMoney(lead.selling_price)}
                />
                <DetailField
                    label={t.expected_margin ?? lb.expected_margin ?? 'Expected margin'}
                    value={formatMoney(lead.expected_margin)}
                />
                <DetailField
                    label={t.customer_name ?? lb.customer ?? 'Customer name'}
                    value={lead.customer_name}
                />
                <DetailField
                    label={t.customer_phone ?? 'Customer phone'}
                    value={lead.customer_phone}
                />
                <DetailField
                    label={t.customer_email ?? 'Customer email'}
                    value={lead.customer_email}
                />
                <DetailField
                    label={t.customer_whatsapp ?? 'Customer WhatsApp'}
                    value={lead.customer_whatsapp}
                />
                <DetailField
                    label={t.address ?? lb.address ?? 'Address'}
                    value={lead.address}
                />
                <DetailField label={t.city ?? 'City'} value={lead.city} />
                <DetailField
                    label={t.postcode ?? 'Postcode'}
                    value={lead.postcode}
                />
                <DetailField
                    label={t.country ?? 'Country'}
                    value={lead.country}
                />
                <DetailField
                    label={t.survey_status ?? 'Survey status'}
                    value={humanize(lead.survey_status)}
                />
                <DetailField
                    label={t.catastro_status ?? 'Catastro status'}
                    value={humanize(lead.catastro_status)}
                />
                <DetailField
                    label={t.cadastral_reference ?? 'Cadastral reference'}
                    value={lead.cadastral_reference}
                    mono
                />
                <DetailField
                    label={t.evidence_status ?? 'Evidence'}
                    value={evidenceLabel(lead.evidence_status)}
                />
                <DetailField
                    label={t.audit_status ?? 'Audit status'}
                    value={humanize(lead.audit_status)}
                />
            </dl>
        </div>
    );

    const PackageDetailsPanel = ({ pkg }: { pkg: PackageOption }) => (
        <div className="space-y-4">
            <div className="rounded-xl border border-rml-border bg-rml-background/40 p-4">
                <h3 className="mb-3 text-sm font-semibold text-rml-text">
                    {pkg.package_reference}
                    {pkg.name ? ` — ${pkg.name}` : ''}
                </h3>
                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <DetailField
                        label={t.status ?? common.status ?? 'Status'}
                        value={humanize(pkg.status)}
                    />
                    <DetailField
                        label={t.lead_count ?? 'Leads'}
                        value={pkg.lead_count}
                    />
                    <DetailField
                        label={t.total_size ?? 'Total size'}
                        value={
                            pkg.total_size_m2 != null
                                ? `${pkg.total_size_m2} m²`
                                : null
                        }
                    />
                    <DetailField
                        label={t.schemes ?? 'Schemes'}
                        value={
                            pkg.schemes.length > 0
                                ? pkg.schemes.join(', ')
                                : null
                        }
                    />
                    <DetailField
                        label={t.zones ?? 'Zones'}
                        value={
                            pkg.zones.length > 0 ? pkg.zones.join(', ') : null
                        }
                    />
                    <DetailField
                        label={t.total_buying_price ?? 'Total buying price'}
                        value={formatMoney(pkg.total_buying_price)}
                    />
                    <DetailField
                        label={t.total_selling_price ?? 'Total selling price'}
                        value={formatMoney(pkg.total_selling_price)}
                    />
                    <DetailField
                        label={t.expected_margin ?? 'Expected margin'}
                        value={formatMoney(pkg.expected_margin)}
                    />
                    <DetailField
                        label={t.estimated_total ?? t.purchase_price ?? 'Estimated total'}
                        value={formatMoney(pkg.estimated_total)}
                    />
                </dl>
            </div>

            <div>
                <h4 className="mb-2 text-sm font-semibold text-rml-text">
                    {t.included_leads ?? 'Included leads'}
                </h4>
                {pkg.leads.length === 0 ? (
                    <p className="text-sm text-rml-muted">
                        {t.no_leads_in_package ?? 'No leads in this package.'}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-rml-border">
                        <table className="min-w-full divide-y divide-rml-border text-left text-sm">
                            <thead className="bg-rml-background">
                                <tr>
                                    <th className="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-rml-muted">
                                        {t.lead ?? 'Lead'}
                                    </th>
                                    <th className="hidden px-3 py-2 text-xs font-semibold uppercase tracking-wide text-rml-muted sm:table-cell">
                                        {common.scheme ?? 'Scheme'}
                                    </th>
                                    <th className="hidden px-3 py-2 text-xs font-semibold uppercase tracking-wide text-rml-muted md:table-cell">
                                        {common.zone ?? 'Zone'}
                                    </th>
                                    <th className="hidden px-3 py-2 text-xs font-semibold uppercase tracking-wide text-rml-muted lg:table-cell">
                                        {t.customer_name ?? 'Customer'}
                                    </th>
                                    <th className="hidden px-3 py-2 text-xs font-semibold uppercase tracking-wide text-rml-muted lg:table-cell">
                                        {t.address ?? 'Address'}
                                    </th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-rml-muted">
                                        {t.selling_price ?? 'Price'}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-rml-border bg-white">
                                {pkg.leads.map((lead) => (
                                    <tr key={lead.id}>
                                        <td className="px-3 py-2">
                                            <span className="block font-mono font-medium text-rml-text">
                                                {lead.lead_reference}
                                            </span>
                                            <span className="mt-0.5 block text-xs text-rml-muted sm:hidden">
                                                {[
                                                    lead.scheme,
                                                    lead.zone,
                                                    lead.size_m2 != null
                                                        ? `${lead.size_m2} m²`
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </span>
                                        </td>
                                        <td className="hidden px-3 py-2 text-rml-text sm:table-cell">
                                            {lead.scheme ?? '—'}
                                        </td>
                                        <td className="hidden px-3 py-2 text-rml-text md:table-cell">
                                            {lead.zone ?? '—'}
                                        </td>
                                        <td className="hidden px-3 py-2 text-rml-text lg:table-cell">
                                            {lead.customer_name ?? '—'}
                                        </td>
                                        <td className="hidden px-3 py-2 text-rml-text lg:table-cell">
                                            {[lead.address, lead.city]
                                                .filter(Boolean)
                                                .join(', ') || '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right font-medium text-rml-text">
                                            {formatMoney(lead.selling_price)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );

    const pageTitle =
        form.data.type === 'package'
            ? (t.buy_package_title ?? t.buy_package ?? 'Buy package')
            : (t.buy_lead_title ?? t.buy_lead ?? 'Buy lead');

    return (
        <AppLayout
            title={pageTitle}
            subtitle={
                t.create_subtitle ??
                t.buy_on_behalf ??
                'Buy on behalf of buyer company'
            }
        >
            <Head title={pageTitle} />

            <div className="mb-1">
                <BackLink
                    href={safeBackHref}
                    label={common.back ?? 'Back'}
                    showLabel
                    useHistory={false}
                />
            </div>

            <form onSubmit={submit} className="rml-page-stack mt-4 space-y-4">
                {stepAlert && (
                    <div data-validation-summary>
                        <Alert
                            variant="error"
                            title={t.validation_title ?? 'Check required fields'}
                        >
                            {stepAlert}
                        </Alert>
                    </div>
                )}

                <Alert variant="info" title={t.buy_as_buyer ?? 'Buy as buyer'}>
                    {t.buy_as_buyer_help ??
                        'This uses the same Buyer Portal purchase and payment logic. Customer details stay locked until payment is confirmed.'}
                </Alert>

                <Stepper
                    current={step}
                    onChange={(id) => {
                        const target = id as StepId;
                        const currentIndex = STEPS.indexOf(step);
                        const targetIndex = STEPS.indexOf(target);
                        if (targetIndex <= currentIndex) {
                            setStep(target);
                            setStepAlert(null);
                            return;
                        }
                        for (let i = currentIndex; i < targetIndex; i += 1) {
                            const errors = validateStep(STEPS[i]);
                            if (Object.keys(errors).length > 0) {
                                setClientErrors(errors);
                                setStepAlert(Object.values(errors)[0] ?? null);
                                setStep(STEPS[i]);
                                scrollToFirstError(errors);
                                return;
                            }
                        }
                        setClientErrors({});
                        setStepAlert(null);
                        setStep(target);
                    }}
                    steps={STEPS.map((id) => ({
                        id,
                        label: stepLabels[id],
                        complete: stepComplete[id],
                    }))}
                />

                {step === 'select' && (
                    <section className="rml-card space-y-4 p-4 sm:p-5">
                        <div>
                            <h2 className="text-sm font-semibold text-rml-text">
                                {stepLabels.select}
                            </h2>
                            <p className="mt-1 text-sm text-rml-muted">
                                {t.select_help ??
                                    'Choose an eligible listed lead or available package.'}
                            </p>
                        </div>

                        <div data-error-field="type">
                            <p className="mb-2 text-sm font-medium text-rml-text">
                                {t.purchase_type ?? 'Purchase type'}
                                <span className="ml-0.5 text-rml-red">*</span>
                            </p>
                            <ul className="grid gap-2 sm:grid-cols-2">
                                {(
                                    [
                                        {
                                            value: 'lead' as const,
                                            title:
                                                t.type_lead ??
                                                t.buy_lead ??
                                                'Buy lead',
                                            description:
                                                t.type_lead_help ??
                                                'Purchase a single marketplace lead.',
                                        },
                                        {
                                            value: 'package' as const,
                                            title:
                                                t.type_package ??
                                                t.buy_package ??
                                                'Buy package',
                                            description:
                                                t.type_package_help ??
                                                'Purchase a pre-built lead package.',
                                        },
                                    ] as const
                                ).map((option) => {
                                    const checked =
                                        form.data.type === option.value;
                                    return (
                                        <li key={option.value}>
                                            <label
                                                className={cn(
                                                    'flex h-full cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition-colors',
                                                    checked
                                                        ? 'border-rml-primary bg-rml-primary-lighter/40'
                                                        : 'border-rml-border hover:border-rml-primary/40 hover:bg-rml-background',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name="type"
                                                    className="mt-1"
                                                    checked={checked}
                                                    onChange={() =>
                                                        setPurchaseType(
                                                            option.value,
                                                        )
                                                    }
                                                />
                                                <span className="min-w-0">
                                                    <span className="block text-sm font-semibold text-rml-text">
                                                        {option.title}
                                                    </span>
                                                    <span className="mt-1 block text-xs text-rml-muted">
                                                        {option.description}
                                                    </span>
                                                </span>
                                            </label>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>

                        {form.data.type === 'lead' ? (
                            <div data-error-field="lead_ids">
                                <label className="mb-2 block text-sm font-medium text-rml-text">
                                    {t.select_lead ?? t.lead ?? 'Lead'}
                                    <span className="ml-0.5 text-rml-red">*</span>
                                </label>

                                {eligible_leads.length === 0 ? (
                                    <EmptyState
                                        title={
                                            t.no_eligible_leads ??
                                            'No eligible leads'
                                        }
                                        description={
                                            t.no_eligible_leads_help ??
                                            'There are no marketplace-ready leads available to buy right now.'
                                        }
                                    />
                                ) : (
                                    <ul className="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                                        {eligible_leads.map((lead) => {
                                            const checked =
                                                form.data.lead_ids.includes(
                                                    lead.id,
                                                );
                                            return (
                                                <li key={lead.id}>
                                                    <label
                                                        className={cn(
                                                            'flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition-colors',
                                                            checked
                                                                ? 'border-rml-primary bg-rml-primary-lighter/40'
                                                                : 'border-rml-border hover:border-rml-primary/40 hover:bg-rml-background',
                                                        )}
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="lead_ids"
                                                            className="mt-1"
                                                            checked={checked}
                                                            onChange={() =>
                                                                selectLead(
                                                                    lead.id,
                                                                )
                                                            }
                                                        />
                                                        <span className="min-w-0 flex-1">
                                                            <span className="flex flex-wrap items-center gap-2">
                                                                <span className="font-mono text-sm font-semibold text-rml-text">
                                                                    {
                                                                        lead.lead_reference
                                                                    }
                                                                </span>
                                                                {lead.zone && (
                                                                    <span className="rounded-md bg-white px-1.5 py-0.5 text-xs text-rml-muted ring-1 ring-rml-border">
                                                                        {
                                                                            lead.zone
                                                                        }
                                                                    </span>
                                                                )}
                                                            </span>
                                                            <span className="mt-1 block text-xs text-rml-muted">
                                                                {[
                                                                    lead.scheme,
                                                                    lead.city,
                                                                    lead.size_m2 !=
                                                                    null
                                                                        ? `${lead.size_m2} m²`
                                                                        : null,
                                                                    lead.customer_name,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(' · ')}
                                                            </span>
                                                        </span>
                                                        <span className="shrink-0 text-sm font-medium text-rml-text">
                                                            {formatMoney(
                                                                lead.selling_price,
                                                            )}
                                                        </span>
                                                    </label>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}

                                {fieldError('lead_ids') && (
                                    <p className="mt-2 text-sm text-red-600">
                                        {fieldError('lead_ids')}
                                    </p>
                                )}
                            </div>
                        ) : (
                            <div data-error-field="package_id">
                                <label className="mb-2 block text-sm font-medium text-rml-text">
                                    {t.select_package ??
                                        t.package ??
                                        'Package'}
                                    <span className="ml-0.5 text-rml-red">*</span>
                                </label>

                                {packages.length === 0 ? (
                                    <EmptyState
                                        title={
                                            t.no_eligible_packages ??
                                            'No available packages'
                                        }
                                        description={
                                            t.no_eligible_packages_help ??
                                            'Create or open a package before buying for a buyer.'
                                        }
                                    />
                                ) : (
                                    <ul className="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                                        {packages.map((pkg) => {
                                            const checked =
                                                form.data.package_id ===
                                                String(pkg.id);
                                            return (
                                                <li key={pkg.id}>
                                                    <label
                                                        className={cn(
                                                            'flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition-colors',
                                                            checked
                                                                ? 'border-rml-primary bg-rml-primary-lighter/40'
                                                                : 'border-rml-border hover:border-rml-primary/40 hover:bg-rml-background',
                                                        )}
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="package_id"
                                                            className="mt-1"
                                                            checked={checked}
                                                            onChange={() => {
                                                                clearClientError(
                                                                    'package_id',
                                                                );
                                                                form.setData(
                                                                    'package_id',
                                                                    String(
                                                                        pkg.id,
                                                                    ),
                                                                );
                                                            }}
                                                        />
                                                        <span className="min-w-0 flex-1">
                                                            <span className="font-mono text-sm font-semibold text-rml-text">
                                                                {
                                                                    pkg.package_reference
                                                                }
                                                            </span>
                                                            <span className="mt-1 block text-xs text-rml-muted">
                                                                {[
                                                                    pkg.name,
                                                                    `${pkg.lead_count} ${t.leads ?? 'leads'}`,
                                                                    humanize(
                                                                        pkg.status,
                                                                    ),
                                                                    pkg.schemes.length >
                                                                    0
                                                                        ? pkg.schemes.join(
                                                                              ', ',
                                                                          )
                                                                        : null,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(' · ')}
                                                            </span>
                                                        </span>
                                                        <span className="shrink-0 text-sm font-medium text-rml-text">
                                                            {formatMoney(
                                                                pkg.estimated_total,
                                                            )}
                                                        </span>
                                                    </label>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}

                                {fieldError('package_id') && (
                                    <p className="mt-2 text-sm text-red-600">
                                        {fieldError('package_id')}
                                    </p>
                                )}
                            </div>
                        )}
                    </section>
                )}

                {step === 'details' && (
                    <section className="rml-card space-y-4 p-4 sm:p-5">
                        <div>
                            <h2 className="text-sm font-semibold text-rml-text">
                                {stepLabels.details}
                            </h2>
                            <p className="mt-1 text-sm text-rml-muted">
                                {t.details_intro ??
                                    'Review full lead or package details before continuing. Admin view includes customer PII.'}
                            </p>
                        </div>

                        {form.data.type === 'lead' && selectedLead ? (
                            <LeadDetailsPanel lead={selectedLead} />
                        ) : form.data.type === 'package' && selectedPackage ? (
                            <PackageDetailsPanel pkg={selectedPackage} />
                        ) : (
                            <EmptyState
                                title={
                                    t.no_selection ??
                                    'No lead or package selected'
                                }
                                description={
                                    t.no_selection_help ??
                                    'Go back and select a lead or package to continue.'
                                }
                            />
                        )}
                    </section>
                )}

                {step === 'buyer' && (
                    <section className="rml-card space-y-4 p-4 sm:p-5">
                        <div>
                            <h2 className="text-sm font-semibold text-rml-text">
                                {stepLabels.buyer}
                            </h2>
                            <p className="mt-1 text-sm text-rml-muted">
                                {t.buyer_company_help ??
                                    t.buy_on_behalf ??
                                    'Buy on behalf of buyer company'}
                            </p>
                        </div>

                        <div data-error-field="buyer_company_id">
                            <label className="mb-2 block text-sm font-medium text-rml-text">
                                {t.select_buyer_company ??
                                    t.buyer_company ??
                                    'Select buyer company'}
                                <span className="ml-0.5 text-rml-red">*</span>
                            </label>

                            {buyers.length === 0 ? (
                                <EmptyState
                                    title={
                                        t.no_buyers ?? 'No approved buyers'
                                    }
                                    description={
                                        t.no_buyers_help ??
                                        'Approve a buyer company before buying on their behalf.'
                                    }
                                />
                            ) : (
                                <ul className="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                                    {buyers.map((buyer) => {
                                        const checked =
                                            form.data.buyer_company_id ===
                                            String(buyer.id);
                                        return (
                                            <li key={buyer.id}>
                                                <label
                                                    className={cn(
                                                        'flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition-colors',
                                                        checked
                                                            ? 'border-rml-primary bg-rml-primary-lighter/40'
                                                            : 'border-rml-border hover:border-rml-primary/40 hover:bg-rml-background',
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="buyer_company_id"
                                                        className="mt-1"
                                                        checked={checked}
                                                        onChange={() =>
                                                            selectBuyer(
                                                                buyer.id,
                                                            )
                                                        }
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="flex flex-wrap items-center gap-2">
                                                            <span className="text-sm font-semibold text-rml-text">
                                                                {buyer.name}
                                                            </span>
                                                            <span className="rounded-md bg-white px-1.5 py-0.5 text-xs text-rml-muted ring-1 ring-rml-border">
                                                                {statusLabel(
                                                                    buyer.status,
                                                                )}
                                                            </span>
                                                        </span>
                                                        <span className="mt-1 block text-xs text-rml-muted">
                                                            {[
                                                                buyer.base_location ||
                                                                    buyer.city,
                                                                buyer.active_purchases !=
                                                                null
                                                                    ? `${buyer.active_purchases} ${t.active_purchases ?? 'active purchases'}`
                                                                    : null,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </span>
                                                    </span>
                                                </label>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}

                            {fieldError('buyer_company_id') && (
                                <p className="mt-2 text-sm text-red-600">
                                    {fieldError('buyer_company_id')}
                                </p>
                            )}
                        </div>
                    </section>
                )}

                {step === 'payment' && (
                    <section className="rml-card space-y-4 p-4 sm:p-5">
                        <div>
                            <h2 className="text-sm font-semibold text-rml-text">
                                {stepLabels.payment}
                            </h2>
                            <p className="mt-1 text-sm text-rml-muted">
                                {t.payment_intro ??
                                    'Choose how this purchase will be paid.'}
                            </p>
                        </div>

                        <Select
                            label={t.payment_method ?? 'Payment method'}
                            name="payment_method"
                            required
                            value={form.data.payment_method}
                            error={fieldError('payment_method')}
                            onChange={(e) => {
                                clearClientError('payment_method');
                                form.setData(
                                    'payment_method',
                                    e.target.value,
                                );
                            }}
                            options={payment_methods.map((method) => ({
                                label: methodLabel(method),
                                value: method,
                                disabled:
                                    method === 'card' && !card_configured,
                            }))}
                        />

                        {!card_configured &&
                            payment_methods.includes('card') && (
                                <Alert
                                    variant="warning"
                                    title={
                                        t.card_not_configured_title ??
                                        'Card payments unavailable'
                                    }
                                >
                                    {t.card_not_configured ??
                                        'Card payments are not configured. Choose bank transfer or configure Stripe in settings.'}
                                </Alert>
                            )}
                    </section>
                )}

                {step === 'review' && (
                    <section className="rml-card space-y-4 p-4 sm:p-5">
                        <div>
                            <h2 className="text-sm font-semibold text-rml-text">
                                {stepLabels.review}
                            </h2>
                            <p className="mt-1 text-sm text-rml-muted">
                                {t.review_intro ??
                                    'Review the purchase details, then confirm to create the purchase and pending payment.'}
                            </p>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <div className="rounded-xl border border-rml-border bg-rml-background/40 p-4">
                                <h3 className="mb-2 text-sm font-semibold text-rml-text">
                                    {t.purchase_summary ??
                                        t.sale_summary ??
                                        'Purchase summary'}
                                </h3>
                                <dl>
                                    {form.data.type === 'package' &&
                                    selectedPackage ? (
                                        <>
                                            <SummaryRow
                                                label={t.package ?? 'Package'}
                                                value={
                                                    <span className="font-mono font-medium">
                                                        {
                                                            selectedPackage.package_reference
                                                        }
                                                        {selectedPackage.name
                                                            ? ` — ${selectedPackage.name}`
                                                            : ''}
                                                    </span>
                                                }
                                                emphasize
                                            />
                                            <SummaryRow
                                                label={
                                                    t.lead_count ?? 'Leads'
                                                }
                                                value={
                                                    selectedPackage.lead_count
                                                }
                                            />
                                            <SummaryRow
                                                label={
                                                    t.total_size ?? 'Total size'
                                                }
                                                value={
                                                    selectedPackage.total_size_m2 !=
                                                    null
                                                        ? `${selectedPackage.total_size_m2} m²`
                                                        : null
                                                }
                                            />
                                        </>
                                    ) : selectedLead ? (
                                        <>
                                            <SummaryRow
                                                label={t.lead ?? 'Lead'}
                                                value={
                                                    <span className="font-mono font-medium">
                                                        {
                                                            selectedLead.lead_reference
                                                        }
                                                    </span>
                                                }
                                                emphasize
                                            />
                                            <SummaryRow
                                                label={
                                                    common.scheme ??
                                                    lb.scheme ??
                                                    'Scheme'
                                                }
                                                value={selectedLead.scheme}
                                            />
                                            <SummaryRow
                                                label={common.zone ?? 'Zone'}
                                                value={selectedLead.zone}
                                            />
                                            <SummaryRow
                                                label={common.size ?? 'Size'}
                                                value={
                                                    selectedLead.size_m2 != null
                                                        ? `${selectedLead.size_m2} m²`
                                                        : null
                                                }
                                            />
                                            <SummaryRow
                                                label={
                                                    t.customer_name ??
                                                    'Customer'
                                                }
                                                value={
                                                    selectedLead.customer_name
                                                }
                                            />
                                        </>
                                    ) : null}
                                    <SummaryRow
                                        label={
                                            t.purchase_price ??
                                            t.selling_price ??
                                            'Price'
                                        }
                                        value={formatMoney(purchaseTotal)}
                                        emphasize
                                    />
                                </dl>
                            </div>

                            <div className="rounded-xl border border-rml-border bg-rml-background/40 p-4">
                                <h3 className="mb-2 text-sm font-semibold text-rml-text">
                                    {t.buyer_and_payment ?? 'Buyer & payment'}
                                </h3>
                                <dl>
                                    <SummaryRow
                                        label={
                                            t.buyer_company ??
                                            t.buyer ??
                                            'Buyer company'
                                        }
                                        value={selectedBuyer?.name}
                                        emphasize
                                    />
                                    <SummaryRow
                                        label={t.location ?? 'Location'}
                                        value={
                                            selectedBuyer?.base_location ||
                                            selectedBuyer?.city
                                        }
                                    />
                                    <SummaryRow
                                        label={t.type ?? 'Purchase type'}
                                        value={
                                            form.data.type === 'package'
                                                ? (t.type_package ??
                                                  t.buy_package ??
                                                  'Buy package')
                                                : (t.type_lead ??
                                                  t.buy_lead ??
                                                  'Buy lead')
                                        }
                                    />
                                    <SummaryRow
                                        label={
                                            t.payment_method ??
                                            'Payment method'
                                        }
                                        value={methodLabel(
                                            form.data.payment_method,
                                        )}
                                    />
                                </dl>
                            </div>
                        </div>

                        <Alert variant="warning" title={t.privacy_title ?? 'Privacy'}>
                            {t.privacy_note ??
                                'Customer details stay locked until payment is confirmed. The buyer will only see full contact information after payment confirmation.'}
                        </Alert>
                    </section>
                )}

                <div className="sticky bottom-0 z-10 -mx-4 border-t border-rml-border bg-rml-background/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border sm:bg-white">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={form.processing || step === 'select'}
                            onClick={goPrevious}
                        >
                            {t.previous ?? common.back ?? 'Back'}
                        </Button>
                        <div className="flex flex-wrap gap-2">
                            {step !== 'review' ? (
                                <Button
                                    type="button"
                                    disabled={
                                        form.processing || !activeStepValid
                                    }
                                    title={
                                        !activeStepValid
                                            ? (t.step_incomplete ??
                                              'Complete required fields to continue')
                                            : undefined
                                    }
                                    onClick={goNext}
                                >
                                    {t.next ?? common.continue ?? 'Continue'}
                                </Button>
                            ) : (
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing || !activeStepValid
                                    }
                                >
                                    {form.processing
                                        ? (common.loading ?? 'Processing…')
                                        : (t.confirm_purchase ??
                                          t.confirm_sale ??
                                          'Confirm purchase')}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
