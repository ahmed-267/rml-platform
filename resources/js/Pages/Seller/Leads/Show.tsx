import { FormEvent } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Alert,
    BackLink,
    Button,
    EmptyState,
    FileUpload,
    StatusBadge,
} from '@/Components/ui';
import type { UploadedFileMeta } from '@/Components/ui/FileUpload';
import { CatastroSellerStatusCard } from '@/Components/catastro/CatastroSellerStatusCard';
import type { CatastroPanelPayload } from '@/Components/catastro/CatastroVerificationCard';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import {
    formatSellerMoney,
    sellerPayoutLabels,
    sellerPayoutStatusLabel,
    type SellerPayoutSummary,
} from '@/lib/seller-payout';
import type { PageProps } from '@/types';

interface EvidenceFile {
    id: number;
    file_type: string | null;
    original_name: string | null;
    mime_type: string | null;
    size: number | null;
    status: string | null;
    created_at: string | null;
    view_url?: string;
    download_url?: string;
    is_image?: boolean;
}

interface SellerLead {
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
    property_type: string | null;
    epc_rating: string | null;
    size_m2: number | null;
    notes: string | null;
    rejection_reason: string | null;
    rejection_comment?: string | null;
    requested_info: string | null;
    scheme: { id: number; name: string; slug: string } | null;
    zone: { id: number; code: string; name: string } | null;
    metrics: Record<string, unknown>;
    evidence: EvidenceFile[];
    payout?: SellerPayoutSummary | null;
    accepted_at: string | null;
    rejected_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

interface SchemeOption {
    id: number;
    name: string;
    slug: string;
}

interface EvidenceForm {
    evidence_photos: File[];
    evidence_video: File | null;
    evidence_agreement: File | null;
    evidence_eligibility: File[];
}

function fileMetas(files: File[]): UploadedFileMeta[] {
    return files.map((file, index) => ({
        id: `${file.name}-${file.size}-${index}`,
        name: file.name,
        sizeLabel: `${Math.round(file.size / 1024)} KB`,
    }));
}

function DetailRow({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div className="space-y-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                {label}
            </dt>
            <dd className="text-sm text-rml-text">{value ?? '—'}</dd>
        </div>
    );
}

interface SurveySummaryProp {
    exists: boolean;
    status: string;
    action: string;
    action_label: string;
    href: string | null;
    eligibility_status: string;
    can_conduct?: boolean;
    can_review?: boolean;
}

export default function SellerLeadsShow({
    lead,
    survey = null,
    catastro = null,
}: {
    lead: SellerLead;
    schemes: SchemeOption[];
    survey?: SurveySummaryProp | null;
    catastro?: CatastroPanelPayload | null;
    can_lookup_catastro?: boolean;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.seller.leads;
    const surveyT = translations.survey;
    const common = translations.seller.common;
    const payoutLabels = sellerPayoutLabels(translations);
    const leadStatuses = translations.lead_statuses;
    const statuses = translations.statuses;
    const payout = lead.payout;

    const canUploadMore =
        lead.status === 'needs_more_information' ||
        lead.status === 'pending_evidence';

    const { data, setData, post, processing, errors, reset } =
        useForm<EvidenceForm>({
            evidence_photos: [],
            evidence_video: null,
            evidence_agreement: null,
            evidence_eligibility: [],
        });

    const submitEvidence = (event: FormEvent) => {
        event.preventDefault();
        post(route('seller.leads.evidence', lead.id), {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    };

    const formatDate = (value: string | null) => {
        if (!value) {
            return '—';
        }

        return new Date(value).toLocaleString(app.locale);
    };

    const currency = payout?.currency ?? 'EUR';
    const estimatedDisplay = (() => {
        if (!payout || payout.managed_by_admin) {
            return null;
        }
        if (payout.is_estimated && payout.amount != null) {
            return formatSellerMoney(payout.amount, currency, app.locale);
        }
        if (payout.estimated_amount != null && !payout.is_confirmed) {
            return formatSellerMoney(
                payout.estimated_amount,
                currency,
                app.locale,
            );
        }
        if (payout.display === 'pending_review') {
            return payoutLabels.pending_review;
        }
        if (payout.display === 'dash') {
            return '—';
        }
        return payoutLabels.not_calculated;
    })();

    const finalDisplay = (() => {
        if (!payout || payout.managed_by_admin) {
            return null;
        }
        if (payout.is_confirmed && payout.amount != null) {
            return formatSellerMoney(payout.amount, currency, app.locale);
        }
        if (payout.final_amount != null && payout.is_confirmed) {
            return formatSellerMoney(payout.final_amount, currency, app.locale);
        }
        if (
            payout.display === 'estimated' ||
            payout.status === 'pending_sale'
        ) {
            return payoutLabels.pending_sale;
        }
        if (payout.display === 'dash') {
            return '—';
        }
        return payoutLabels.pending_sale;
    })();

    return (
        <AppLayout
            title={t.show_title}
            subtitle={lead.lead_reference}
            headerActions={
                <div className="flex flex-wrap items-center gap-2">
                    {survey?.href && (
                        <Link
                            href={survey.href}
                            method={survey.action === 'start' ? 'post' : 'get'}
                            as={survey.action === 'start' ? 'button' : 'a'}
                        >
                            <Button size="sm" variant="secondary">
                                {survey.action_label}
                            </Button>
                        </Link>
                    )}
                    {(lead.status === 'draft' ||
                        lead.status === 'pending_evidence') && (
                        <Link href={route('seller.leads.edit', lead.id)}>
                            <Button size="sm">
                                {lead.status === 'draft'
                                    ? (t.continue_draft ?? t.edit_draft)
                                    : t.edit_draft}
                            </Button>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title={`${t.show_title} · ${lead.lead_reference}`} />

            <div className="flex flex-wrap items-center gap-3">
                <BackLink
                    href={route('seller.leads.index')}
                    label={common.back}
                    useHistory={false}
                />
                <p className="font-mono text-lg font-semibold text-rml-text">
                    {lead.lead_reference}
                </p>
                {lead.status && (
                    <StatusBadge
                        label={leadStatusLabel(lead.status, {
                            ...statuses,
                            ...leadStatuses,
                        })}
                        tone={leadStatusTone(lead.status)}
                    />
                )}
            </div>

            {survey && (
                <section className="rml-card space-y-2 p-4 sm:p-5">
                    <h2 className="text-base font-semibold text-rml-text">
                        {surveyT.card_title}
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        <StatusBadge
                            label={
                                surveyT.statuses?.[
                                    survey.status as keyof typeof surveyT.statuses
                                ] ?? survey.status
                            }
                            tone="neutral"
                        />
                    </div>
                    <p className="text-sm text-rml-muted">
                        {surveyT.eligibility}: {survey.eligibility_status}
                    </p>
                </section>
            )}

            {catastro && (
                <CatastroSellerStatusCard
                    cadastralReference={
                        catastro.lead_submitted?.cadastral_reference ?? null
                    }
                    sellerStatus={catastro.seller_status ?? null}
                    enabled={catastro.enabled !== false}
                />
            )}

            {(lead.rejection_reason ||
                lead.rejection_comment ||
                lead.requested_info) && (
                <div className="space-y-3">
                    {lead.rejection_reason && (
                        <Alert variant="error" title={t.rejection_reason}>
                            <div className="space-y-2">
                                <p>{lead.rejection_reason}</p>
                                {lead.rejection_comment ? (
                                    <p className="text-sm opacity-90">
                                        {lead.rejection_comment}
                                    </p>
                                ) : null}
                            </div>
                        </Alert>
                    )}
                    {!lead.rejection_reason && lead.rejection_comment && (
                        <Alert variant="error" title={t.rejection_reason}>
                            {lead.rejection_comment}
                        </Alert>
                    )}
                    {lead.requested_info && (
                        <Alert variant="warning" title={t.requested_info}>
                            {lead.requested_info}
                        </Alert>
                    )}
                </div>
            )}

            {payout && (
                <section className="rml-card space-y-3 p-4 sm:p-5">
                    <h2 className="text-base font-semibold text-rml-text">
                        {payoutLabels.estimate_title}
                    </h2>
                    {payout.managed_by_admin ? (
                        <p className="text-sm text-rml-muted">
                            {payoutLabels.managed_by_admin}
                        </p>
                    ) : (
                        <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <DetailRow
                                label={payoutLabels.lead_reference}
                                value={lead.lead_reference}
                            />
                            <DetailRow
                                label={common.scheme}
                                value={lead.scheme?.name}
                            />
                            <DetailRow
                                label={common.zone}
                                value={
                                    lead.zone
                                        ? `${lead.zone.code} — ${lead.zone.name}`
                                        : null
                                }
                            />
                            {payout.metric?.key && (
                                <DetailRow
                                    label={
                                        payoutLabels[
                                            `metric_${payout.metric.key}`
                                        ] ?? payout.metric.key
                                    }
                                    value={
                                        payout.metric.value != null
                                            ? payout.metric.key === 'area_m2' ||
                                              payout.metric.key ===
                                                  'property_size' ||
                                              payout.metric.key ===
                                                  'glazing_area'
                                                ? `${payout.metric.value} m²`
                                                : payout.metric.value
                                            : null
                                    }
                                />
                            )}
                            {payout.rate_percent != null && (
                                <DetailRow
                                    label={payoutLabels.commission_rate}
                                    value={`${payout.rate_percent}%`}
                                />
                            )}
                            <DetailRow
                                label={payoutLabels.estimated_payout}
                                value={estimatedDisplay}
                            />
                            <DetailRow
                                label={payoutLabels.final_payout}
                                value={finalDisplay}
                            />
                            <DetailRow
                                label={payoutLabels.payout_status}
                                value={sellerPayoutStatusLabel(
                                    payout.status,
                                    payoutLabels,
                                )}
                            />
                        </dl>
                    )}
                </section>
            )}

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.section_customer}
                </h2>
                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <DetailRow
                        label={t.first_name}
                        value={lead.customer_first_name}
                    />
                    <DetailRow
                        label={t.last_name}
                        value={lead.customer_last_name}
                    />
                    <DetailRow label={t.phone} value={lead.customer_phone} />
                    <DetailRow
                        label={t.whatsapp}
                        value={lead.customer_whatsapp}
                    />
                    <DetailRow label={t.email} value={lead.customer_email} />
                </dl>
            </section>

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.property_summary}
                </h2>
                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <DetailRow
                        label={t.address_1}
                        value={lead.address_line_1}
                    />
                    <DetailRow
                        label={t.address_2}
                        value={lead.address_line_2}
                    />
                    <DetailRow label={t.city} value={lead.city} />
                    <DetailRow label={t.postcode} value={lead.postcode} />
                    <DetailRow label={t.country} value={lead.country} />
                    <DetailRow
                        label={t.property_type}
                        value={lead.property_type}
                    />
                    <DetailRow label={t.epc_rating} value={lead.epc_rating} />
                    <DetailRow
                        label={common.size}
                        value={
                            lead.size_m2 != null ? `${lead.size_m2} m²` : null
                        }
                    />
                    <DetailRow
                        label={common.scheme}
                        value={lead.scheme?.name}
                    />
                    <DetailRow
                        label={common.zone}
                        value={
                            lead.zone
                                ? `${lead.zone.code} — ${lead.zone.name}`
                                : null
                        }
                    />
                    <DetailRow
                        label={common.submitted}
                        value={formatDate(lead.created_at)}
                    />
                </dl>
                {lead.notes && (
                    <div>
                        <p className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                            {t.notes}
                        </p>
                        <p className="mt-1 whitespace-pre-wrap text-sm text-rml-text">
                            {lead.notes}
                        </p>
                    </div>
                )}
            </section>

            {Object.keys(lead.metrics ?? {}).length > 0 && (
                <section className="rml-card space-y-4 p-5 sm:p-6">
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.section_scheme}
                    </h2>
                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {Object.entries(lead.metrics).map(([key, value]) => (
                            <DetailRow
                                key={key}
                                label={key}
                                value={
                                    value === null || value === undefined
                                        ? null
                                        : String(value)
                                }
                            />
                        ))}
                    </dl>
                </section>
            )}

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.evidence_list}
                </h2>
                {lead.evidence.length === 0 ? (
                    <EmptyState title={t.no_evidence} />
                ) : (
                    <ul className="divide-y divide-rml-border rounded-xl border border-rml-border">
                        {lead.evidence.map((file) => (
                            <li
                                key={file.id}
                                className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-rml-text">
                                        {file.original_name ??
                                            `#${file.id}`}
                                    </p>
                                    <p className="text-xs text-rml-muted">
                                        {file.file_type}
                                        {file.size != null
                                            ? ` · ${Math.round(file.size / 1024)} KB`
                                            : ''}
                                        {` · ${formatDate(file.created_at)}`}
                                    </p>
                                </div>
                                <div className="flex shrink-0 flex-wrap items-center gap-2">
                                    {file.view_url && (
                                        <a
                                            href={file.view_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-sm font-medium text-rml-primary hover:underline"
                                        >
                                            {t.view_evidence ?? common.view}
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
                                    {file.status && (
                                        <StatusBadge
                                            label={leadStatusLabel(
                                                file.status,
                                                {
                                                    ...statuses,
                                                    ...leadStatuses,
                                                },
                                            )}
                                            tone={leadStatusTone(file.status)}
                                        />
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {canUploadMore && (
                <section className="rml-card space-y-4 p-5 sm:p-6">
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.upload_more}
                    </h2>
                    <form onSubmit={submitEvidence} className="space-y-4">
                        <FileUpload
                            label={t.evidence_photos}
                            multiple
                            accept="image/jpeg,image/png,image/webp,image/heic,.jpg,.jpeg,.png,.webp,.heic"
                            files={fileMetas(data.evidence_photos)}
                            error={errors.evidence_photos}
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
                            label={t.evidence_video}
                            multiple={false}
                            accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm"
                            files={
                                data.evidence_video
                                    ? fileMetas([data.evidence_video])
                                    : []
                            }
                            error={errors.evidence_video}
                            onFilesSelected={(list) =>
                                setData('evidence_video', list.item(0))
                            }
                            onRemove={() => setData('evidence_video', null)}
                        />
                        <FileUpload
                            label={t.evidence_agreement}
                            multiple={false}
                            accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png"
                            files={
                                data.evidence_agreement
                                    ? fileMetas([data.evidence_agreement])
                                    : []
                            }
                            error={errors.evidence_agreement}
                            onFilesSelected={(list) =>
                                setData('evidence_agreement', list.item(0))
                            }
                            onRemove={() =>
                                setData('evidence_agreement', null)
                            }
                        />
                        <FileUpload
                            label={t.evidence_eligibility}
                            multiple
                            accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png"
                            files={fileMetas(data.evidence_eligibility)}
                            error={errors.evidence_eligibility}
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
                        <Button type="submit" disabled={processing}>
                            {t.upload_more}
                        </Button>
                    </form>
                </section>
            )}
        </AppLayout>
    );
}
